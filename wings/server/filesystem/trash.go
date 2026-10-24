package filesystem

import (
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"os"
	"path"
	"path/filepath"
	"regexp"
	"slices"
	"strconv"
	"strings"
	"time"

	"emperror.dev/errors"
	"golang.org/x/sys/unix"

	"github.com/pterodactyl/wings/internal/ufs"
)

// Hydra: trash of the file manager. Files deleted in the panel are moved next
// to the data directories of the servers (like the game profiles), so they
// cannot be reached through the file manager, SFTP, backups or the container,
// and are moved with a rename, which is instant. Every entry is a folder with
// the deleted file or folder ("item") and its details ("entry.json"). Entries
// are removed once they expire; until then their size counts against the disk
// of the server.

const (
	trashItem = "item"
	trashMeta = "entry.json"
)

var trashID = regexp.MustCompile(`^[a-f0-9]{32}$`)

var (
	ErrTrashNotFound = errors.New("filesystem: the trash entry does not exist")
	// ErrTrashUnavailable is returned when the server directory is on another
	// device than the trash, so files cannot be moved into it.
	ErrTrashUnavailable = errors.New("filesystem: the trash is on another device")
)

// TrashEntry is a file or folder in the trash.
type TrashEntry struct {
	ID string `json:"id"`
	// Path the entry was deleted from, e.g. "/plugins/old.jar".
	Path      string    `json:"path"`
	Directory bool      `json:"directory"`
	Size      int64     `json:"size"`
	DeletedAt time.Time `json:"deleted_at"`
	ExpiresAt time.Time `json:"expires_at"`
}

// TrashPath is the folder with the trash of the server.
func (fs *Filesystem) TrashPath() string {
	return filepath.Join(filepath.Dir(fs.Path()), ".hydra-trash", filepath.Base(fs.Path()))
}

func (fs *Filesystem) trashEntryPath(id string) (string, error) {
	if !trashID.MatchString(id) {
		return "", ErrTrashNotFound
	}
	return filepath.Join(fs.TrashPath(), id), nil
}

// cleanServerPath turns p into an absolute path inside the server, e.g.
// "plugins/../x" into "/x".
func cleanServerPath(p string) string {
	return path.Clean("/" + strings.TrimLeft(filepath.ToSlash(p), "/"))
}

// MoveToTrash moves the file or folder at p into the trash, where it is kept
// for the given time.
func (fs *Filesystem) MoveToTrash(p string, keep time.Duration) (TrashEntry, error) {
	dirfd, name, closeFd, err := fs.unixFS.SafePath(p)
	defer closeFd()
	if err != nil {
		return TrashEntry{}, err
	}
	// Never move the server directory itself.
	if name == "" || name == "." {
		return TrashEntry{}, &ufs.PathError{Op: "trash", Path: p, Err: ufs.ErrBadPathResolution}
	}
	st, err := fs.unixFS.Lstatat(dirfd, name)
	if err != nil {
		return TrashEntry{}, err
	}

	id, err := newTrashID()
	if err != nil {
		return TrashEntry{}, err
	}
	dir := filepath.Join(fs.TrashPath(), id)
	if err := os.MkdirAll(dir, 0o700); err != nil {
		return TrashEntry{}, err
	}
	tfd, err := unix.Open(dir, unix.O_DIRECTORY|unix.O_RDONLY|unix.O_CLOEXEC, 0)
	if err != nil {
		_ = os.RemoveAll(dir)
		return TrashEntry{}, err
	}
	defer unix.Close(tfd)

	if err := unix.Renameat(dirfd, name, tfd, trashItem); err != nil {
		_ = os.RemoveAll(dir)
		if errors.Is(err, unix.EXDEV) {
			return TrashEntry{}, ErrTrashUnavailable
		}
		return TrashEntry{}, &ufs.PathError{Op: "trash", Path: p, Err: err}
	}

	now := time.Now().UTC()
	entry := TrashEntry{
		ID:        id,
		Path:      cleanServerPath(p),
		Directory: st.IsDir(),
		Size:      st.Size(),
		DeletedAt: now,
		ExpiresAt: now.Add(keep),
	}
	if entry.Directory {
		entry.Size = directorySize(filepath.Join(dir, trashItem))
	}
	if err := writeTrashEntry(dir, entry); err != nil {
		// Without its details the entry could not be restored, so put it back.
		_ = unix.Renameat(tfd, trashItem, dirfd, name)
		_ = os.RemoveAll(dir)
		return TrashEntry{}, err
	}
	return entry, nil
}

// Trash lists the entries of the trash, the most recently deleted first.
func (fs *Filesystem) Trash() ([]TrashEntry, error) {
	dirs, err := os.ReadDir(fs.TrashPath())
	if err != nil {
		if os.IsNotExist(err) {
			return []TrashEntry{}, nil
		}
		return nil, err
	}
	out := make([]TrashEntry, 0, len(dirs))
	for _, d := range dirs {
		if !d.IsDir() || !trashID.MatchString(d.Name()) {
			continue
		}
		entry, err := readTrashEntry(filepath.Join(fs.TrashPath(), d.Name()))
		if err != nil {
			continue
		}
		out = append(out, entry)
	}
	slices.SortFunc(out, func(a, b TrashEntry) int {
		return b.DeletedAt.Compare(a.DeletedAt)
	})
	return out, nil
}

// TrashEntry returns a single entry of the trash.
func (fs *Filesystem) TrashEntry(id string) (TrashEntry, error) {
	dir, err := fs.trashEntryPath(id)
	if err != nil {
		return TrashEntry{}, err
	}
	return readTrashEntry(dir)
}

// RestoreTrash moves an entry back to where it was deleted from and returns
// that path. When something exists there by now, " (restored)" or
// " (restored 2)" … is added to the name.
func (fs *Filesystem) RestoreTrash(id string) (string, error) {
	dir, err := fs.trashEntryPath(id)
	if err != nil {
		return "", err
	}
	entry, err := readTrashEntry(dir)
	if err != nil {
		return "", err
	}

	if err := fs.mkdirAll(path.Dir(entry.Path), 0o755); err != nil {
		return "", err
	}
	target, err := fs.freeRestorePath(entry.Path)
	if err != nil {
		return "", err
	}

	dirfd, name, closeFd, err := fs.unixFS.SafePath(target)
	defer closeFd()
	if err != nil {
		return "", err
	}
	if name == "" || name == "." {
		return "", &ufs.PathError{Op: "restore", Path: target, Err: ufs.ErrBadPathResolution}
	}
	if _, err := fs.unixFS.Lstatat(dirfd, name); err == nil {
		return "", &ufs.PathError{Op: "restore", Path: target, Err: ufs.ErrExist}
	}
	tfd, err := unix.Open(dir, unix.O_DIRECTORY|unix.O_RDONLY|unix.O_CLOEXEC, 0)
	if err != nil {
		return "", err
	}
	defer unix.Close(tfd)
	if err := unix.Renameat(tfd, trashItem, dirfd, name); err != nil {
		return "", &ufs.PathError{Op: "restore", Path: target, Err: err}
	}
	_ = os.RemoveAll(dir)
	return target, nil
}

// freeRestorePath returns p, or p with " (restored)" added to its name when
// something exists at p.
func (fs *Filesystem) freeRestorePath(p string) (string, error) {
	dir, base := path.Split(p)
	ext := path.Ext(base)
	stem := strings.TrimSuffix(base, ext)
	// Keep ".tar" of ".tar.gz" with the extension.
	if strings.HasSuffix(stem, ".tar") {
		ext = ".tar" + ext
		stem = strings.TrimSuffix(stem, ".tar")
	}
	// Hidden files like ".env" have no extension.
	if stem == "" {
		stem, ext = base, ""
	}
	for i := 0; i < 1000; i++ {
		candidate := p
		switch i {
		case 0:
		case 1:
			candidate = dir + stem + " (restored)" + ext
		default:
			candidate = dir + stem + " (restored " + strconv.Itoa(i) + ")" + ext
		}
		if _, err := fs.unixFS.Lstat(candidate); err != nil {
			if errors.Is(err, ufs.ErrNotExist) {
				return candidate, nil
			}
			return "", err
		}
	}
	return "", &ufs.PathError{Op: "restore", Path: p, Err: ufs.ErrExist}
}

// DeleteTrash removes an entry of the trash for good; a missing one is no
// error.
func (fs *Filesystem) DeleteTrash(id string) error {
	dir, err := fs.trashEntryPath(id)
	if err != nil {
		return err
	}
	if err := os.RemoveAll(dir); err != nil {
		return err
	}
	fs.refreshUsage()
	return nil
}

// EmptyTrash removes every entry of the trash, also when the server is deleted.
func (fs *Filesystem) EmptyTrash() error {
	if err := os.RemoveAll(fs.TrashPath()); err != nil {
		return err
	}
	fs.refreshUsage()
	return nil
}

// PurgeExpiredTrash removes the entries that expired before now and returns
// how many were removed. Entries without readable details are removed a day
// after they were created, since they cannot be restored anyway.
func (fs *Filesystem) PurgeExpiredTrash(now time.Time) int {
	dirs, err := os.ReadDir(fs.TrashPath())
	if err != nil {
		return 0
	}
	removed := 0
	for _, d := range dirs {
		dir := filepath.Join(fs.TrashPath(), d.Name())
		entry, err := readTrashEntry(dir)
		if err != nil {
			info, ierr := d.Info()
			if ierr != nil || now.Sub(info.ModTime()) < 24*time.Hour {
				continue
			}
		} else if now.Before(entry.ExpiresAt) {
			continue
		}
		if os.RemoveAll(dir) == nil {
			removed++
		}
	}
	if removed > 0 {
		fs.refreshUsage()
	}
	return removed
}

// trashSize is the size of every entry of the trash.
func (fs *Filesystem) trashSize() int64 {
	return directorySize(fs.TrashPath())
}

func newTrashID() (string, error) {
	b := make([]byte, 16)
	if _, err := rand.Read(b); err != nil {
		return "", err
	}
	return hex.EncodeToString(b), nil
}

func writeTrashEntry(dir string, entry TrashEntry) error {
	b, err := json.Marshal(entry)
	if err != nil {
		return err
	}
	return os.WriteFile(filepath.Join(dir, trashMeta), b, 0o600)
}

func readTrashEntry(dir string) (TrashEntry, error) {
	b, err := os.ReadFile(filepath.Join(dir, trashMeta))
	if err != nil {
		if os.IsNotExist(err) {
			return TrashEntry{}, ErrTrashNotFound
		}
		return TrashEntry{}, err
	}
	var entry TrashEntry
	if err := json.Unmarshal(b, &entry); err != nil {
		return TrashEntry{}, err
	}
	if entry.ID != filepath.Base(dir) || !trashID.MatchString(entry.ID) {
		return TrashEntry{}, ErrTrashNotFound
	}
	if _, err := os.Lstat(filepath.Join(dir, trashItem)); err != nil {
		return TrashEntry{}, ErrTrashNotFound
	}
	entry.Path = cleanServerPath(entry.Path)
	return entry, nil
}

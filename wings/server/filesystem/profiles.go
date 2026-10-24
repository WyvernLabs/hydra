package filesystem

import (
	"io/fs"
	"os"
	"path/filepath"
	"regexp"

	"emperror.dev/errors"
)

// Hydra: game profiles (addon "games"). When a server switches to another
// game, its files can be kept as a profile to switch back to later. Profiles
// are stored next to the data directories of the servers, so they cannot be
// reached through the file manager, SFTP, backups or the container, and are
// moved with a rename, which is instant. Their size counts against the disk
// of the server.

var profileID = regexp.MustCompile(`^[a-z0-9-]{8,64}$`)

var (
	ErrProfileExists   = errors.New("filesystem: the profile exists already")
	ErrProfileNotFound = errors.New("filesystem: the profile does not exist")
	ErrRootNotEmpty    = errors.New("filesystem: the server directory is not empty")
	errProfileID       = errors.New("filesystem: invalid profile id")
)

// ProfilesPath is the folder with the profiles of the server.
func (fs *Filesystem) ProfilesPath() string {
	return filepath.Join(filepath.Dir(fs.Path()), ".hydra-profiles", filepath.Base(fs.Path()))
}

func (fs *Filesystem) profilePath(id string) (string, error) {
	if !profileID.MatchString(id) {
		return "", errProfileID
	}
	return filepath.Join(fs.ProfilesPath(), id), nil
}

// SaveProfile moves every file of the server into a new profile and returns
// its size.
func (fs *Filesystem) SaveProfile(id string) (int64, error) {
	dir, err := fs.profilePath(id)
	if err != nil {
		return 0, err
	}
	if _, err := os.Lstat(dir); err == nil {
		return 0, ErrProfileExists
	}
	if err := os.MkdirAll(dir, 0o700); err != nil {
		return 0, err
	}
	if err := moveEntries(fs.Path(), dir); err != nil {
		// Put back what was moved already.
		_ = moveEntries(dir, fs.Path())
		_ = os.Remove(dir)
		return 0, err
	}
	fs.refreshUsage()
	return directorySize(dir), nil
}

// LoadProfile moves the files of a profile back into the empty directory of
// the server and removes the profile.
func (fs *Filesystem) LoadProfile(id string) error {
	dir, err := fs.profilePath(id)
	if err != nil {
		return err
	}
	if st, err := os.Lstat(dir); err != nil || !st.IsDir() {
		return ErrProfileNotFound
	}
	entries, err := os.ReadDir(fs.Path())
	if err != nil {
		return err
	}
	if len(entries) > 0 {
		return ErrRootNotEmpty
	}
	if err := moveEntries(dir, fs.Path()); err != nil {
		_ = moveEntries(fs.Path(), dir)
		return err
	}
	_ = os.Remove(dir)
	fs.refreshUsage()
	return nil
}

// DeleteProfile removes a profile; a missing one is no error.
func (fs *Filesystem) DeleteProfile(id string) error {
	dir, err := fs.profilePath(id)
	if err != nil {
		return err
	}
	if err := os.RemoveAll(dir); err != nil {
		return err
	}
	fs.refreshUsage()
	return nil
}

// DeleteProfiles removes every profile of the server, when it is deleted.
func (fs *Filesystem) DeleteProfiles() error {
	return os.RemoveAll(fs.ProfilesPath())
}

// profilesSize is the size of every profile of the server.
func (fs *Filesystem) profilesSize() int64 {
	return directorySize(fs.ProfilesPath())
}

func (fs *Filesystem) refreshUsage() {
	_, _ = fs.DiskUsage(false)
}

// moveEntries renames every entry of from into to. Links are moved as they
// are, without following them.
func moveEntries(from, to string) error {
	entries, err := os.ReadDir(from)
	if err != nil {
		return err
	}
	for _, e := range entries {
		if err := os.Rename(filepath.Join(from, e.Name()), filepath.Join(to, e.Name())); err != nil {
			return errors.Wrap(err, "filesystem: could not move "+e.Name())
		}
	}
	return nil
}

// directorySize adds up the regular files below dir, 0 when it is missing.
func directorySize(dir string) int64 {
	var size int64
	_ = filepath.WalkDir(dir, func(_ string, d fs.DirEntry, err error) error {
		if err != nil {
			return nil
		}
		if d.Type().IsRegular() {
			if info, err := d.Info(); err == nil {
				size += info.Size()
			}
		}
		return nil
	})
	return size
}

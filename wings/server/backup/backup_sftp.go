package backup

import (
	"context"
	"io"
	"net"
	"net/netip"
	"os"
	"path"
	"strconv"
	"strings"
	"time"

	"emperror.dev/errors"
	"github.com/juju/ratelimit"
	"github.com/mholt/archives"
	"github.com/pkg/sftp"
	"golang.org/x/crypto/ssh"

	"github.com/pterodactyl/wings/config"
	wnet "github.com/pterodactyl/wings/internal/network"
	"github.com/pterodactyl/wings/remote"
	"github.com/pterodactyl/wings/server/filesystem"
)

// SftpTarget (Hydra) is an SFTP server backups are stored on: either one an
// admin set for the node, or the own server of a user. The panel sends it with
// every request, the node does not keep it.
type SftpTarget struct {
	Host       string `json:"host"`
	Port       int    `json:"port"`
	Username   string `json:"username"`
	Password   string `json:"password"`
	PrivateKey string `json:"private_key"`
	// Folder the backups are stored in, relative to the login folder unless it
	// starts with a slash.
	Path string `json:"path"`
	// SHA256 fingerprint of the host key, as returned by a connection test.
	HostKey string `json:"host_key"`
	// AllowInternal permits addresses in private networks. Only set for
	// targets of an admin, so users cannot reach the node's own network.
	AllowInternal bool `json:"allow_internal"`
}

// SftpFile is a backup archive found on an SFTP server.
type SftpFile struct {
	Name     string    `json:"name"`
	Size     int64     `json:"size"`
	Modified time.Time `json:"modified"`
}

var (
	ErrSftpHostKey    = errors.New("the host key of the SFTP server has changed")
	ErrSftpAddress    = errors.New("the SFTP server is in a private network, which is not allowed")
	errSftpTarget     = errors.New("the SFTP server settings are incomplete")
	errSftpFileName   = errors.New("invalid backup file name")
	sftpDialTimeout   = 15 * time.Second
	sftpFileExtension = ".tar.gz"
)

// Validate checks that the target can be connected to.
func (t *SftpTarget) Validate() error {
	if t.Port == 0 {
		t.Port = 22
	}
	if t.Host == "" || strings.ContainsAny(t.Host, " /\\@") || t.Port < 1 || t.Port > 65535 {
		return errSftpTarget
	}
	if t.Username == "" || (t.Password == "" && t.PrivateKey == "") {
		return errSftpTarget
	}
	if strings.ContainsRune(t.Path, 0) {
		return errSftpTarget
	}
	return nil
}

// ValidSftpFileName reports whether name can be used as the name of a backup
// file on an SFTP server, it may not lead into another folder.
func ValidSftpFileName(name string) bool {
	return name != "" && len(name) <= 255 && name != "." && name != ".." && !strings.ContainsAny(name, "/\\\x00")
}

func (t *SftpTarget) remotePath(file string) (string, error) {
	if !ValidSftpFileName(file) {
		return "", errSftpFileName
	}
	dir := strings.TrimSpace(t.Path)
	if dir == "" {
		dir = "."
	}
	return path.Join(dir, file), nil
}

// SftpConn is an open connection to an SFTP server.
type SftpConn struct {
	*sftp.Client
	target *SftpTarget
	ssh    *ssh.Client
	// Fingerprint of the host key the server presented.
	Fingerprint string
}

func (c *SftpConn) Close() error {
	_ = c.Client.Close()
	return c.ssh.Close()
}

// Connect opens a connection to the target. Unless trustAnyKey is set the
// host key has to match the stored fingerprint; a connection test sets it to
// learn the fingerprint of a new server.
func (t *SftpTarget) Connect(ctx context.Context, trustAnyKey bool) (*SftpConn, error) {
	if err := t.Validate(); err != nil {
		return nil, err
	}
	if !trustAnyKey && t.HostKey == "" {
		return nil, ErrSftpHostKey
	}

	var auth []ssh.AuthMethod
	if t.PrivateKey != "" {
		signer, err := ssh.ParsePrivateKey([]byte(t.PrivateKey))
		if err != nil {
			return nil, errors.New("the private key could not be read (keys with a passphrase are not supported)")
		}
		auth = append(auth, ssh.PublicKeys(signer))
	}
	if t.Password != "" {
		auth = append(auth, ssh.Password(t.Password))
	}

	var fingerprint string
	cfg := &ssh.ClientConfig{
		User: t.Username,
		Auth: auth,
		HostKeyCallback: func(_ string, _ net.Addr, key ssh.PublicKey) error {
			fingerprint = ssh.FingerprintSHA256(key)
			if !trustAnyKey && fingerprint != t.HostKey {
				return ErrSftpHostKey
			}
			return nil
		},
		Timeout: sftpDialTimeout,
	}

	conn, err := t.dial(ctx)
	if err != nil {
		return nil, err
	}
	_ = conn.SetDeadline(time.Now().Add(sftpDialTimeout))
	address := net.JoinHostPort(t.Host, strconv.Itoa(t.Port))
	c, chans, reqs, err := ssh.NewClientConn(conn, address, cfg)
	if err != nil {
		_ = conn.Close()
		if errors.Is(err, ErrSftpHostKey) {
			return nil, ErrSftpHostKey
		}
		return nil, errors.Wrap(err, "could not log in to the SFTP server")
	}
	_ = conn.SetDeadline(time.Time{})
	client := ssh.NewClient(c, chans, reqs)
	s, err := sftp.NewClient(client, sftp.UseConcurrentWrites(true))
	if err != nil {
		_ = client.Close()
		return nil, errors.Wrap(err, "the server does not offer SFTP")
	}
	return &SftpConn{Client: s, target: t, ssh: client, Fingerprint: fingerprint}, nil
}

// dial connects to the host, refusing addresses in private networks unless
// they are allowed for this target.
func (t *SftpTarget) dial(ctx context.Context) (net.Conn, error) {
	ctx, cancel := context.WithTimeout(ctx, sftpDialTimeout)
	defer cancel()
	ips, err := net.DefaultResolver.LookupIPAddr(ctx, t.Host)
	if err != nil {
		return nil, errors.New("the SFTP server could not be found")
	}
	var lastErr error = errors.New("the SFTP server could not be found")
	for _, ip := range ips {
		addr, ok := netip.AddrFromSlice(ip.IP)
		if !ok {
			continue
		}
		addr = addr.Unmap()
		if !t.AllowInternal && wnet.IsInternal(addr) && !wnet.IsAllowed(config.Get().System.Backups.RestoreHostAllowlist, t.Host, addr) {
			lastErr = ErrSftpAddress
			continue
		}
		var d net.Dialer
		conn, err := d.DialContext(ctx, "tcp", net.JoinHostPort(addr.String(), strconv.Itoa(t.Port)))
		if err == nil {
			return conn, nil
		}
		lastErr = errors.Wrap(err, "could not connect to the SFTP server")
	}
	return nil, lastErr
}

// closeOnDone closes the connection when ctx ends, which stops a running
// transfer. The returned function ends the watch.
func (c *SftpConn) closeOnDone(ctx context.Context) func() {
	done := make(chan struct{})
	go func() {
		select {
		case <-ctx.Done():
			_ = c.Close()
		case <-done:
		}
	}()
	return func() { close(done) }
}

// Upload copies the local file to the target under the given name. It is
// written to a temporary name first, so an aborted upload never looks like a
// complete backup.
func (c *SftpConn) Upload(ctx context.Context, local string, file string, progress filesystem.ProgressFunc) error {
	p, err := c.target.remotePath(file)
	if err != nil {
		return err
	}
	f, err := os.Open(local)
	if err != nil {
		return err
	}
	defer f.Close()
	st, err := f.Stat()
	if err != nil {
		return err
	}

	stop := c.closeOnDone(ctx)
	defer stop()

	if dir := path.Dir(p); dir != "." && dir != "/" {
		if err := c.MkdirAll(dir); err != nil {
			return errors.Wrap(err, "could not create the backup folder on the SFTP server")
		}
	}
	part := p + ".part"
	w, err := c.Create(part)
	if err != nil {
		return errors.Wrap(err, "could not create the backup on the SFTP server")
	}
	_, err = io.Copy(w, filesystem.NewProgressReader(f, st.Size(), progress))
	if cerr := w.Close(); err == nil {
		err = cerr
	}
	if err != nil {
		_ = c.Remove(part)
		if ctx.Err() != nil {
			return ctx.Err()
		}
		return errors.Wrap(err, "the upload to the SFTP server failed")
	}
	if err := c.PosixRename(part, p); err != nil {
		_ = c.Remove(p)
		if err := c.Rename(part, p); err != nil {
			_ = c.Remove(part)
			return errors.Wrap(err, "could not rename the backup on the SFTP server")
		}
	}
	return nil
}

// Open opens a backup file on the target for reading.
func (c *SftpConn) Open(file string) (*sftp.File, os.FileInfo, error) {
	p, err := c.target.remotePath(file)
	if err != nil {
		return nil, nil, err
	}
	f, err := c.Client.Open(p)
	if err != nil {
		return nil, nil, err
	}
	st, err := f.Stat()
	if err != nil {
		_ = f.Close()
		return nil, nil, err
	}
	if !st.Mode().IsRegular() {
		_ = f.Close()
		return nil, nil, os.ErrNotExist
	}
	return f, st, nil
}

// Delete removes a backup file from the target, a missing file is no error.
func (c *SftpConn) Delete(file string) error {
	p, err := c.target.remotePath(file)
	if err != nil {
		return err
	}
	if err := c.Remove(p); err != nil && !errors.Is(err, os.ErrNotExist) {
		return err
	}
	return nil
}

// List returns the backup archives in the folder of the target.
func (c *SftpConn) List() ([]SftpFile, error) {
	dir := strings.TrimSpace(c.target.Path)
	if dir == "" {
		dir = "."
	}
	entries, err := c.ReadDir(dir)
	if err != nil {
		if errors.Is(err, os.ErrNotExist) {
			return []SftpFile{}, nil
		}
		return nil, err
	}
	files := []SftpFile{}
	for _, e := range entries {
		if !e.Mode().IsRegular() || !strings.HasSuffix(e.Name(), sftpFileExtension) || !ValidSftpFileName(e.Name()) {
			continue
		}
		files = append(files, SftpFile{Name: e.Name(), Size: e.Size(), Modified: e.ModTime()})
	}
	return files, nil
}

// SftpBackup creates the archive on the node like a local backup, then moves
// it to the SFTP target and removes the local copy.
type SftpBackup struct {
	Backup
	Target SftpTarget
	// Name of the archive on the target, "<uuid>.tar.gz" unless the backup was
	// imported from files already on the target.
	File string
}

var _ BackupInterface = (*SftpBackup)(nil)

func NewSftp(client remote.Client, uuid string, ignore string, target SftpTarget, file string) *SftpBackup {
	if file == "" {
		file = uuid + sftpFileExtension
	}
	return &SftpBackup{
		Backup: Backup{
			client:  client,
			Uuid:    uuid,
			Ignore:  ignore,
			adapter: SftpBackupAdapter,
		},
		Target: target,
		File:   file,
	}
}

// Remove removes the local copy of the backup.
func (s *SftpBackup) Remove() error {
	if err := s.validateIdentifier(); err != nil {
		return err
	}
	return os.Remove(s.Path())
}

// WithLogContext attaches additional context to the log output for this backup.
func (s *SftpBackup) WithLogContext(c map[string]interface{}) {
	s.logContext = c
}

// Generate creates the archive and uploads it to the SFTP target.
func (s *SftpBackup) Generate(ctx context.Context, fsys *filesystem.Filesystem, ignore string) (*ArchiveDetails, error) {
	if err := s.validateIdentifier(); err != nil {
		return nil, err
	}
	if !ValidSftpFileName(s.File) {
		return nil, errSftpFileName
	}
	defer func() {
		if err := s.Remove(); err != nil && !errors.Is(err, os.ErrNotExist) {
			s.log().WithField("error", err).Warn("failed to remove local copy of backup")
		}
	}()

	if err := s.createArchive(ctx, fsys, ignore); err != nil {
		return nil, err
	}
	ad, err := s.Details(ctx, nil)
	if err != nil {
		return nil, errors.WrapIf(err, "backup: failed to get archive details")
	}

	conn, err := s.Target.Connect(ctx, false)
	if err != nil {
		return nil, SftpError{err}
	}
	defer conn.Close()
	s.log().WithField("host", s.Target.Host).Info("uploading backup to sftp server")
	if err := conn.Upload(ctx, s.Path(), s.File, s.phase("upload")); err != nil {
		return nil, SftpError{err}
	}
	s.log().Info("uploaded backup to sftp server")
	return ad, nil
}

// Restore extracts the archive read from r, which the caller opened on the
// SFTP target.
func (s *SftpBackup) Restore(ctx context.Context, r io.Reader, callback RestoreCallback) error {
	reader := r
	if writeLimit := int64(config.Get().System.Backups.WriteLimit * 1024 * 1024); writeLimit > 0 {
		reader = ratelimit.Reader(r, ratelimit.NewBucketWithRate(float64(writeLimit), writeLimit))
	}
	return format.Extract(ctx, reader, func(ctx context.Context, f archives.FileInfo) error {
		r, err := f.Open()
		if err != nil {
			return err
		}
		defer r.Close()
		return callback(f.NameInArchive, f.FileInfo, r)
	})
}

// SftpError marks an error of the SFTP server, so its message can be shown to
// the user.
type SftpError struct {
	err error
}

func (e SftpError) Error() string {
	return e.err.Error()
}

func (e SftpError) Unwrap() error {
	return e.err
}

package server

import (
	"context"
	"io"
	"os"
	"path"
	"strings"
	"sync"
	"time"

	"emperror.dev/errors"
	"github.com/pkg/sftp"

	"github.com/pterodactyl/wings/internal/ufs"
	"github.com/pterodactyl/wings/server/backup"
	"github.com/pterodactyl/wings/server/filesystem"
)

// ImportProgressEvent (Hydra) reports how far an import from another server
// over SFTP is: bytes and files done, the names of the files copied since the
// last event, and at the end whether it worked.
const ImportProgressEvent = "hydra import progress"

// MaxImportFiles is the most files one import copies.
const MaxImportFiles = 500000

// ErrImportRunning is returned when an import is started while one runs.
var ErrImportRunning = errors.New("an import is running already")

// ImportStatus is what the panel and the browser see of an import.
type ImportStatus struct {
	State      string `json:"state"` // "listing", "copying", "done", "failed" or "" when there was none
	Done       int64  `json:"done"`
	Total      int64  `json:"total"`
	Files      int    `json:"files"`
	FilesTotal int    `json:"files_total"`
	Error      string `json:"error,omitempty"`
}

type importJob struct {
	mu     sync.Mutex
	status ImportStatus
	cancel context.CancelFunc
	// Files copied since the last event.
	lines []string
	last  time.Time
}

var imports = struct {
	sync.Mutex
	m map[string]*importJob
}{m: map[string]*importJob{}}

// ImportStatus returns the state of the last import of the server.
func (s *Server) ImportStatus() ImportStatus {
	imports.Lock()
	job := imports.m[s.ID()]
	imports.Unlock()
	if job == nil {
		return ImportStatus{}
	}
	job.mu.Lock()
	defer job.mu.Unlock()
	return job.status
}

// CancelImport stops a running import.
func (s *Server) CancelImport() {
	imports.Lock()
	job := imports.m[s.ID()]
	imports.Unlock()
	if job != nil && job.cancel != nil {
		job.cancel()
	}
}

// StartImport copies the folder source of the SFTP server into the folder
// target of this server in the background. The connection is opened here, so
// a wrong password or host key is returned right away.
func (s *Server) StartImport(target backup.SftpTarget, source, into string) error {
	imports.Lock()
	if job := imports.m[s.ID()]; job != nil {
		job.mu.Lock()
		running := job.status.State == "listing" || job.status.State == "copying"
		job.mu.Unlock()
		if running {
			imports.Unlock()
			return ErrImportRunning
		}
	}
	ctx, cancel := context.WithCancel(s.Context())
	job := &importJob{cancel: cancel, status: ImportStatus{State: "listing"}}
	imports.m[s.ID()] = job
	imports.Unlock()

	conn, err := target.Connect(ctx, false)
	if err != nil {
		cancel()
		job.finish(s, err)
		return err
	}

	go func() {
		defer cancel()
		defer conn.Close()
		// Closing the connection stops a transfer that waits for the network.
		go func() {
			<-ctx.Done()
			_ = conn.Close()
		}()
		err := s.runImport(ctx, job, conn.Client, source, into)
		if err == nil && ctx.Err() != nil {
			err = errors.New("the import was cancelled")
		}
		job.finish(s, err)
		if err != nil {
			s.Log().WithField("error", err).Warn("hydra: import from SFTP failed")
		}
	}()
	return nil
}

type importEntry struct {
	remote string
	local  string
	size   int64
	mode   os.FileMode
	dir    bool
}

func (s *Server) runImport(ctx context.Context, job *importJob, client *sftp.Client, source, into string) error {
	source = strings.TrimSpace(source)
	if source == "" {
		source = "."
	}
	into = strings.Trim(path.Clean("/"+strings.TrimSpace(into)), "/")

	// First find everything, so the progress knows the total.
	var entries []importEntry
	var total int64
	walker := client.Walk(source)
	for walker.Step() {
		if ctx.Err() != nil {
			return ctx.Err()
		}
		if err := walker.Err(); err != nil {
			if walker.Path() == source {
				return errors.Wrap(err, "the source folder could not be read")
			}
			// Unreadable entries are skipped.
			continue
		}
		rel := strings.TrimPrefix(strings.TrimPrefix(walker.Path(), source), "/")
		if rel == "" {
			if !walker.Stat().IsDir() {
				return errors.New("the source is no folder")
			}
			continue
		}
		info := walker.Stat()
		mode := info.Mode()
		// Links could point anywhere on the other server; only plain files and folders are copied.
		if mode&os.ModeType != 0 && !mode.IsDir() {
			continue
		}
		local := path.Join(into, rel)
		entries = append(entries, importEntry{remote: walker.Path(), local: local, size: info.Size(), mode: mode, dir: mode.IsDir()})
		if !mode.IsDir() {
			total += info.Size()
		}
		if len(entries) > MaxImportFiles {
			return errors.New("the source folder holds too many files")
		}
	}

	files := 0
	for _, e := range entries {
		if !e.dir {
			files++
		}
	}
	job.update(s, func(st *ImportStatus) {
		st.State = "copying"
		st.Total = total
		st.FilesTotal = files
	}, "", true)

	fs := s.Filesystem()
	if err := fs.HasSpaceFor(total); err != nil {
		return errors.New("the server has not enough disk space for the files")
	}

	for _, e := range entries {
		if ctx.Err() != nil {
			return ctx.Err()
		}
		if e.dir {
			if err := fs.CreateDirectory(path.Base(e.local), path.Dir(e.local)); err != nil {
				return errors.Wrap(err, "could not create "+e.local)
			}
			continue
		}
		if err := s.importFile(ctx, job, client, fs, e); err != nil {
			if filesystem.IsErrorCode(err, filesystem.ErrCodeDiskSpace) {
				return errors.New("the server has not enough disk space for the files")
			}
			return errors.Wrap(err, "could not copy "+e.local)
		}
		job.update(s, func(st *ImportStatus) { st.Files++ }, e.local, false)
	}
	return nil
}

func (s *Server) importFile(ctx context.Context, job *importJob, client *sftp.Client, fs *filesystem.Filesystem, e importEntry) error {
	f, err := client.Open(e.remote)
	if err != nil {
		return err
	}
	defer f.Close()
	r := &importReader{ctx: ctx, r: f, add: func(n int64) {
		job.update(s, func(st *ImportStatus) { st.Done += n }, "", false)
	}}
	mode := ufs.FileMode(e.mode.Perm() | 0o600)
	return fs.Write(e.local, r, e.size, mode)
}

// importReader counts the bytes read and stops when the import is cancelled.
type importReader struct {
	ctx context.Context
	r   io.Reader
	add func(int64)
}

func (r *importReader) Read(p []byte) (int, error) {
	if err := r.ctx.Err(); err != nil {
		return 0, err
	}
	n, err := r.r.Read(p)
	if n > 0 {
		r.add(int64(n))
	}
	return n, err
}

// update changes the status and publishes it, at most four times a second
// unless force is set. line is a file that was copied.
func (j *importJob) update(s *Server, change func(*ImportStatus), line string, force bool) {
	j.mu.Lock()
	defer j.mu.Unlock()
	change(&j.status)
	if line != "" {
		j.lines = append(j.lines, line)
	}
	if !force && time.Since(j.last) < 250*time.Millisecond {
		return
	}
	j.publish(s)
}

func (j *importJob) finish(s *Server, err error) {
	j.mu.Lock()
	defer j.mu.Unlock()
	if err != nil {
		j.status.State = "failed"
		j.status.Error = err.Error()
	} else {
		j.status.State = "done"
		j.status.Done = j.status.Total
	}
	j.publish(s)
}

// publish sends the status with the files since the last event; long lists
// only keep their end, the browser shows how many were left out.
func (j *importJob) publish(s *Server) {
	lines := j.lines
	skipped := 0
	if len(lines) > 50 {
		skipped = len(lines) - 50
		lines = lines[skipped:]
	}
	j.lines = nil
	j.last = time.Now()
	s.Events().Publish(ImportProgressEvent, map[string]interface{}{
		"status":  j.status,
		"lines":   lines,
		"skipped": skipped,
	})
}

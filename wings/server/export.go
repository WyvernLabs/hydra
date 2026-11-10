package server

import (
	"context"
	"io"
	"os"
	"path"
	"strings"
	"sync"

	"emperror.dev/errors"
	"github.com/pkg/sftp"

	"github.com/pterodactyl/wings/internal/ufs"
	"github.com/pterodactyl/wings/server/backup"
)

// ExportProgressEvent (Hydra) reports how far an export of the server's files
// to another server over SFTP is. It carries the same data as an import.
const ExportProgressEvent = "hydra export progress"

// ErrExportRunning is returned when an export is started while one runs.
var ErrExportRunning = errors.New("an export is running already")

var exports = struct {
	sync.Mutex
	m map[string]*importJob
}{m: map[string]*importJob{}}

// ExportStatus returns the state of the last export of the server.
func (s *Server) ExportStatus() ImportStatus {
	exports.Lock()
	job := exports.m[s.ID()]
	exports.Unlock()
	if job == nil {
		return ImportStatus{}
	}
	job.mu.Lock()
	defer job.mu.Unlock()
	return job.status
}

// CancelExport stops a running export.
func (s *Server) CancelExport() {
	exports.Lock()
	job := exports.m[s.ID()]
	exports.Unlock()
	if job != nil && job.cancel != nil {
		job.cancel()
	}
}

// StartExport uploads the folder source of this server into the folder target
// on the SFTP server in the background. The connection is opened here, so a
// wrong password or host key is returned right away.
func (s *Server) StartExport(remote backup.SftpTarget, source, target string) error {
	exports.Lock()
	if job := exports.m[s.ID()]; job != nil {
		job.mu.Lock()
		running := job.status.State == "listing" || job.status.State == "copying"
		job.mu.Unlock()
		if running {
			exports.Unlock()
			return ErrExportRunning
		}
	}
	ctx, cancel := context.WithCancel(s.Context())
	job := &importJob{cancel: cancel, status: ImportStatus{State: "listing"}, event: ExportProgressEvent}
	exports.m[s.ID()] = job
	exports.Unlock()

	conn, err := remote.Connect(ctx, false)
	if err != nil {
		cancel()
		job.finish(s, err)
		return err
	}

	go func() {
		defer cancel()
		defer conn.Close()
		go func() {
			<-ctx.Done()
			_ = conn.Close()
		}()
		err := s.runExport(ctx, job, conn.Client, source, target)
		if err == nil && ctx.Err() != nil {
			err = errors.New("the export was cancelled")
		}
		job.finish(s, err)
		if err != nil {
			s.Log().WithField("error", err).Warn("hydra: export to SFTP failed")
		}
	}()
	return nil
}

type exportEntry struct {
	local  string
	remote string
	size   int64
	mode   os.FileMode
	dir    bool
}

func (s *Server) runExport(ctx context.Context, job *importJob, client *sftp.Client, source, target string) error {
	source = strings.Trim(path.Clean("/"+strings.TrimSpace(source)), "/")
	if source == "" {
		source = "."
	}
	target = strings.TrimSpace(target)
	if target == "" {
		target = "."
	}

	fs := s.Filesystem()
	if st, err := fs.Stat(source); err != nil || !st.IsDir() {
		return errors.New("the source folder does not exist on this server")
	}

	// First find everything, so the progress knows the total. Links are
	// skipped, they could point anywhere.
	var entries []exportEntry
	var total int64
	err := fs.UnixFS().WalkDir(source, func(p string, d ufs.DirEntry, err error) error {
		if ctx.Err() != nil {
			return ctx.Err()
		}
		if err != nil {
			if errors.Is(err, ufs.ErrNotExist) {
				return nil
			}
			if p == source {
				return err
			}
			// Unreadable entries are skipped.
			if d != nil && d.IsDir() {
				return ufs.SkipDir
			}
			return nil
		}
		rel := p
		if source != "." {
			rel = strings.TrimPrefix(strings.TrimPrefix(p, source), "/")
		}
		if rel == "" || rel == "." {
			return nil
		}
		if !d.IsDir() && !d.Type().IsRegular() {
			return nil
		}
		info, err := d.Info()
		if err != nil {
			return nil
		}
		entries = append(entries, exportEntry{
			local:  p,
			remote: path.Join(target, rel),
			size:   info.Size(),
			mode:   info.Mode(),
			dir:    d.IsDir(),
		})
		if !d.IsDir() {
			total += info.Size()
		}
		if len(entries) > MaxImportFiles {
			return errors.New("the source folder holds too many files")
		}
		return nil
	})
	if err != nil {
		return errors.Wrap(err, "the source folder could not be read")
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

	if err := client.MkdirAll(target); err != nil {
		return errors.Wrap(err, "the target folder could not be created")
	}

	for _, e := range entries {
		if ctx.Err() != nil {
			return ctx.Err()
		}
		if e.dir {
			if err := client.MkdirAll(e.remote); err != nil {
				return errors.Wrap(err, "could not create "+e.remote)
			}
			continue
		}
		if err := s.exportFile(ctx, job, client, e); err != nil {
			return errors.Wrap(err, "could not upload "+e.local)
		}
		job.update(s, func(st *ImportStatus) { st.Files++ }, e.local, false)
	}
	return nil
}

func (s *Server) exportFile(ctx context.Context, job *importJob, client *sftp.Client, e exportEntry) error {
	f, _, err := s.Filesystem().File(e.local)
	if err != nil {
		return err
	}
	defer f.Close()

	out, err := client.OpenFile(e.remote, os.O_WRONLY|os.O_CREATE|os.O_TRUNC)
	if err != nil {
		return err
	}
	r := &importReader{ctx: ctx, r: f, add: func(n int64) {
		job.update(s, func(st *ImportStatus) { st.Done += n }, "", false)
	}}
	if _, err := io.Copy(out, r); err != nil {
		_ = out.Close()
		return err
	}
	if err := out.Close(); err != nil {
		return err
	}
	// Keep the permissions, the target may not support it.
	_ = client.Chmod(e.remote, e.mode.Perm())
	return nil
}

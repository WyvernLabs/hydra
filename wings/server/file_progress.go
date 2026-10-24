package server

import (
	"regexp"
	"sync"
	"time"
)

// FileProgressEvent (Hydra) reports the progress of an upload, download,
// archive or extraction to the websocket, so the panel can show how long it
// will take. The panel picks the id, the event carries nothing but numbers.
const FileProgressEvent = "hydra file progress"

var fileProgressID = regexp.MustCompile(`^[A-Za-z0-9-]{8,64}$`)

// FileProgress sends the progress of one operation, at most twice a second.
type FileProgress struct {
	s    *Server
	id   string
	mu   sync.Mutex
	last time.Time
	sent bool
}

// NewFileProgress returns nil when id is not a valid progress id, every method
// of FileProgress does nothing then.
func (s *Server) NewFileProgress(id string) *FileProgress {
	if !fileProgressID.MatchString(id) {
		return nil
	}
	return &FileProgress{s: s, id: id}
}

// Update reports that done out of total bytes have been handled.
func (p *FileProgress) Update(done, total int64) {
	if p == nil {
		return
	}
	p.mu.Lock()
	defer p.mu.Unlock()
	now := time.Now()
	if p.sent && now.Sub(p.last) < 500*time.Millisecond {
		return
	}
	p.sent = true
	p.last = now
	p.publish(done, total, false, false)
}

// Finish reports that the operation has ended, failed tells whether it did
// not complete.
func (p *FileProgress) Finish(failed bool) {
	if p == nil {
		return
	}
	p.mu.Lock()
	defer p.mu.Unlock()
	p.publish(0, 0, true, failed)
}

func (p *FileProgress) publish(done, total int64, finished, failed bool) {
	p.s.Events().Publish(FileProgressEvent, map[string]interface{}{
		"id":       p.id,
		"done":     done,
		"total":    total,
		"finished": finished,
		"failed":   failed,
	})
}

package filesystem

import (
	"io"
	"sync/atomic"

	"github.com/pterodactyl/wings/internal/ufs"
)

// ProgressFunc (Hydra) receives how many of total bytes an operation on the
// filesystem has handled so far. It may be called very often, the receiver is
// expected to throttle whatever it does with the values.
type ProgressFunc func(done, total int64)

// progressFile counts every byte read from an archive, through Read as well as
// ReadAt, so that both streamed formats (tar) and formats read at offsets (zip)
// report their progress. Seeking is passed through, since some formats need it.
type progressFile struct {
	ufs.File
	total int64
	done  atomic.Int64
	fn    ProgressFunc
}

func newProgressFile(f ufs.File, total int64, fn ProgressFunc) *progressFile {
	return &progressFile{File: f, total: total, fn: fn}
}

func (p *progressFile) add(n int) {
	if n <= 0 {
		return
	}
	done := p.done.Add(int64(n))
	p.fn(min(done, p.total), p.total)
}

func (p *progressFile) Read(b []byte) (int, error) {
	n, err := p.File.Read(b)
	p.add(n)
	return n, err
}

func (p *progressFile) ReadAt(b []byte, off int64) (int, error) {
	n, err := p.File.ReadAt(b, off)
	p.add(n)
	return n, err
}

// progressReader counts the bytes read from r.
type progressReader struct {
	r     io.Reader
	done  int64
	total int64
	fn    ProgressFunc
}

// NewProgressReader (Hydra) calls fn with the number of bytes read from r so
// far, out of total.
func NewProgressReader(r io.Reader, total int64, fn ProgressFunc) io.Reader {
	if fn == nil {
		return r
	}
	return &progressReader{r: r, total: total, fn: fn}
}

func (p *progressReader) Read(b []byte) (int, error) {
	n, err := p.r.Read(b)
	if n > 0 {
		p.done += int64(n)
		p.fn(min(p.done, p.total), p.total)
	}
	return n, err
}

package router

import (
	"context"
	"io"

	"github.com/pterodactyl/wings/router/downloader"
	"github.com/pterodactyl/wings/server"
)

// cancelableReader stops reading once ctx is done. Hydra: used for file
// downloads and uploads, so they end right away when the user loses access to
// the server (e.g. the owner puts it into lockdown).
type cancelableReader struct {
	ctx context.Context
	r   io.Reader
}

func (c cancelableReader) Read(p []byte) (int, error) {
	if err := c.ctx.Err(); err != nil {
		return 0, err
	}
	return c.r.Read(p)
}

// cancelUserTransfers ends every running file download, upload and remote
// download the user started on the server.
func cancelUserTransfers(s *server.Server, user string) {
	s.FileTransfers().Cancel(user)
	for _, dl := range downloader.ByServer(s.ID()) {
		if dl.User() == user {
			dl.Cancel()
		}
	}
}

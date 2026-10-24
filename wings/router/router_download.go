package router

import (
	"bufio"
	"errors"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"

	"github.com/gin-gonic/gin"
	"github.com/google/uuid"

	"github.com/pterodactyl/wings/internal/ufs"
	"github.com/pterodactyl/wings/router/middleware"
	"github.com/pterodactyl/wings/router/tokens"
	"github.com/pterodactyl/wings/server/backup"
	"github.com/pterodactyl/wings/server/filesystem"
)

// Handle a download request for a server backup.
func getDownloadBackup(c *gin.Context) {
	client := middleware.ExtractApiClient(c)
	manager := middleware.ExtractManager(c)

	// Get the payload from the token.
	token := tokens.BackupPayload{}
	if err := tokens.ParseToken([]byte(c.Query("token")), &token); err != nil {
		middleware.CaptureAndAbort(c, err)
		return
	}

	// Get the server using the UUID from the token.
	s, ok := manager.Get(token.ServerUuid)
	if !ok || token.Denylisted() || !token.IsUniqueRequest() || !token.HasScope(tokens.BackupDownload) {
		c.AbortWithStatusJSON(http.StatusNotFound, gin.H{
			"error": "The requested resource was not found on this server.",
		})
		return
	}
	middleware.ClearDeadlines(c)

	// Validate that the BackupUuid field is actually a UUID and not some random characters or a
	// file path.
	if _, err := uuid.Parse(token.BackupUuid); err != nil {
		middleware.CaptureAndAbort(c, err)
		return
	}

	// Hydra: backups on an SFTP server are streamed from there.
	if token.HydraSftpDownload != "" {
		d, ok := takeSftpDownload(token.HydraSftpDownload, token.ServerUuid, token.BackupUuid)
		if !ok {
			c.AbortWithStatusJSON(http.StatusNotFound, gin.H{
				"error": "The requested backup was not found on this server.",
			})
			return
		}
		streamSftpBackup(c, s, token.UserUuid, d)
		return
	}

	// Locate the backup on the local disk.
	b, st, err := backup.LocateLocal(client, token.BackupUuid)
	if err != nil {
		if errors.Is(err, os.ErrNotExist) {
			c.AbortWithStatusJSON(http.StatusNotFound, gin.H{
				"error": "The requested backup was not found on this server.",
			})
			return
		}

		middleware.CaptureAndAbort(c, err)
		return
	}

	// The use of `os` here is safe as backups are not stored within server
	// accessible directories.
	f, err := os.Open(b.Path())
	if err != nil {
		middleware.CaptureAndAbort(c, err)
		return
	}
	defer f.Close()

	c.Header("Content-Length", strconv.Itoa(int(st.Size())))
	c.Header("Content-Disposition", "attachment; filename="+strconv.Quote(st.Name()))
	c.Header("Content-Type", "application/octet-stream")

	// Hydra: ends the download when the user loses access to the server. The
	// connection is closed then, since less than Content-Length was sent.
	_, _ = io.Copy(c.Writer, cancelableReader{ctx: s.FileTransfers().Context(token.UserUuid), r: bufio.NewReader(f)})
}

// Handles downloading a specific file for a server.
func getDownloadFile(c *gin.Context) {
	manager := middleware.ExtractManager(c)
	token := tokens.FilePayload{}
	if err := tokens.ParseToken([]byte(c.Query("token")), &token); err != nil {
		middleware.CaptureAndAbort(c, err)
		return
	}

	s, ok := manager.Get(token.ServerUuid)
	if !ok || token.Denylisted() || (!token.Preview && !token.IsUniqueRequest()) || !token.HasScope(tokens.FileDownload) {
		c.AbortWithStatusJSON(http.StatusNotFound, gin.H{
			"error": "The requested resource was not found on this server.",
		})
		return
	}
	middleware.ClearDeadlines(c)

	f, st, err := s.Filesystem().File(token.FilePath)
	if err != nil {
		middleware.CaptureAndAbort(c, err)
		return
	}
	defer f.Close()
	if st.IsDir() {
		c.AbortWithStatusJSON(http.StatusNotFound, gin.H{
			"error": "The requested resource was not found on this server.",
		})
		return
	}

	if token.Preview {
		servePreview(c, f, st)
		return
	}

	c.Header("Content-Length", strconv.Itoa(int(st.Size())))
	c.Header("Content-Disposition", "attachment; filename="+strconv.Quote(st.Name()))
	c.Header("Content-Type", "application/octet-stream")

	// Hydra: ends the download when the user loses access to the server, and
	// reports how much has been sent under the id the panel passed along.
	p := s.NewFileProgress(c.Query("progress"))
	var r io.Reader = cancelableReader{ctx: s.FileTransfers().Context(token.UserUuid), r: bufio.NewReader(f)}
	if p != nil {
		r = filesystem.NewProgressReader(r, st.Size(), p.Update)
	}
	n, _ := io.Copy(c.Writer, r)
	p.Finish(n < st.Size())
}

// previewTypes (Hydra) are the types the file manager shows inline. Anything
// else, HTML in particular, is sent as plain bytes, so a preview link can never
// run scripts on the address of the node.
var previewTypes = map[string]string{
	".png":  "image/png",
	".jpg":  "image/jpeg",
	".jpeg": "image/jpeg",
	".gif":  "image/gif",
	".webp": "image/webp",
	".avif": "image/avif",
	".bmp":  "image/bmp",
	".ico":  "image/x-icon",
	".svg":  "image/svg+xml",
	".mp4":  "video/mp4",
	".m4v":  "video/mp4",
	".webm": "video/webm",
	".ogv":  "video/ogg",
	".mov":  "video/quicktime",
	".mkv":  "video/x-matroska",
	".mp3":  "audio/mpeg",
	".ogg":  "audio/ogg",
	".oga":  "audio/ogg",
	".opus": "audio/opus",
	".wav":  "audio/wav",
	".flac": "audio/flac",
	".m4a":  "audio/mp4",
	".aac":  "audio/aac",
}

// servePreview (Hydra) sends a file for the preview of the file manager. It
// answers range requests, so videos and music can be skipped through.
func servePreview(c *gin.Context, f ufs.File, st filesystem.Stat) {
	contentType, ok := previewTypes[strings.ToLower(filepath.Ext(st.Name()))]
	if !ok {
		contentType = "application/octet-stream"
	}
	c.Header("Content-Type", contentType)
	c.Header("Content-Disposition", "inline; filename="+strconv.Quote(st.Name()))
	c.Header("X-Content-Type-Options", "nosniff")
	// SVG images could carry scripts when the link is opened directly.
	c.Header("Content-Security-Policy", "default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'; sandbox")
	c.Header("Cache-Control", "private, max-age=600")
	http.ServeContent(c.Writer, c.Request, "", st.ModTime(), f)
}

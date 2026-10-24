package router

import (
	"crypto/rand"
	"encoding/hex"
	"io"
	"net/http"
	"strconv"
	"sync"
	"time"

	"emperror.dev/errors"
	"github.com/gin-gonic/gin"

	"github.com/pterodactyl/wings/router/middleware"
	"github.com/pterodactyl/wings/server"
	"github.com/pterodactyl/wings/server/backup"
)

// Hydra: backups on SFTP servers. The panel sends the target with every
// request, so the node never stores credentials.

type sftpRequest struct {
	Sftp backup.SftpTarget `json:"sftp"`
	File string            `json:"file"`
}

func bindSftpRequest(c *gin.Context) (*sftpRequest, bool) {
	var data sftpRequest
	if err := c.BindJSON(&data); err != nil {
		return nil, false
	}
	if err := data.Sftp.Validate(); err != nil {
		c.AbortWithStatusJSON(http.StatusBadRequest, gin.H{"error": "The SFTP server settings are incomplete."})
		return nil, false
	}
	return &data, true
}

func abortSftp(c *gin.Context, err error) {
	c.AbortWithStatusJSON(http.StatusBadRequest, gin.H{"error": "SFTP: " + err.Error()})
}

// postSftpTest connects to a target, trusting any host key, and returns the
// fingerprint of the key and the backups found in the folder.
func postSftpTest(c *gin.Context) {
	data, ok := bindSftpRequest(c)
	if !ok {
		return
	}
	conn, err := data.Sftp.Connect(c.Request.Context(), true)
	if err != nil {
		abortSftp(c, err)
		return
	}
	defer conn.Close()
	if data.Sftp.HostKey != "" && data.Sftp.HostKey != conn.Fingerprint {
		c.JSON(http.StatusOK, gin.H{"fingerprint": conn.Fingerprint, "changed": true, "files": []backup.SftpFile{}})
		return
	}
	files, err := conn.List()
	if err != nil {
		abortSftp(c, errors.Wrap(err, "the backup folder could not be read"))
		return
	}
	c.JSON(http.StatusOK, gin.H{"fingerprint": conn.Fingerprint, "changed": false, "files": files})
}

// postSftpList returns the backups in the folder of a target.
func postSftpList(c *gin.Context) {
	data, ok := bindSftpRequest(c)
	if !ok {
		return
	}
	conn, err := data.Sftp.Connect(c.Request.Context(), false)
	if err != nil {
		abortSftp(c, err)
		return
	}
	defer conn.Close()
	files, err := conn.List()
	if err != nil {
		abortSftp(c, errors.Wrap(err, "the backup folder could not be read"))
		return
	}
	c.JSON(http.StatusOK, gin.H{"files": files})
}

// postSftpDelete deletes a backup from a target.
func postSftpDelete(c *gin.Context) {
	data, ok := bindSftpRequest(c)
	if !ok {
		return
	}
	if !backup.ValidSftpFileName(data.File) {
		c.AbortWithStatusJSON(http.StatusBadRequest, gin.H{"error": "Invalid backup file name."})
		return
	}
	conn, err := data.Sftp.Connect(c.Request.Context(), false)
	if err != nil {
		abortSftp(c, err)
		return
	}
	defer conn.Close()
	if err := conn.Delete(data.File); err != nil {
		abortSftp(c, err)
		return
	}
	c.Status(http.StatusNoContent)
}

// sftpDownload is a download of a backup on an SFTP server the panel
// announced; the browser then asks for it with a signed token naming the id.
type sftpDownload struct {
	server  string
	backup  string
	target  backup.SftpTarget
	file    string
	expires time.Time
}

var sftpDownloads = struct {
	sync.Mutex
	m map[string]sftpDownload
}{m: map[string]sftpDownload{}}

// postServerSftpDownload stores the target of a backup download for 15
// minutes and returns its id.
func postServerSftpDownload(c *gin.Context) {
	s := middleware.ExtractServer(c)
	backupUuid, ok := parseBackupUuid(c, c.Param("backup"))
	if !ok {
		return
	}
	data, ok := bindSftpRequest(c)
	if !ok {
		return
	}
	if !backup.ValidSftpFileName(data.File) {
		c.AbortWithStatusJSON(http.StatusBadRequest, gin.H{"error": "Invalid backup file name."})
		return
	}
	b := make([]byte, 24)
	if _, err := rand.Read(b); err != nil {
		middleware.CaptureAndAbort(c, err)
		return
	}
	id := hex.EncodeToString(b)

	sftpDownloads.Lock()
	now := time.Now()
	for k, v := range sftpDownloads.m {
		if now.After(v.expires) {
			delete(sftpDownloads.m, k)
		}
	}
	sftpDownloads.m[id] = sftpDownload{server: s.ID(), backup: backupUuid, target: data.Sftp, file: data.File, expires: now.Add(15 * time.Minute)}
	sftpDownloads.Unlock()

	c.JSON(http.StatusOK, gin.H{"id": id})
}

// takeSftpDownload returns the announced download once.
func takeSftpDownload(id, serverUuid, backupUuid string) (sftpDownload, bool) {
	sftpDownloads.Lock()
	defer sftpDownloads.Unlock()
	d, ok := sftpDownloads.m[id]
	delete(sftpDownloads.m, id)
	if !ok || time.Now().After(d.expires) || d.server != serverUuid || d.backup != backupUuid {
		return sftpDownload{}, false
	}
	return d, true
}

// streamSftpBackup sends a backup from an SFTP server to the browser.
func streamSftpBackup(c *gin.Context, s *server.Server, user string, d sftpDownload) {
	conn, err := d.target.Connect(s.Context(), false)
	if err != nil {
		abortSftp(c, err)
		return
	}
	defer conn.Close()
	f, st, err := conn.Open(d.file)
	if err != nil {
		c.AbortWithStatusJSON(http.StatusNotFound, gin.H{"error": "The requested backup was not found on the SFTP server."})
		return
	}
	defer f.Close()

	c.Header("Content-Length", strconv.FormatInt(st.Size(), 10))
	c.Header("Content-Disposition", "attachment; filename="+strconv.Quote(d.file))
	c.Header("Content-Type", "application/octet-stream")
	_, _ = io.Copy(c.Writer, cancelableReader{ctx: s.FileTransfers().Context(user), r: f})
}

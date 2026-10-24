package router

import (
	"net/http"
	"path"
	"strings"

	"emperror.dev/errors"
	"github.com/gin-gonic/gin"

	"github.com/pterodactyl/wings/router/middleware"
	"github.com/pterodactyl/wings/server"
	"github.com/pterodactyl/wings/server/backup"
)

// Hydra: import of the files of another server over SFTP (addon "importer").
// The panel sends the login with every request; the node keeps nothing.

type importRequest struct {
	Sftp   backup.SftpTarget `json:"sftp"`
	Source string            `json:"source"`
	Target string            `json:"target"`
}

func bindImportRequest(c *gin.Context) (*importRequest, bool) {
	var data importRequest
	if err := c.BindJSON(&data); err != nil {
		return nil, false
	}
	// Servers of users never reach the node's own network.
	data.Sftp.AllowInternal = false
	if err := data.Sftp.Validate(); err != nil {
		c.AbortWithStatusJSON(http.StatusBadRequest, gin.H{"error": "The SFTP login is incomplete."})
		return nil, false
	}
	if strings.ContainsRune(data.Source, 0) || strings.ContainsRune(data.Target, 0) {
		c.AbortWithStatusJSON(http.StatusBadRequest, gin.H{"error": "Invalid folder."})
		return nil, false
	}
	return &data, true
}

// postServerImportTest logs in to the other server, trusting any host key,
// and returns the fingerprint of the key and what is in the source folder.
func postServerImportTest(c *gin.Context) {
	data, ok := bindImportRequest(c)
	if !ok {
		return
	}
	conn, err := data.Sftp.Connect(c.Request.Context(), true)
	if err != nil {
		abortSftp(c, err)
		return
	}
	defer conn.Close()

	source := strings.TrimSpace(data.Source)
	if source == "" {
		source = "."
	}
	entries, err := conn.ReadDir(source)
	if err != nil {
		abortSftp(c, errors.New("the source folder could not be read"))
		return
	}
	names := make([]string, 0, 20)
	for _, e := range entries {
		if len(names) == 20 {
			break
		}
		name := e.Name()
		if e.IsDir() {
			name += "/"
		}
		names = append(names, name)
	}
	folder, _ := conn.RealPath(source)
	c.JSON(http.StatusOK, gin.H{
		"fingerprint": conn.Fingerprint,
		"folder":      path.Clean(folder),
		"entries":     len(entries),
		"names":       names,
	})
}

// postServerImport starts an import; its progress is sent over the websocket.
func postServerImport(c *gin.Context) {
	s := middleware.ExtractServer(c)
	data, ok := bindImportRequest(c)
	if !ok {
		return
	}
	if data.Sftp.HostKey == "" {
		c.AbortWithStatusJSON(http.StatusBadRequest, gin.H{"error": "Test the connection first."})
		return
	}
	if err := s.StartImport(data.Sftp, data.Source, data.Target); err != nil {
		if errors.Is(err, server.ErrImportRunning) {
			c.AbortWithStatusJSON(http.StatusConflict, gin.H{"error": "An import is running already."})
			return
		}
		abortSftp(c, err)
		return
	}
	c.Status(http.StatusAccepted)
}

// getServerImport returns the state of the last import.
func getServerImport(c *gin.Context) {
	c.JSON(http.StatusOK, middleware.ExtractServer(c).ImportStatus())
}

// deleteServerImport stops a running import.
func deleteServerImport(c *gin.Context) {
	middleware.ExtractServer(c).CancelImport()
	c.Status(http.StatusNoContent)
}

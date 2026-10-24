package router

import (
	"net/http"

	"emperror.dev/errors"
	"github.com/gin-gonic/gin"

	"github.com/pterodactyl/wings/environment"
	"github.com/pterodactyl/wings/router/middleware"
	"github.com/pterodactyl/wings/server/filesystem"
)

// postServerProfile (Hydra) keeps, restores and removes game profiles of a
// server (addon "games"). The server has to be offline, so no file is in use.
//
//   - save:   moves every file into a new profile, answers with its size
//   - load:   moves the files of a profile back into the empty directory
//   - wipe:   removes every file of the server, the profiles stay
//   - delete: removes a profile
func postServerProfile(c *gin.Context) {
	s := middleware.ExtractServer(c)
	var data struct {
		Action  string `json:"action"`
		Profile string `json:"profile"`
	}
	if err := c.BindJSON(&data); err != nil {
		return
	}

	if data.Action != "delete" && (s.Environment.State() != environment.ProcessOfflineState || s.IsInstalling() || s.IsTransferring() || s.IsRestoring()) {
		c.AbortWithStatusJSON(http.StatusConflict, gin.H{"error": "The server has to be stopped first.", "code": "server_running"})
		return
	}

	fs := s.Filesystem()
	var (
		size int64
		err  error
	)
	switch data.Action {
	case "save":
		size, err = fs.SaveProfile(data.Profile)
	case "load":
		err = fs.LoadProfile(data.Profile)
	case "wipe":
		err = fs.TruncateRootDirectory()
	case "delete":
		err = fs.DeleteProfile(data.Profile)
	default:
		c.AbortWithStatusJSON(http.StatusBadRequest, gin.H{"error": "Unknown action.", "code": "invalid_request"})
		return
	}

	if err != nil {
		code := ""
		switch {
		case errors.Is(err, filesystem.ErrProfileExists):
			code = "profile_exists"
		case errors.Is(err, filesystem.ErrProfileNotFound):
			code = "profile_not_found"
		case errors.Is(err, filesystem.ErrRootNotEmpty):
			code = "not_empty"
		}
		if code != "" {
			c.AbortWithStatusJSON(http.StatusConflict, gin.H{"error": err.Error(), "code": code})
			return
		}
		middleware.CaptureAndAbort(c, err)
		return
	}

	c.JSON(http.StatusOK, gin.H{"size": size})
}

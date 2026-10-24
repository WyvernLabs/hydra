package router

import (
	"net/http"

	"emperror.dev/errors"
	"github.com/gin-gonic/gin"

	"github.com/pterodactyl/wings/router/middleware"
	"github.com/pterodactyl/wings/server/filesystem"
)

// getServerTrash (Hydra) lists the trash of the file manager.
func getServerTrash(c *gin.Context) {
	s := middleware.ExtractServer(c)
	entries, err := s.Filesystem().Trash()
	if err != nil {
		middleware.CaptureAndAbort(c, err)
		return
	}
	c.JSON(http.StatusOK, gin.H{"entries": entries})
}

// postServerTrashRestore (Hydra) moves entries of the trash back to where they
// were deleted from and answers with the paths they were restored to.
func postServerTrashRestore(c *gin.Context) {
	s := middleware.ExtractServer(c)
	var data struct {
		IDs []string `json:"ids"`
	}
	if err := c.BindJSON(&data); err != nil {
		return
	}

	restored := make(map[string]string, len(data.IDs))
	for _, id := range data.IDs {
		p, err := s.Filesystem().RestoreTrash(id)
		if err != nil {
			if errors.Is(err, filesystem.ErrTrashNotFound) {
				c.AbortWithStatusJSON(http.StatusNotFound, gin.H{"error": err.Error(), "code": "trash_not_found", "restored": restored})
				return
			}
			middleware.CaptureAndAbort(c, err)
			return
		}
		restored[id] = p
	}
	c.JSON(http.StatusOK, gin.H{"restored": restored})
}

// postServerTrashDelete (Hydra) removes entries of the trash for good, or the
// whole trash with "all".
func postServerTrashDelete(c *gin.Context) {
	s := middleware.ExtractServer(c)
	var data struct {
		IDs []string `json:"ids"`
		All bool     `json:"all"`
	}
	if err := c.BindJSON(&data); err != nil {
		return
	}

	if data.All {
		if err := s.Filesystem().EmptyTrash(); err != nil {
			middleware.CaptureAndAbort(c, err)
			return
		}
		c.Status(http.StatusNoContent)
		return
	}
	for _, id := range data.IDs {
		if err := s.Filesystem().DeleteTrash(id); err != nil && !errors.Is(err, filesystem.ErrTrashNotFound) {
			middleware.CaptureAndAbort(c, err)
			return
		}
	}
	c.Status(http.StatusNoContent)
}

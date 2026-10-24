package router

import (
	"net/http"

	"github.com/gin-gonic/gin"

	"github.com/pterodactyl/wings/motd"
)

// putMotd (Hydra) replaces the MOTD configuration of the node (addon "motd").
func putMotd(c *gin.Context) {
	var data motd.Config
	if err := c.BindJSON(&data); err != nil {
		return
	}
	if err := motd.Set(&data); err != nil {
		c.AbortWithStatusJSON(http.StatusUnprocessableEntity, gin.H{"error": err.Error()})
		return
	}
	c.Status(http.StatusNoContent)
}

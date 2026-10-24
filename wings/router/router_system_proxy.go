package router

import (
	"net/http"
	"time"

	"github.com/gin-gonic/gin"

	"github.com/pterodactyl/wings/system"
)

// Hydra: reverse proxies of this node (addon "proxy"), managed by the Panel.

var nodeProxies = &system.Proxies{}

// getProxies tells whether Caddy is installed.
func getProxies(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{"installed": nodeProxies.Installed()})
}

// putProxies replaces every reverse proxy of the node.
func putProxies(c *gin.Context) {
	var data struct {
		Proxies []system.Proxy `json:"proxies"`
	}
	if err := c.BindJSON(&data); err != nil {
		return
	}

	ctx, cancel := firewallContext(c, time.Minute)
	defer cancel()
	if err := nodeProxies.Sync(ctx, data.Proxies); err != nil {
		firewallFailure(c, err)
		return
	}
	c.Status(http.StatusNoContent)
}

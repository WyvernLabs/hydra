package router

import (
	"errors"
	"net/http"
	"net/url"
	"strings"

	"github.com/gin-gonic/gin"

	"github.com/pterodactyl/wings/config"
	"github.com/pterodactyl/wings/router/middleware"
)

// parseRemote checks that the value is the plain base URL of a panel, such as
// https://panel.example.com, and returns it without a trailing slash.
func parseRemote(value string) (string, error) {
	u, err := url.Parse(strings.TrimSpace(value))
	if err != nil {
		return "", errors.New("the remote is not a valid URL")
	}
	if u.Scheme != "http" && u.Scheme != "https" {
		return "", errors.New("the remote must start with http:// or https://")
	}
	if u.Hostname() == "" || u.User != nil || u.RawQuery != "" || u.Fragment != "" || (u.Path != "" && u.Path != "/") {
		return "", errors.New("the remote must be the base URL of the panel")
	}
	return u.Scheme + "://" + u.Host, nil
}

// putRemote (Hydra) points the node at a new panel URL after the domain of the
// panel changed (Admin > Settings > Domain). The new URL is written to the
// configuration and used right away, without restarting Wings.
func putRemote(c *gin.Context) {
	var data struct {
		Remote string `json:"remote"`
	}
	if err := c.BindJSON(&data); err != nil {
		return
	}

	remote, err := parseRemote(data.Remote)
	if err != nil {
		c.AbortWithStatusJSON(http.StatusUnprocessableEntity, gin.H{"error": err.Error()})
		return
	}

	cfg := config.Get()
	if cfg.IgnorePanelConfigUpdates {
		c.AbortWithStatusJSON(http.StatusConflict, gin.H{"error": "ignore_panel_config_updates is set on this node, change the remote in its configuration file"})
		return
	}

	cfg.PanelLocation = remote
	if err := config.WriteToDisk(cfg); err != nil {
		middleware.CaptureAndAbort(c, err)
		return
	}
	config.Set(cfg)
	middleware.ExtractManager(c).Client().SetBaseURL(remote)

	c.JSON(http.StatusOK, gin.H{"remote": remote})
}

package router

import (
	"context"
	"errors"
	"net/http"
	"time"

	"github.com/gin-gonic/gin"

	"github.com/pterodactyl/wings/config"
	"github.com/pterodactyl/wings/system"
)

// Hydra: firewall (UFW) and database host of this node, managed from the
// admin area of the Panel.

var (
	nodeFirewall = &system.Firewall{}
	nodeDatabase = &system.Database{Firewall: nodeFirewall}
)

func firewall() *system.Firewall {
	cfg := config.Get()
	nodeFirewall.ApiPort = cfg.Api.Port
	nodeFirewall.SftpPort = cfg.System.Sftp.Port
	return nodeFirewall
}

// firewallFailure answers with the code of the error, which the Panel
// translates, and the output of the failed command.
func firewallFailure(c *gin.Context, err error) {
	var fe *system.FirewallError
	if errors.As(err, &fe) {
		status := http.StatusInternalServerError
		switch fe.Code {
		case "invalid_rule", "invalid_request":
			status = http.StatusUnprocessableEntity
		case "rule_not_found":
			status = http.StatusNotFound
		case "ufw_missing", "unsupported_os":
			status = http.StatusConflict
		}
		c.AbortWithStatusJSON(status, gin.H{"error": fe.Code, "code": fe.Code, "detail": fe.Detail})
		return
	}
	c.AbortWithStatusJSON(http.StatusInternalServerError, gin.H{"error": err.Error(), "code": "command_failed", "detail": err.Error()})
}

func firewallContext(c *gin.Context, timeout time.Duration) (context.Context, context.CancelFunc) {
	return context.WithTimeout(c.Request.Context(), timeout)
}

// getFirewall returns the state of UFW and its rules. client_ip is the address
// the Panel connects from, as a suggestion for the database access.
func getFirewall(c *gin.Context) {
	ctx, cancel := firewallContext(c, 20*time.Second)
	defer cancel()

	st, err := firewall().State(ctx)
	if err != nil {
		firewallFailure(c, err)
		return
	}
	c.JSON(http.StatusOK, gin.H{"firewall": st, "client_ip": c.ClientIP()})
}

func postFirewallInstall(c *gin.Context) {
	ctx, cancel := firewallContext(c, 5*time.Minute)
	defer cancel()

	if err := firewall().Install(ctx); err != nil {
		firewallFailure(c, err)
		return
	}
	c.Status(http.StatusNoContent)
}

func postFirewallEnable(c *gin.Context) {
	ctx, cancel := firewallContext(c, time.Minute)
	defer cancel()

	if err := firewall().Enable(ctx); err != nil {
		firewallFailure(c, err)
		return
	}
	c.Status(http.StatusNoContent)
}

func postFirewallDisable(c *gin.Context) {
	ctx, cancel := firewallContext(c, time.Minute)
	defer cancel()

	if err := firewall().Disable(ctx); err != nil {
		firewallFailure(c, err)
		return
	}
	c.Status(http.StatusNoContent)
}

func postFirewallRule(c *gin.Context) {
	var rule system.NewFirewallRule
	if err := c.BindJSON(&rule); err != nil {
		return
	}

	ctx, cancel := firewallContext(c, time.Minute)
	defer cancel()
	if err := firewall().AddRule(ctx, rule); err != nil {
		firewallFailure(c, err)
		return
	}
	c.Status(http.StatusNoContent)
}

func deleteFirewallRule(c *gin.Context) {
	var data struct {
		Rule string `json:"rule"`
	}
	if err := c.BindJSON(&data); err != nil {
		return
	}

	ctx, cancel := firewallContext(c, time.Minute)
	defer cancel()
	if err := firewall().DeleteRule(ctx, data.Rule); err != nil {
		firewallFailure(c, err)
		return
	}
	c.Status(http.StatusNoContent)
}

// putFirewallMysql opens the database port for the panel and the nodes. The
// Panel calls it whenever nodes are added, changed or removed.
func putFirewallMysql(c *gin.Context) {
	var data struct {
		Port    int      `json:"port"`
		Sources []string `json:"sources"`
	}
	if err := c.BindJSON(&data); err != nil {
		return
	}

	ctx, cancel := firewallContext(c, 2*time.Minute)
	defer cancel()
	sources := append(data.Sources, dockerSubnets()...)
	if err := firewall().SyncMysqlRules(ctx, data.Port, sources); err != nil {
		firewallFailure(c, err)
		return
	}
	c.Status(http.StatusNoContent)
}

// dockerSubnets are the networks of the servers on this node; their traffic to
// the node itself passes the firewall.
func dockerSubnets() []string {
	if v4 := config.Get().Docker.Network.Interfaces.V4.Subnet; v4 != "" {
		return []string{v4}
	}
	return nil
}

func getDatabase(c *gin.Context) {
	ctx, cancel := firewallContext(c, 20*time.Second)
	defer cancel()
	firewall()
	c.JSON(http.StatusOK, nodeDatabase.State(ctx))
}

func postDatabaseSetup(c *gin.Context) {
	var data system.DatabaseSetupRequest
	if err := c.BindJSON(&data); err != nil {
		return
	}

	firewall()
	if err := nodeDatabase.Start(data, dockerSubnets()); err != nil {
		if errors.Is(err, system.ErrDatabaseSetupRunning) {
			c.AbortWithStatusJSON(http.StatusConflict, gin.H{"error": "busy", "code": "busy"})
			return
		}
		firewallFailure(c, err)
		return
	}
	c.Status(http.StatusAccepted)
}

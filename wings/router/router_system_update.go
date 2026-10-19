package router

import (
	"context"
	"encoding/json"
	"errors"
	"io"
	"net/http"
	"os"
	"os/exec"
	"strings"
	"time"

	"github.com/gin-gonic/gin"

	"github.com/pterodactyl/wings/system"
)

// Hydra: updates of this node requested in the admin area of the Panel. The
// update itself is done by the hydra-node-update systemd unit, which the node
// installer sets up; Wings only starts it and reports how it went.
const (
	nodeUpdateUnit   = "hydra-node-update.service"
	nodeUpdateDir    = "/var/lib/hydra/updates"
	nodeUpdateLogMax = 64 * 1024
)

type nodeUpdateStatus struct {
	Version   string          `json:"version"`
	Available bool            `json:"available"`
	Running   bool            `json:"running"`
	Status    json.RawMessage `json:"status"`
	Log       string          `json:"log"`
}

// getNodeUpdate returns the version of Wings and the state of the last update.
func getNodeUpdate(c *gin.Context) {
	out := nodeUpdateStatus{
		Version:   system.Version,
		Available: nodeUpdateAvailable(),
		Running:   nodeUpdateRunning(c.Request.Context()),
		Status:    json.RawMessage("null"),
	}

	if b, err := os.ReadFile(nodeUpdateDir + "/status.json"); err == nil && json.Valid(b) {
		out.Status = b
	}
	out.Log = tailFile(nodeUpdateDir+"/node.log", nodeUpdateLogMax)

	c.JSON(http.StatusOK, out)
}

// postNodeUpdate starts the update of this node in the background.
func postNodeUpdate(c *gin.Context) {
	if !nodeUpdateAvailable() {
		c.AbortWithStatusJSON(http.StatusNotImplemented, gin.H{
			"error": "Updates from the Panel are not set up on this node yet. Run \"hydra-node update\" on the node once.",
		})
		return
	}
	if nodeUpdateRunning(c.Request.Context()) {
		c.AbortWithStatusJSON(http.StatusConflict, gin.H{"error": "An update of this node is already running."})
		return
	}

	ctx, cancel := context.WithTimeout(c.Request.Context(), 15*time.Second)
	defer cancel()
	if out, err := exec.CommandContext(ctx, "systemctl", "start", "--no-block", nodeUpdateUnit).CombinedOutput(); err != nil {
		c.AbortWithStatusJSON(http.StatusInternalServerError, gin.H{
			"error": "Could not start the update: " + strings.TrimSpace(string(out)),
		})
		return
	}

	c.Status(http.StatusAccepted)
}

func nodeUpdateAvailable() bool {
	_, err := os.Stat("/etc/systemd/system/" + nodeUpdateUnit)
	return err == nil
}

func nodeUpdateRunning(ctx context.Context) bool {
	ctx, cancel := context.WithTimeout(ctx, 5*time.Second)
	defer cancel()
	out, err := exec.CommandContext(ctx, "systemctl", "is-active", nodeUpdateUnit).Output()
	if err != nil && !errors.As(err, new(*exec.ExitError)) {
		return false
	}
	state := strings.TrimSpace(string(out))
	return state == "active" || state == "activating"
}

// tailFile returns at most the last max bytes of a file.
func tailFile(path string, max int64) string {
	f, err := os.Open(path)
	if err != nil {
		return ""
	}
	defer f.Close()

	st, err := f.Stat()
	if err != nil {
		return ""
	}
	if st.Size() > max {
		if _, err := f.Seek(-max, io.SeekEnd); err != nil {
			return ""
		}
	}
	b, err := io.ReadAll(io.LimitReader(f, max))
	if err != nil {
		return ""
	}
	return string(b)
}

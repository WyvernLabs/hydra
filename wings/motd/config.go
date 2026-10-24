package motd

import (
	"bytes"
	"encoding/base64"
	"encoding/json"
	"os"
	"path/filepath"
	"slices"
	"sync"

	"emperror.dev/errors"
)

// Hydra: custom MOTD of Minecraft Java servers that are not running (addon
// "motd" of the Panel). While a server is offline, starting, stopping or
// suspended, Wings answers the server list ping of players on its port with
// the texts and picture set in the Panel. The Panel sends the configuration
// of the whole node, which is kept on disk for restarts of Wings.

// File keeps the configuration sent by the Panel.
var File = "/etc/hydra/motd.json"

// States the Panel can set texts for.
var States = []string{"offline", "starting", "stopping", "suspended"}

const maxText = 512

// Texts shown for one state.
type Texts struct {
	// Line1 and Line2 of the MOTD, with & or § color codes and {name}.
	Line1 string `json:"line1"`
	Line2 string `json:"line2"`
	// Label replaces the player count, e.g. "&cOffline".
	Label string `json:"label"`
}

// Config of the node.
type Config struct {
	Enabled bool `json:"enabled"`
	// Favicon is a 64×64 PNG, base64 encoded, or empty.
	Favicon string           `json:"favicon"`
	States  map[string]Texts `json:"states"`
	// Servers (UUIDs) that get the MOTD, the Minecraft servers of the node.
	Servers []string `json:"servers"`
	// Names of the servers as the Panel knows them, for {name}.
	Names map[string]string `json:"names"`
}

var pngHeader = []byte("\x89PNG\r\n\x1a\n")

// Validate checks the texts and the picture.
func (c *Config) Validate() error {
	if c.Favicon != "" {
		b, err := base64.StdEncoding.DecodeString(c.Favicon)
		if err != nil || len(b) > 64*1024 || !bytes.HasPrefix(b, pngHeader) {
			return errors.New("motd: the favicon must be a PNG of at most 64 KiB")
		}
	}
	for state, texts := range c.States {
		if !slices.Contains(States, state) {
			return errors.New("motd: unknown state " + state)
		}
		if len(texts.Line1) > maxText || len(texts.Line2) > maxText || len(texts.Label) > maxText {
			return errors.New("motd: text too long")
		}
	}
	for _, name := range c.Names {
		if len(name) > 191 {
			return errors.New("motd: server name too long")
		}
	}
	if len(c.Servers) > 10000 {
		return errors.New("motd: too many servers")
	}
	return nil
}

// Uses reports whether the server gets the MOTD.
func (c *Config) Uses(uuid string) bool {
	return c != nil && c.Enabled && slices.Contains(c.Servers, uuid)
}

var (
	mu      sync.RWMutex
	current *Config
)

// Current returns the configuration, nil when the Panel never sent one.
func Current() *Config {
	mu.RLock()
	defer mu.RUnlock()
	return current
}

// Set replaces the configuration and keeps it on disk.
func Set(c *Config) error {
	if err := c.Validate(); err != nil {
		return err
	}
	b, err := json.Marshal(c)
	if err != nil {
		return err
	}
	if err := os.MkdirAll(filepath.Dir(File), 0o755); err != nil {
		return err
	}
	if err := os.WriteFile(File+".tmp", b, 0o600); err != nil {
		return err
	}
	if err := os.Rename(File+".tmp", File); err != nil {
		return err
	}

	mu.Lock()
	current = c
	mu.Unlock()
	return nil
}

// Load reads the configuration kept on disk, if any.
func Load() error {
	b, err := os.ReadFile(File)
	if err != nil {
		if os.IsNotExist(err) {
			return nil
		}
		return err
	}
	var c Config
	if err := json.Unmarshal(b, &c); err != nil {
		return err
	}
	if err := c.Validate(); err != nil {
		return err
	}
	mu.Lock()
	current = &c
	mu.Unlock()
	return nil
}

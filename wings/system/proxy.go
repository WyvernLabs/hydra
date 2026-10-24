package system

import (
	"bytes"
	"context"
	"fmt"
	"net"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"sync"
)

// Hydra: reverse proxies of the node (addon "proxy"). Caddy, set up by the
// node installer, serves every domain with a certificate of Let's Encrypt and
// passes the requests on to a port of a server. The Panel always sends the
// full list of the node; it is written to one file Caddy imports.

const (
	// ProxyDir holds the files of Hydra the Caddyfile of the node imports.
	ProxyDir = "/etc/hydra/caddy"
	// CaddyMain is the main configuration of Caddy.
	CaddyMain = "/etc/caddy/Caddyfile"
)

var proxyDomain = regexp.MustCompile(`^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]$`)

// Proxy passes the requests for a domain on to an address of the node.
type Proxy struct {
	Domain   string `json:"domain"`
	Upstream string `json:"upstream"`
}

// Validate checks the domain and that the upstream is an IP address and port.
func (p Proxy) Validate() error {
	if len(p.Domain) > 253 || !proxyDomain.MatchString(p.Domain) {
		return firewallError("invalid_request", "invalid domain "+p.Domain)
	}
	host, port, err := net.SplitHostPort(p.Upstream)
	if err != nil || net.ParseIP(host) == nil {
		return firewallError("invalid_request", "invalid upstream "+p.Upstream)
	}
	if n, err := strconv.Atoi(port); err != nil || n < 1 || n > 65535 {
		return firewallError("invalid_request", "invalid upstream "+p.Upstream)
	}
	return nil
}

// Proxies writes the reverse proxies of the node and reloads Caddy.
type Proxies struct {
	mu sync.Mutex
	// Overridable for tests.
	Dir    string
	Reload func(ctx context.Context) error
}

// Installed reports whether Caddy is installed on the node.
func (p *Proxies) Installed() bool {
	_, err := exec.LookPath("caddy")
	return err == nil
}

// Render returns the Caddyfile for the proxies, sorted by domain.
func Render(list []Proxy) []byte {
	sorted := append([]Proxy(nil), list...)
	sort.Slice(sorted, func(i, j int) bool { return sorted[i].Domain < sorted[j].Domain })

	var b bytes.Buffer
	b.WriteString("# Managed by Hydra (reverse proxies of this node). Changes are overwritten.\n")
	for _, proxy := range sorted {
		fmt.Fprintf(&b, "\n%s {\n\treverse_proxy %s\n}\n", proxy.Domain, proxy.Upstream)
	}
	return b.Bytes()
}

// Sync replaces the proxies of the node. When Caddy refuses the new
// configuration, the previous one is restored.
func (p *Proxies) Sync(ctx context.Context, list []Proxy) error {
	seen := map[string]bool{}
	for i, proxy := range list {
		proxy.Domain = strings.ToLower(strings.TrimSuffix(proxy.Domain, "."))
		list[i] = proxy
		if err := proxy.Validate(); err != nil {
			return err
		}
		if seen[proxy.Domain] {
			return firewallError("invalid_request", "duplicate domain "+proxy.Domain)
		}
		seen[proxy.Domain] = true
	}
	if !p.Installed() && p.Reload == nil {
		return firewallError("caddy_missing", "")
	}

	p.mu.Lock()
	defer p.mu.Unlock()

	dir := p.Dir
	if dir == "" {
		dir = ProxyDir
	}
	if err := os.MkdirAll(dir, 0o755); err != nil {
		return err
	}
	file := filepath.Join(dir, "proxies.caddy")
	previous, err := os.ReadFile(file)
	if err != nil && !os.IsNotExist(err) {
		return err
	}
	if err := os.WriteFile(file, Render(list), 0o644); err != nil {
		return err
	}

	reload := p.Reload
	if reload == nil {
		reload = reloadCaddy
	}
	if err := reload(ctx); err != nil {
		if previous != nil {
			_ = os.WriteFile(file, previous, 0o644)
		} else {
			_ = os.Remove(file)
		}
		_ = reload(ctx)
		return err
	}
	return nil
}

// reloadCaddy checks the configuration and makes Caddy load it.
func reloadCaddy(ctx context.Context) error {
	if out, err := exec.CommandContext(ctx, "caddy", "validate", "--config", CaddyMain, "--adapter", "caddyfile").CombinedOutput(); err != nil {
		return firewallError("caddy_invalid", string(out))
	}
	if out, err := exec.CommandContext(ctx, "systemctl", "reload-or-restart", "caddy").CombinedOutput(); err != nil {
		return firewallError("caddy_reload_failed", string(out))
	}
	return nil
}

package system

import (
	"context"
	"errors"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestProxiesSync(t *testing.T) {
	dir := t.TempDir()
	fail := false
	p := &Proxies{Dir: dir, Reload: func(context.Context) error {
		if fail {
			return errors.New("caddy refused")
		}
		return nil
	}}
	file := filepath.Join(dir, "proxies.caddy")

	err := p.Sync(context.Background(), []Proxy{
		{Domain: "Map.Example.com.", Upstream: "127.0.0.1:8100"},
		{Domain: "a.example.com", Upstream: "203.0.113.5:25565"},
	})
	if err != nil {
		t.Fatal(err)
	}
	b, _ := os.ReadFile(file)
	content := string(b)
	if !strings.Contains(content, "map.example.com {\n\treverse_proxy 127.0.0.1:8100\n}") {
		t.Fatalf("unexpected configuration:\n%s", content)
	}
	if strings.Index(content, "a.example.com") > strings.Index(content, "map.example.com") {
		t.Fatal("domains are not sorted")
	}

	// Invalid entries are refused before anything is written.
	for _, bad := range []Proxy{
		{Domain: "evil.com {\n}\n:80", Upstream: "127.0.0.1:80"},
		{Domain: "ok.example.com", Upstream: "localhost:80"},
		{Domain: "ok.example.com", Upstream: "127.0.0.1:99999"},
	} {
		if err := p.Sync(context.Background(), []Proxy{bad}); err == nil {
			t.Fatalf("accepted invalid proxy %+v", bad)
		}
	}

	// When Caddy refuses the configuration, the previous one stays.
	fail = true
	if err := p.Sync(context.Background(), []Proxy{{Domain: "new.example.com", Upstream: "127.0.0.1:1"}}); err == nil {
		t.Fatal("expected the reload to fail")
	}
	after, _ := os.ReadFile(file)
	if string(after) != content {
		t.Fatal("the previous configuration was not restored")
	}

	// An empty list leaves no site.
	fail = false
	if err := p.Sync(context.Background(), nil); err != nil {
		t.Fatal(err)
	}
	empty, _ := os.ReadFile(file)
	if strings.Contains(string(empty), "{") {
		t.Fatalf("expected no sites, got:\n%s", empty)
	}
}

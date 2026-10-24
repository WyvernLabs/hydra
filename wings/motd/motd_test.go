package motd

import (
	"bufio"
	"encoding/json"
	"net"
	"path/filepath"
	"strings"
	"testing"
	"time"
)

func handshake(next int32) []byte {
	data := appendVarInt(nil, 769)
	data = appendString(data, "play.example.com")
	data = append(data, 0x63, 0xdd)
	data = appendVarInt(data, next)
	payload := append(appendVarInt(nil, 0x00), data...)
	return append(appendVarInt(nil, int32(len(payload))), payload...)
}

func readJSON(t *testing.T, r *bufio.Reader) map[string]any {
	t.Helper()
	id, body, err := readPacket(r)
	if err != nil || id != 0x00 {
		t.Fatalf("packet %d, %v", id, err)
	}
	length, err := readVarInt(body)
	if err != nil {
		t.Fatal(err)
	}
	text := make([]byte, length)
	if _, err := body.Read(text); err != nil {
		t.Fatal(err)
	}
	var out map[string]any
	if err := json.Unmarshal(text, &out); err != nil {
		t.Fatalf("%s: %v", text, err)
	}
	return out
}

func TestStatusAndPing(t *testing.T) {
	client, server := net.Pipe()
	defer client.Close()
	go func() {
		_ = Serve(server, Answer{Lines: "&aHello\n&7World", Label: "&cOffline", Favicon: "iVBORw0KGgo="})
		_ = server.Close()
	}()
	_ = client.SetDeadline(time.Now().Add(5 * time.Second))

	if _, err := client.Write(append(handshake(1), 0x01, 0x00)); err != nil {
		t.Fatal(err)
	}
	r := bufio.NewReader(client)
	status := readJSON(t, r)

	version := status["version"].(map[string]any)
	if version["name"] != "§cOffline" || version["protocol"].(float64) != -1 {
		t.Fatalf("unexpected version %v", version)
	}
	if status["favicon"] != "data:image/png;base64,iVBORw0KGgo=" {
		t.Fatalf("unexpected favicon %v", status["favicon"])
	}
	extra := status["description"].(map[string]any)["extra"].([]any)
	first := extra[0].(map[string]any)
	if first["text"] != "Hello\n" || first["color"] != "green" {
		t.Fatalf("unexpected description %v", extra)
	}

	// Ping is echoed.
	if _, err := client.Write([]byte{0x09, 0x01, 1, 2, 3, 4, 5, 6, 7, 8}); err != nil {
		t.Fatal(err)
	}
	id, body, err := readPacket(r)
	if err != nil || id != 0x01 || body.Len() != 8 {
		t.Fatalf("unexpected pong %d %v", id, err)
	}
}

func TestLoginIsDisconnected(t *testing.T) {
	client, server := net.Pipe()
	defer client.Close()
	go func() {
		_ = Serve(server, Answer{Lines: "&cServer offline\nStart it in the panel"})
		_ = server.Close()
	}()
	_ = client.SetDeadline(time.Now().Add(5 * time.Second))

	if _, err := client.Write(handshake(2)); err != nil {
		t.Fatal(err)
	}
	reason := readJSON(t, bufio.NewReader(client))
	text, _ := json.Marshal(reason)
	if !strings.Contains(string(text), "Server offline") {
		t.Fatalf("unexpected reason %s", text)
	}
}

func TestComponent(t *testing.T) {
	c := Component("&#FF8800&lHydra &rplain §bblue &zx")
	extra := c["extra"].([]map[string]any)
	if extra[0]["color"] != "#FF8800" || extra[0]["bold"] != true || extra[0]["text"] != "Hydra " {
		t.Fatalf("unexpected first part %v", extra[0])
	}
	if extra[1]["text"] != "plain " || extra[1]["bold"] != false {
		t.Fatalf("unexpected second part %v", extra[1])
	}
	if extra[2]["color"] != "aqua" || extra[2]["text"] != "blue &zx" {
		t.Fatalf("unexpected third part %v", extra[2])
	}
	if got := Legacy("&#123456&aGo &lnow"); got != "§aGo §lnow" {
		t.Fatalf("unexpected legacy %q", got)
	}
}

func TestRules(t *testing.T) {
	rules := Rules([]Target{{IP: "203.0.113.5", Port: 25565}, {IP: "0.0.0.0", Port: 25566}, {IP: "2001:db8::1", Port: 25567}})
	if len(rules) != 2 {
		t.Fatalf("expected 2 rules, got %v", rules)
	}
	if strings.Join(rules[0], " ") != "-A HYDRA_MOTD -p tcp -d 203.0.113.5 --dport 25565 -j REDIRECT --to-ports 47823" {
		t.Fatalf("unexpected rule %v", rules[0])
	}
	if !strings.Contains(strings.Join(rules[1], " "), "--dst-type LOCAL --dport 25566") {
		t.Fatalf("unexpected rule %v", rules[1])
	}
}

type recorder struct{ applied [][]Target }

func (r *recorder) Apply(targets []Target) error {
	r.applied = append(r.applied, targets)
	return nil
}

func TestDesiredTargets(t *testing.T) {
	File = filepath.Join(t.TempDir(), "motd.json")
	if err := Set(&Config{Enabled: true, Servers: []string{"a", "b", "c"}, States: map[string]Texts{"offline": {Line1: "x"}}}); err != nil {
		t.Fatal(err)
	}
	if err := Set(&Config{Favicon: "bm90IGEgcG5n"}); err == nil {
		t.Fatal("a picture that is no PNG was accepted")
	}

	servers := []Server{
		{UUID: "a", Port: 25565, State: "offline"},
		{UUID: "b", Port: 25566, State: "running"},
		{UUID: "c", Port: 25567, State: "starting"},
		{UUID: "d", Port: 25568, State: "offline"},
	}
	s := &Service{Servers: func() []Server { return servers }}
	got := s.desired()
	if len(got) != 2 || got[0].Port != 25565 || got[1].Port != 25567 {
		t.Fatalf("unexpected targets %v", got)
	}

	// A server stuck in starting is no longer redirected.
	s.since["c"] = stateSince{state: "starting", at: time.Now().Add(-MaxTransition - time.Second)}
	if got := s.desired(); len(got) != 1 {
		t.Fatalf("unexpected targets %v", got)
	}
}

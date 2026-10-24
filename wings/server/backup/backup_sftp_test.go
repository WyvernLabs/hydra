package backup

import (
	"context"
	"crypto/ed25519"
	"crypto/rand"
	"errors"
	"io"
	"net"
	"os"
	"path/filepath"
	"testing"

	"github.com/pkg/sftp"
	"golang.org/x/crypto/ssh"

	"github.com/pterodactyl/wings/config"
)

// startSftpServer runs an SFTP server on loopback that accepts the password
// "secret" and returns its port and host key fingerprint.
func startSftpServer(t *testing.T) (int, string) {
	t.Helper()
	_, key, err := ed25519.GenerateKey(rand.Reader)
	if err != nil {
		t.Fatal(err)
	}
	signer, err := ssh.NewSignerFromKey(key)
	if err != nil {
		t.Fatal(err)
	}
	cfg := &ssh.ServerConfig{
		PasswordCallback: func(_ ssh.ConnMetadata, password []byte) (*ssh.Permissions, error) {
			if string(password) != "secret" {
				return nil, errors.New("wrong password")
			}
			return nil, nil
		},
	}
	cfg.AddHostKey(signer)

	l, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = l.Close() })

	go func() {
		for {
			conn, err := l.Accept()
			if err != nil {
				return
			}
			go func(conn net.Conn) {
				_, chans, reqs, err := ssh.NewServerConn(conn, cfg)
				if err != nil {
					return
				}
				go ssh.DiscardRequests(reqs)
				for ch := range chans {
					channel, requests, err := ch.Accept()
					if err != nil {
						continue
					}
					go func() {
						for req := range requests {
							_ = req.Reply(req.Type == "subsystem" && string(req.Payload[4:]) == "sftp", nil)
							if req.Type == "subsystem" {
								server, err := sftp.NewServer(channel)
								if err != nil {
									return
								}
								_ = server.Serve()
								_ = server.Close()
								return
							}
						}
					}()
				}
			}(conn)
		}
	}()

	return l.Addr().(*net.TCPAddr).Port, ssh.FingerprintSHA256(signer.PublicKey())
}

func TestSftpTarget(t *testing.T) {
	config.Set(&config.Configuration{AuthenticationToken: "test-token"})
	port, fingerprint := startSftpServer(t)
	dir := t.TempDir()
	target := SftpTarget{
		Host:          "127.0.0.1",
		Port:          port,
		Username:      "hydra",
		Password:      "secret",
		Path:          filepath.Join(dir, "backups"),
		AllowInternal: true,
	}
	ctx := context.Background()

	// A user's target may not point into a private network.
	internal := target
	internal.AllowInternal = false
	if _, err := internal.Connect(ctx, true); !errors.Is(err, ErrSftpAddress) {
		t.Fatalf("expected the private address to be refused, got %v", err)
	}

	// Without a stored host key only a test connection is possible.
	if _, err := target.Connect(ctx, false); !errors.Is(err, ErrSftpHostKey) {
		t.Fatalf("expected a missing host key to be refused, got %v", err)
	}
	conn, err := target.Connect(ctx, true)
	if err != nil {
		t.Fatal(err)
	}
	if conn.Fingerprint != fingerprint {
		t.Fatalf("fingerprint %q does not match %q", conn.Fingerprint, fingerprint)
	}
	_ = conn.Close()

	wrong := target
	wrong.HostKey = "SHA256:somethingelse"
	if _, err := wrong.Connect(ctx, false); !errors.Is(err, ErrSftpHostKey) {
		t.Fatalf("expected a changed host key to be refused, got %v", err)
	}

	target.HostKey = fingerprint
	conn, err = target.Connect(ctx, false)
	if err != nil {
		t.Fatal(err)
	}
	defer conn.Close()

	local := filepath.Join(dir, "local.tar.gz")
	content := []byte("not really an archive, but enough for the transfer")
	if err := os.WriteFile(local, content, 0o600); err != nil {
		t.Fatal(err)
	}
	var done, total int64
	if err := conn.Upload(ctx, local, "a.tar.gz", func(d, n int64) { done, total = d, n }); err != nil {
		t.Fatal(err)
	}
	if done != int64(len(content)) || total != int64(len(content)) {
		t.Fatalf("upload progress ended at %d of %d", done, total)
	}
	if _, _, err := conn.Open("../local.tar.gz"); err == nil {
		t.Fatal("a file outside of the backup folder could be opened")
	}

	files, err := conn.List()
	if err != nil {
		t.Fatal(err)
	}
	if len(files) != 1 || files[0].Name != "a.tar.gz" || files[0].Size != int64(len(content)) {
		t.Fatalf("unexpected files on the server: %+v", files)
	}

	f, _, err := conn.Open("a.tar.gz")
	if err != nil {
		t.Fatal(err)
	}
	b, err := io.ReadAll(f)
	_ = f.Close()
	if err != nil {
		t.Fatal(err)
	}
	if string(b) != string(content) {
		t.Fatal("downloaded file does not match the uploaded one")
	}

	if err := conn.Delete("a.tar.gz"); err != nil {
		t.Fatal(err)
	}
	if err := conn.Delete("a.tar.gz"); err != nil {
		t.Fatalf("deleting a missing file failed: %v", err)
	}
	if _, err := os.Stat(filepath.Join(dir, "backups", "a.tar.gz")); !os.IsNotExist(err) {
		t.Fatal("the backup was not deleted")
	}
}

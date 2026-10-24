package server

import (
	"crypto/ed25519"
	"crypto/rand"
	"errors"
	"net"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/pkg/sftp"
	"github.com/stretchr/testify/require"
	"golang.org/x/crypto/ssh"

	"github.com/pterodactyl/wings/server/backup"
)

// startImportSftpServer runs an SFTP server on loopback that serves the local
// filesystem to the password "secret" and returns its port and host key.
func startImportSftpServer(t *testing.T) (int, string) {
	t.Helper()
	_, key, err := ed25519.GenerateKey(rand.Reader)
	require.NoError(t, err)
	signer, err := ssh.NewSignerFromKey(key)
	require.NoError(t, err)
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
	require.NoError(t, err)
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
								srv, err := sftp.NewServer(channel)
								if err != nil {
									return
								}
								_ = srv.Serve()
								_ = srv.Close()
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

func waitForImport(t *testing.T, s *Server) ImportStatus {
	t.Helper()
	deadline := time.Now().Add(15 * time.Second)
	for time.Now().Before(deadline) {
		st := s.ImportStatus()
		if st.State == "done" || st.State == "failed" {
			return st
		}
		time.Sleep(20 * time.Millisecond)
	}
	t.Fatal("the import did not end")
	return ImportStatus{}
}

func TestImportCopiesFilesAndFolders(t *testing.T) {
	s, _, root := newRestoreTestServer(t)
	port, fingerprint := startImportSftpServer(t)

	remote := t.TempDir()
	require.NoError(t, os.WriteFile(filepath.Join(remote, "server.properties"), []byte("motd=hello"), 0o644))
	require.NoError(t, os.MkdirAll(filepath.Join(remote, "world", "region"), 0o755))
	require.NoError(t, os.WriteFile(filepath.Join(remote, "world", "region", "r.0.0.mca"), make([]byte, 70000), 0o600))
	require.NoError(t, os.Mkdir(filepath.Join(remote, "empty"), 0o755))
	// Links are not followed.
	require.NoError(t, os.Symlink("/etc/passwd", filepath.Join(remote, "passwd")))

	target := backup.SftpTarget{Host: "127.0.0.1", Port: port, Username: "hydra", Password: "secret", HostKey: fingerprint, AllowInternal: true}
	require.NoError(t, s.StartImport(target, remote, "/imported/"))

	st := waitForImport(t, s)
	require.Equal(t, "done", st.State, st.Error)
	require.Equal(t, 2, st.FilesTotal)
	require.Equal(t, 2, st.Files)
	require.Equal(t, int64(70010), st.Total)
	require.Equal(t, st.Total, st.Done)

	content, err := os.ReadFile(filepath.Join(root, "imported", "server.properties"))
	require.NoError(t, err)
	require.Equal(t, "motd=hello", string(content))
	info, err := os.Stat(filepath.Join(root, "imported", "world", "region", "r.0.0.mca"))
	require.NoError(t, err)
	require.Equal(t, int64(70000), info.Size())
	require.DirExists(t, filepath.Join(root, "imported", "empty"))
	_, err = os.Lstat(filepath.Join(root, "imported", "passwd"))
	require.True(t, os.IsNotExist(err))
}

func TestImportRefusesAChangedHostKeyAndAMissingFolder(t *testing.T) {
	s, _, _ := newRestoreTestServer(t)
	port, fingerprint := startImportSftpServer(t)

	target := backup.SftpTarget{Host: "127.0.0.1", Port: port, Username: "hydra", Password: "secret", HostKey: "SHA256:other", AllowInternal: true}
	require.ErrorIs(t, s.StartImport(target, "/", ""), backup.ErrSftpHostKey)
	require.Equal(t, "failed", s.ImportStatus().State)

	target.HostKey = fingerprint
	require.NoError(t, s.StartImport(target, filepath.Join(t.TempDir(), "missing"), ""))
	st := waitForImport(t, s)
	require.Equal(t, "failed", st.State)
	require.Contains(t, st.Error, "source folder could not be read")
}

package filesystem

import (
	"archive/zip"
	"context"
	"errors"
	"io"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// TestCompressFilesWritesZip checks that archives are zip files holding the
// selected files, and that the progress ends at the size of all files.
func TestCompressFilesWritesZip(t *testing.T) {
	fs, rfs := NewFs()
	t.Cleanup(func() { _ = os.RemoveAll(rfs.root) })

	if err := os.MkdirAll(filepath.Join(rfs.root, "server", "world", "region"), 0o755); err != nil {
		t.Fatal(err)
	}
	data := randomData(t, 200<<10)
	if err := rfs.CreateServerFile("world/region/r.0.0.mca", data); err != nil {
		t.Fatal(err)
	}
	if err := rfs.CreateServerFileFromString("server.properties", "motd=Hydra\n"); err != nil {
		t.Fatal(err)
	}
	if err := rfs.CreateServerFileFromString("other.txt", "not selected"); err != nil {
		t.Fatal(err)
	}

	var done, total int64
	st, err := fs.CompressFiles("/", []string{"world", "server.properties"}, func(d, n int64) {
		done, total = d, n
	})
	if err != nil {
		t.Fatal(err)
	}
	if !strings.HasSuffix(st.Name(), ".zip") {
		t.Fatalf("archive is named %q, expected a .zip", st.Name())
	}
	want := int64(len(data) + len("motd=Hydra\n"))
	if total != want || done != want {
		t.Fatalf("progress ended at %d of %d, expected %d of %d", done, total, want, want)
	}

	zr, err := zip.OpenReader(filepath.Join(rfs.root, "server", st.Name()))
	if err != nil {
		t.Fatal(err)
	}
	defer zr.Close()

	files := map[string][]byte{}
	for _, f := range zr.File {
		r, err := f.Open()
		if err != nil {
			t.Fatal(err)
		}
		b, err := io.ReadAll(r)
		_ = r.Close()
		if err != nil {
			t.Fatal(err)
		}
		files[f.Name] = b
	}
	if len(files) != 2 {
		t.Fatalf("archive holds %d files, expected 2", len(files))
	}
	if string(files["server.properties"]) != "motd=Hydra\n" {
		t.Fatalf("server.properties holds %q", files["server.properties"])
	}
	if string(files["world/region/r.0.0.mca"]) != string(data) {
		t.Fatal("world/region/r.0.0.mca does not match the original file")
	}

	// The archive can be extracted again, reporting its progress.
	if err := os.RemoveAll(filepath.Join(rfs.root, "server", "world")); err != nil {
		t.Fatal(err)
	}
	var extracted, size int64
	err = fs.DecompressFileWithProgress(context.Background(), "/", st.Name(), false, func(d, n int64) {
		extracted, size = d, n
	})
	if err != nil {
		t.Fatal(err)
	}
	if size != st.Size() || extracted == 0 {
		t.Fatalf("extraction progress ended at %d of %d, archive is %d bytes", extracted, size, st.Size())
	}
	b, err := os.ReadFile(filepath.Join(rfs.root, "server", "world", "region", "r.0.0.mca"))
	if err != nil {
		t.Fatal(err)
	}
	if string(b) != string(data) {
		t.Fatal("extracted file does not match the original file")
	}
}

// TestArchiveMaxSize checks that a backup archive stops at its size limit
// (Hydra: the space left for backups).
func TestArchiveMaxSize(t *testing.T) {
	fs, rfs := NewFs()
	t.Cleanup(func() { _ = os.RemoveAll(rfs.root) })

	if err := rfs.CreateServerFile("data.bin", randomData(t, 256<<10)); err != nil {
		t.Fatal(err)
	}
	dst := filepath.Join(rfs.root, "backup.tar.gz")

	a := &Archive{Filesystem: fs, MaxSize: 16 << 10}
	if err := a.Create(context.Background(), dst); !errors.Is(err, ErrArchiveTooLarge) {
		t.Fatalf("expected the archive to be refused, got %v", err)
	}

	var done, total int64
	a = &Archive{Filesystem: fs, MaxSize: 1 << 20, OnProgress: func(d, n int64) { done, total = d, n }}
	if err := a.Create(context.Background(), dst); err != nil {
		t.Fatal(err)
	}
	if done != 256<<10 || total != 256<<10 {
		t.Fatalf("progress ended at %d of %d", done, total)
	}
}

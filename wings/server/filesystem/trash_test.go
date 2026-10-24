package filesystem

import (
	"errors"
	"os"
	"path/filepath"
	"testing"
	"time"
)

func TestTrash(t *testing.T) {
	fs, rfs := NewFs()
	t.Cleanup(func() {
		_ = os.RemoveAll(rfs.root)
		_ = os.RemoveAll(fs.TrashPath())
	})

	if err := os.MkdirAll(filepath.Join(rfs.root, "server", "plugins", "data"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := rfs.CreateServerFileFromString("plugins/data/config.yml", "hello"); err != nil {
		t.Fatal(err)
	}
	if err := rfs.CreateServerFileFromString("plugins/old.jar", "jar"); err != nil {
		t.Fatal(err)
	}
	before, err := fs.DiskUsage(false)
	if err != nil {
		t.Fatal(err)
	}

	// The trash is outside of the server directory.
	if !filepathHasPrefix(fs.TrashPath(), filepath.Dir(fs.Path())) || filepathHasPrefix(fs.TrashPath(), fs.Path()) {
		t.Fatalf("unexpected trash path %s", fs.TrashPath())
	}

	if _, err := fs.MoveToTrash("/", time.Hour); err == nil {
		t.Fatal("the server directory was moved into the trash")
	}
	if _, err := fs.MoveToTrash("../../etc", time.Hour); err == nil {
		t.Fatal("a path outside of the server was moved into the trash")
	}

	dir, err := fs.MoveToTrash("plugins/data", time.Hour)
	if err != nil {
		t.Fatal(err)
	}
	if dir.Path != "/plugins/data" || !dir.Directory || dir.Size != int64(len("hello")) {
		t.Fatalf("unexpected entry %+v", dir)
	}
	jar, err := fs.MoveToTrash("/plugins/old.jar", -time.Minute)
	if err != nil {
		t.Fatal(err)
	}
	if _, err := os.Stat(filepath.Join(fs.Path(), "plugins", "data")); !os.IsNotExist(err) {
		t.Fatal("the folder is still in the server directory")
	}
	// Files in the trash still count against the disk.
	if after, _ := fs.DiskUsage(false); after != before {
		t.Fatalf("disk usage changed from %d to %d", before, after)
	}

	entries, err := fs.Trash()
	if err != nil || len(entries) != 2 {
		t.Fatalf("expected 2 entries, got %d (%v)", len(entries), err)
	}

	// Something new exists at the old path, so the restored folder gets another name.
	if err := os.MkdirAll(filepath.Join(fs.Path(), "plugins", "data"), 0o755); err != nil {
		t.Fatal(err)
	}
	restored, err := fs.RestoreTrash(dir.ID)
	if err != nil {
		t.Fatal(err)
	}
	if restored != "/plugins/data (restored)" {
		t.Fatalf("restored to %s", restored)
	}
	if b, err := os.ReadFile(filepath.Join(fs.Path(), "plugins", "data (restored)", "config.yml")); err != nil || string(b) != "hello" {
		t.Fatalf("restored file has %q (%v)", b, err)
	}
	if _, err := fs.RestoreTrash(dir.ID); !errors.Is(err, ErrTrashNotFound) {
		t.Fatalf("expected the entry to be gone, got %v", err)
	}
	if _, err := fs.RestoreTrash("../../etc"); !errors.Is(err, ErrTrashNotFound) {
		t.Fatalf("expected an invalid id to be refused, got %v", err)
	}

	// The jar expired already.
	if n := fs.PurgeExpiredTrash(time.Now()); n != 1 {
		t.Fatalf("purged %d entries", n)
	}
	if _, err := fs.TrashEntry(jar.ID); !errors.Is(err, ErrTrashNotFound) {
		t.Fatalf("expected the expired entry to be removed, got %v", err)
	}

	if _, err := fs.MoveToTrash("plugins/data (restored)", time.Hour); err != nil {
		t.Fatal(err)
	}
	if err := fs.EmptyTrash(); err != nil {
		t.Fatal(err)
	}
	if entries, _ := fs.Trash(); len(entries) != 0 {
		t.Fatalf("the trash still holds %d entries", len(entries))
	}
}

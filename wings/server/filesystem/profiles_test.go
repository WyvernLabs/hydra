package filesystem

import (
	"errors"
	"os"
	"path/filepath"
	"testing"
)

func TestGameProfiles(t *testing.T) {
	fs, rfs := NewFs()
	t.Cleanup(func() {
		_ = os.RemoveAll(rfs.root)
		_ = fs.DeleteProfiles()
	})

	if err := os.MkdirAll(filepath.Join(rfs.root, "server", "world"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := rfs.CreateServerFileFromString("world/level.dat", "minecraft"); err != nil {
		t.Fatal(err)
	}
	if err := rfs.CreateServerFileFromString(".hidden", "x"); err != nil {
		t.Fatal(err)
	}
	before, err := fs.DiskUsage(false)
	if err != nil {
		t.Fatal(err)
	}

	if _, err := fs.SaveProfile("../escape"); err == nil {
		t.Fatal("an invalid profile id was accepted")
	}

	size, err := fs.SaveProfile("profile-minecraft")
	if err != nil {
		t.Fatal(err)
	}
	if size != int64(len("minecraft")+1) {
		t.Fatalf("profile size %d", size)
	}
	entries, _ := os.ReadDir(fs.Path())
	if len(entries) != 0 {
		t.Fatalf("the server directory still holds %d entries", len(entries))
	}
	// The profile is outside of the server directory but counts against its disk.
	if !filepathHasPrefix(fs.ProfilesPath(), filepath.Dir(fs.Path())) || filepathHasPrefix(fs.ProfilesPath(), fs.Path()) {
		t.Fatalf("unexpected profiles path %s", fs.ProfilesPath())
	}
	if after, _ := fs.DiskUsage(false); after != before {
		t.Fatalf("disk usage changed from %d to %d", before, after)
	}

	if _, err := fs.SaveProfile("profile-minecraft"); !errors.Is(err, ErrProfileExists) {
		t.Fatalf("expected the profile to exist already, got %v", err)
	}

	// Another game was installed: loading needs an empty directory.
	if err := rfs.CreateServerFileFromString("terraria.wld", "terraria"); err != nil {
		t.Fatal(err)
	}
	if err := fs.LoadProfile("profile-minecraft"); !errors.Is(err, ErrRootNotEmpty) {
		t.Fatalf("expected the directory to be refused, got %v", err)
	}
	if err := fs.TruncateRootDirectory(); err != nil {
		t.Fatal(err)
	}
	if err := fs.LoadProfile("profile-minecraft"); err != nil {
		t.Fatal(err)
	}
	b, err := os.ReadFile(filepath.Join(fs.Path(), "world", "level.dat"))
	if err != nil || string(b) != "minecraft" {
		t.Fatalf("the files were not restored: %v", err)
	}
	if _, err := os.Stat(filepath.Join(fs.ProfilesPath(), "profile-minecraft")); !os.IsNotExist(err) {
		t.Fatal("the loaded profile was not removed")
	}
	if err := fs.LoadProfile("profile-minecraft"); !errors.Is(err, ErrProfileNotFound) {
		t.Fatalf("expected the profile to be gone, got %v", err)
	}
}

func filepathHasPrefix(p, prefix string) bool {
	rel, err := filepath.Rel(prefix, p)
	return err == nil && rel != "." && rel != ".." && !filepath.IsAbs(rel) && (len(rel) < 2 || rel[:2] != "..")
}

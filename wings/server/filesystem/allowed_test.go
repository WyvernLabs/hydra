package filesystem

import (
	"archive/tar"
	"bytes"
	"compress/gzip"
	"context"
	"os"
	"path/filepath"
	"testing"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestAllowedPaths(t *testing.T) {
	a := NewAllowedPaths([]string{"plugins/WorldEdit", "/world/build/"})
	require.True(t, a.IsRestricted())

	cases := []struct {
		path   string
		allows bool
		browse bool
	}{
		{"/", false, true},
		{"/plugins", false, true},
		{"/plugins/WorldEdit", true, true},
		{"plugins/WorldEdit/config.yml", true, true},
		{"/plugins/WorldEditor", false, false},
		{"/world/build/../../server.properties", false, false},
		{"/world/build/region/r.0.0.mca", true, true},
		{"/server.properties", false, false},
	}
	for _, c := range cases {
		assert.Equal(t, c.allows, a.Allows(c.path), "Allows(%s)", c.path)
		assert.Equal(t, c.browse, a.CanBrowse(c.path), "CanBrowse(%s)", c.path)
	}

	assert.False(t, NewAllowedPaths(nil).IsRestricted())
	assert.False(t, NewAllowedPaths([]string{"plugins", "/"}).IsRestricted())
	assert.True(t, NewAllowedPaths(nil).Allows("/anything"))
}

// A contained extraction keeps every entry inside the target folder and leaves
// out links, so a subuser limited to a folder cannot write anywhere else.
func TestDecompressFileContained(t *testing.T) {
	fs, rfs := NewFs()
	t.Cleanup(func() { _ = os.RemoveAll(rfs.root) })

	var buf bytes.Buffer
	gz := gzip.NewWriter(&buf)
	tw := tar.NewWriter(gz)
	for _, name := range []string{"inside.txt", "../escape.txt", "sub/../../escape2.txt", "sub/deep.txt"} {
		require.NoError(t, tw.WriteHeader(&tar.Header{Name: name, Mode: 0o644, Size: 2, Typeflag: tar.TypeReg}))
		_, err := tw.Write([]byte("hi"))
		require.NoError(t, err)
	}
	require.NoError(t, tw.WriteHeader(&tar.Header{Name: "link", Linkname: "../server.properties", Mode: 0o777, Typeflag: tar.TypeSymlink}))
	require.NoError(t, tw.Close())
	require.NoError(t, gz.Close())
	require.NoError(t, os.MkdirAll(filepath.Join(rfs.root, "server", "build"), 0o755))
	require.NoError(t, rfs.CreateServerFile("build/bundle.tar.gz", buf.Bytes()))

	require.NoError(t, fs.DecompressFileContained(context.Background(), "/build", "bundle.tar.gz"))

	root := filepath.Join(rfs.root, "server")
	for _, name := range []string{"build/inside.txt", "build/sub/deep.txt"} {
		_, err := os.Lstat(filepath.Join(root, name))
		assert.NoError(t, err, name)
	}
	for _, name := range []string{"escape.txt", "escape2.txt", "build/link"} {
		_, err := os.Lstat(filepath.Join(root, name))
		assert.ErrorIs(t, err, os.ErrNotExist, name)
	}
}

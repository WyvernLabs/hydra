package router

import (
	"bytes"
	"mime/multipart"
	"testing"
)

func TestUploadFileNameKeepsFolders(t *testing.T) {
	names := map[string]string{
		"level.dat":                 "level.dat",
		"world/region/r.0.0.mca":    "world/region/r.0.0.mca",
		"../../etc/passwd":          "etc/passwd",
		"/abs/file.txt":             "abs/file.txt",
		"world\\windows\\style.txt": "world/windows/style.txt",
		"a/./b/../c.txt":            "a/c.txt",
		"..":                        "",
	}

	for in, want := range names {
		var body bytes.Buffer
		w := multipart.NewWriter(&body)
		if _, err := w.CreateFormFile("files", in); err != nil {
			t.Fatal(err)
		}
		_ = w.Close()

		part, err := multipart.NewReader(&body, w.Boundary()).NextPart()
		if err != nil {
			t.Fatal(err)
		}
		if got := uploadFileName(part); got != want {
			t.Errorf("uploadFileName(%q) = %q, want %q", in, got, want)
		}
	}
}

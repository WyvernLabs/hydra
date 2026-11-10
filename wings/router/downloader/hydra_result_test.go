package downloader

import (
	"context"
	"testing"

	"emperror.dev/errors"
)

// Hydra: errors of background downloads are turned into short codes.
func TestErrorCode(t *testing.T) {
	cases := map[string]error{
		"cancelled":            context.Canceled,
		"internal":             errors.WithStack(ErrInternalResolution),
		"resolve":              errors.WithStack(ErrInvalidIPAddress),
		"no_length":            errors.New("downloader: request is missing ContentLength"),
		"status:404 Not Found": errors.New("downloader: got bad response status from endpoint: 404 Not Found"),
		"redirects":            errors.New("Get \"https://x\": downloader: too many redirects"),
		"something else":       errors.New("something else"),
	}
	for want, err := range cases {
		if got := ErrorCode(err); got != want {
			t.Errorf("ErrorCode(%q) = %q, want %q", err, got, want)
		}
	}
}

func TestFinishedIsEmptyForUnknownServers(t *testing.T) {
	if got := Finished("unknown"); len(got) != 0 {
		t.Fatalf("expected no results, got %d", len(got))
	}
}

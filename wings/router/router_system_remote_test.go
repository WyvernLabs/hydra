package router

import "testing"

func TestParseRemote(t *testing.T) {
	valid := map[string]string{
		"https://panel.example.com":       "https://panel.example.com",
		"https://panel.example.com/":      "https://panel.example.com",
		" http://203.0.113.5 ":            "http://203.0.113.5",
		"https://panel.example.com:8443/": "https://panel.example.com:8443",
	}
	for in, want := range valid {
		got, err := parseRemote(in)
		if err != nil || got != want {
			t.Errorf("parseRemote(%q) = %q, %v; want %q", in, got, err, want)
		}
	}

	for _, in := range []string{
		"",
		"panel.example.com",
		"ftp://panel.example.com",
		"https://",
		"https://user:pass@panel.example.com",
		"https://panel.example.com/path",
		"https://panel.example.com/?x=1",
		"https://panel.example.com/#x",
	} {
		if got, err := parseRemote(in); err == nil {
			t.Errorf("parseRemote(%q) = %q, want an error", in, got)
		}
	}
}

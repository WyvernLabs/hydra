package filesystem

import (
	"path"
	"strings"
)

// AllowedPaths (Hydra) holds the folders a subuser may work in. An empty list
// means the whole server. Paths are compared after cleaning them the same way
// the Panel does, so "/world/../plugins" counts as "/plugins".
type AllowedPaths []string

// NewAllowedPaths cleans the given folders. Allowing "/" is the same as no
// restriction at all.
func NewAllowedPaths(paths []string) AllowedPaths {
	var out AllowedPaths
	for _, p := range paths {
		p = CleanPath(p)
		if p == "/" {
			return nil
		}
		out = append(out, p)
	}
	return out
}

// CleanPath turns a path into the form "/a/b".
func CleanPath(p string) string {
	return path.Clean("/" + strings.ReplaceAll(p, "\\", "/"))
}

// IsRestricted returns true if the user may not use the whole server.
func (a AllowedPaths) IsRestricted() bool {
	return len(a) > 0
}

// Allows returns true if the path is one of the folders or inside one of them.
func (a AllowedPaths) Allows(p string) bool {
	if !a.IsRestricted() {
		return true
	}
	p = CleanPath(p)
	for _, allowed := range a {
		if p == allowed || strings.HasPrefix(p, allowed+"/") {
			return true
		}
	}
	return false
}

// CanBrowse returns true if the folder may be opened: an allowed folder,
// something inside one, or a folder on the way to one.
func (a AllowedPaths) CanBrowse(p string) bool {
	if a.Allows(p) {
		return true
	}
	p = CleanPath(p)
	for _, allowed := range a {
		if p == "/" || strings.HasPrefix(allowed, p+"/") {
			return true
		}
	}
	return false
}

// isWithin returns true if p is dir or inside of it.
func isWithin(dir string, p string) bool {
	dir = CleanPath(dir)
	p = CleanPath(p)
	return dir == "/" || p == dir || strings.HasPrefix(p, dir+"/")
}

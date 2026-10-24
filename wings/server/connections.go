package server

import (
	"github.com/pterodactyl/wings/system"
)

// Sftp returns the SFTP connection bag for the server instance. This bag tracks
// all open SFTP connections by individual user and allows for a single user or
// all users to be disconnected by other processes.
func (s *Server) Sftp() *system.ContextBag {
	s.Lock()
	defer s.Unlock()

	if s.sftpBag == nil {
		s.sftpBag = system.NewContextBag(s.Context())
	}

	return s.sftpBag
}

// FileTransfers returns the bag of running file downloads and uploads of the
// server by user, so they can be stopped when a user loses access (Hydra).
func (s *Server) FileTransfers() *system.ContextBag {
	s.Lock()
	defer s.Unlock()

	if s.transferBag == nil {
		s.transferBag = system.NewContextBag(s.Context())
	}

	return s.transferBag
}

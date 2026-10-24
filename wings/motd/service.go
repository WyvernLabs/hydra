package motd

import (
	"context"
	"net"
	"net/netip"
	"slices"
	"strconv"
	"sync"
	"time"

	"github.com/apex/log"
	"golang.org/x/sys/unix"
)

// MaxTransition is how long a server may be starting or stopping before its
// port is no longer redirected. An egg whose "done" line never matches leaves
// a running server in "starting", and players would never reach it.
var MaxTransition = 5 * time.Minute

// Port is where Wings answers the redirected pings, only reachable through
// the redirects (see Firewall).
var Port = 47823

// Server is what the MOTD needs to know about a server of the node.
type Server struct {
	UUID string
	Name string
	IP   string
	Port int
	// State is "offline", "starting", "running", "stopping" or "suspended".
	State string
}

// Target is a server whose port is redirected to the MOTD.
type Target struct {
	UUID  string
	IP    string
	Port  int
	Name  string
	State string
}

// Service redirects the ports of the servers that are not running to its
// listener and answers there.
type Service struct {
	// Servers lists the servers of the node.
	Servers func() []Server
	// Firewall applies the redirects, replaceable in tests.
	Firewall Firewall

	mu      sync.RWMutex
	targets []Target
	// since holds when each server entered its current state.
	since map[string]stateSince
}

type stateSince struct {
	state string
	at    time.Time
}

// Run listens and keeps the redirects up to date until ctx ends.
func (s *Service) Run(ctx context.Context) {
	if err := Load(); err != nil {
		log.WithField("error", err).Warn("motd: could not read the stored configuration")
	}

	listener, err := net.Listen("tcp4", "0.0.0.0:"+strconv.Itoa(Port))
	if err != nil {
		log.WithField("error", err).WithField("port", Port).Error("motd: could not listen, the custom MOTD is off")
		return
	}
	go func() {
		<-ctx.Done()
		_ = listener.Close()
	}()
	go s.accept(listener)

	var applied []Target
	verified := time.Time{}
	ticker := time.NewTicker(2 * time.Second)
	defer ticker.Stop()
	defer func() {
		_ = s.Firewall.Apply(nil)
	}()

	for {
		targets := s.desired()
		s.mu.Lock()
		s.targets = targets
		s.mu.Unlock()

		// Docker can put its own rules in front of the redirects, so check
		// their order once a minute even when nothing changed.
		if !slices.Equal(applied, targets) || time.Since(verified) > time.Minute {
			if err := s.Firewall.Apply(targets); err != nil {
				log.WithField("error", err).Warn("motd: could not update the redirects")
			} else {
				applied = targets
				verified = time.Now()
			}
		}

		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
		}
	}
}

// desired are the servers that get the MOTD right now.
func (s *Service) desired() []Target {
	cfg := Current()
	if cfg == nil || !cfg.Enabled {
		return nil
	}
	if s.since == nil {
		s.since = map[string]stateSince{}
	}
	now := time.Now()
	seen := map[string]bool{}
	var out []Target
	for _, server := range s.Servers() {
		seen[server.UUID] = true
		if prev, ok := s.since[server.UUID]; !ok || prev.state != server.State {
			s.since[server.UUID] = stateSince{state: server.State, at: now}
		}
		if server.State == "running" || server.Port <= 0 || !cfg.Uses(server.UUID) {
			continue
		}
		if (server.State == "starting" || server.State == "stopping") && now.Sub(s.since[server.UUID].at) > MaxTransition {
			continue
		}
		out = append(out, Target{UUID: server.UUID, IP: server.IP, Port: server.Port, Name: server.Name, State: server.State})
	}
	for uuid := range s.since {
		if !seen[uuid] {
			delete(s.since, uuid)
		}
	}
	slices.SortFunc(out, func(a, b Target) int {
		if a.Port != b.Port {
			return a.Port - b.Port
		}
		if a.IP < b.IP {
			return -1
		}
		if a.IP > b.IP {
			return 1
		}
		return 0
	})
	return out
}

func (s *Service) accept(listener net.Listener) {
	// At most this many pings are answered at the same time.
	slots := make(chan struct{}, 256)
	for {
		conn, err := listener.Accept()
		if err != nil {
			return
		}
		select {
		case slots <- struct{}{}:
		default:
			_ = conn.Close()
			continue
		}
		go func() {
			defer func() {
				<-slots
				_ = conn.Close()
			}()
			s.handle(conn)
		}()
	}
}

func (s *Service) handle(conn net.Conn) {
	tcp, ok := conn.(*net.TCPConn)
	if !ok {
		return
	}
	dst, err := originalDestination(tcp)
	if err != nil {
		return
	}
	target, ok := s.find(dst)
	cfg := Current()
	if !ok || cfg == nil {
		return
	}
	texts := cfg.States[target.State]
	name := target.Name
	if known := cfg.Names[target.UUID]; known != "" {
		name = known
	}
	answer := Answer{
		Lines:   Fill(texts.Line1, name) + "\n" + Fill(texts.Line2, name),
		Label:   Fill(texts.Label, name),
		Favicon: cfg.Favicon,
	}
	_ = Serve(conn, answer)
}

// find returns the server a redirected connection was meant for.
func (s *Service) find(dst netip.AddrPort) (Target, bool) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	for _, target := range s.targets {
		if target.Port != int(dst.Port()) {
			continue
		}
		if target.IP == "" || target.IP == "0.0.0.0" || target.IP == dst.Addr().String() {
			return target, true
		}
	}
	return Target{}, false
}

// originalDestination is the address a redirected connection was sent to.
func originalDestination(conn *net.TCPConn) (netip.AddrPort, error) {
	raw, err := conn.SyscallConn()
	if err != nil {
		return netip.AddrPort{}, err
	}
	var (
		addr netip.AddrPort
		serr error
	)
	err = raw.Control(func(fd uintptr) {
		// SO_ORIGINAL_DST returns a sockaddr_in, read through the
		// IPv6 multicast request, which has the same size.
		mreq, err := unix.GetsockoptIPv6Mreq(int(fd), unix.IPPROTO_IP, 80)
		if err != nil {
			serr = err
			return
		}
		m := mreq.Multiaddr
		addr = netip.AddrPortFrom(netip.AddrFrom4([4]byte{m[4], m[5], m[6], m[7]}), uint16(m[2])<<8|uint16(m[3]))
	})
	if err != nil {
		return netip.AddrPort{}, err
	}
	return addr, serr
}

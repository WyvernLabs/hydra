package motd

import (
	"bytes"
	"context"
	"net"
	"os/exec"
	"strconv"
	"strings"
	"time"

	"emperror.dev/errors"
)

// Firewall sends the ports of the targets to the MOTD listener.
type Firewall interface {
	Apply(targets []Target) error
}

// IPTables redirects with a chain of its own in the nat table, which is
// jumped to first from PREROUTING, before the rules of Docker. While a server
// runs it is not in the chain, so players reach the server as usual.
type IPTables struct{}

const chain = "HYDRA_MOTD"

func iptables(args ...string) ([]byte, error) {
	ctx, cancel := context.WithTimeout(context.Background(), 15*time.Second)
	defer cancel()
	out, err := exec.CommandContext(ctx, "iptables", append([]string{"-w", "5"}, args...)...).CombinedOutput()
	if err != nil {
		return out, errors.Wrap(err, "motd: iptables "+strings.Join(args, " ")+": "+strings.TrimSpace(string(out)))
	}
	return out, nil
}

// Rules are the rules of the chain for the targets.
func Rules(targets []Target) [][]string {
	var rules [][]string
	for _, t := range targets {
		rule := []string{"-A", chain, "-p", "tcp"}
		if ip := net.ParseIP(t.IP); ip != nil && ip.To4() != nil && !ip.IsUnspecified() {
			rule = append(rule, "-d", ip.String())
		} else if t.IP == "" || t.IP == "0.0.0.0" {
			rule = append(rule, "-m", "addrtype", "--dst-type", "LOCAL")
		} else {
			// IPv6 allocations are not redirected.
			continue
		}
		rule = append(rule, "--dport", strconv.Itoa(t.Port), "-j", "REDIRECT", "--to-ports", strconv.Itoa(Port))
		rules = append(rules, rule)
	}
	return rules
}

func (IPTables) Apply(targets []Target) error {
	if _, err := exec.LookPath("iptables"); err != nil {
		return errors.New("motd: iptables is not installed")
	}

	// The chain, filled anew.
	if _, err := iptables("-t", "nat", "-N", chain); err != nil && !strings.Contains(err.Error(), "exists") {
		return err
	}
	if _, err := iptables("-t", "nat", "-F", chain); err != nil {
		return err
	}
	for _, rule := range Rules(targets) {
		if _, err := iptables(append([]string{"-t", "nat"}, rule...)...); err != nil {
			return err
		}
	}

	// The jump to the chain has to come first in PREROUTING.
	list, err := iptables("-t", "nat", "-S", "PREROUTING")
	if err != nil {
		return err
	}
	jump := "-A PREROUTING -j " + chain
	lines := strings.Split(strings.TrimSpace(string(list)), "\n")
	first := ""
	for _, line := range lines {
		if strings.HasPrefix(line, "-A ") {
			first = line
			break
		}
	}
	if first != jump {
		for bytes.Contains(list, []byte(jump)) {
			if _, err := iptables("-t", "nat", "-D", "PREROUTING", "-j", chain); err != nil {
				break
			}
			list, _ = iptables("-t", "nat", "-S", "PREROUTING")
		}
		if _, err := iptables("-t", "nat", "-I", "PREROUTING", "1", "-j", chain); err != nil {
			return err
		}
	}

	// Redirected connections are let through even when the firewall of the
	// node (ufw) is on; the port itself stays closed to everything else.
	accept := []string{"INPUT", "-p", "tcp", "--dport", strconv.Itoa(Port), "-m", "conntrack", "--ctstate", "DNAT", "-j", "ACCEPT"}
	if _, err := iptables(append([]string{"-C"}, accept...)...); err != nil {
		if _, err := iptables(append([]string{"-I"}, accept...)...); err != nil {
			return err
		}
	}
	return nil
}

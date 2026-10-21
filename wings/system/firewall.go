package system

import (
	"bufio"
	"context"
	"fmt"
	"net"
	"os"
	"os/exec"
	"regexp"
	"slices"
	"strconv"
	"strings"
	"sync"
)

// Hydra: management of the UFW firewall of the node from the admin area of
// the Panel. Every change goes through the ufw command, so the node can still
// be managed by hand on the console as before.
//
// Rules are read with "ufw show added", which lists them in the same form as
// they were added, whether the firewall is active or not. A rule is removed
// again with "ufw delete <rule>".

// MysqlRuleComment marks the rules that open the database port for the panel
// and the nodes. They are kept in sync by the Panel and should not be edited
// by hand.
const MysqlRuleComment = "Hydra MySQL"

// FirewallError is an error with a code the Panel can translate, and the
// output of the command that failed, if any.
type FirewallError struct {
	Code   string
	Detail string
}

func (e *FirewallError) Error() string {
	if e.Detail == "" {
		return e.Code
	}
	return e.Code + ": " + e.Detail
}

func firewallError(code string, detail string) error {
	return &FirewallError{Code: code, Detail: strings.TrimSpace(detail)}
}

// FirewallRule is a single rule as listed by "ufw show added".
type FirewallRule struct {
	// Rule is the rule without the leading "ufw" and without its comment,
	// e.g. "allow from 1.2.3.4 to any port 3306 proto tcp".
	Rule    string `json:"rule"`
	Action  string `json:"action"`
	Comment string `json:"comment"`
	// Protected rules keep SSH, Wings or SFTP reachable. Removing them while
	// the firewall is active can lock the Panel and the admins out.
	Protected bool `json:"protected"`
	// Managed rules are kept in sync by the Panel (database access).
	Managed bool `json:"managed"`
}

// FirewallState is the state of UFW on this node.
type FirewallState struct {
	Installed bool           `json:"installed"`
	Active    bool           `json:"active"`
	Incoming  string         `json:"incoming"`
	Outgoing  string         `json:"outgoing"`
	Rules     []FirewallRule `json:"rules"`
	// Ports that are allowed automatically when the firewall is enabled.
	SSHPorts []int `json:"ssh_ports"`
	ApiPort  int   `json:"api_port"`
	SftpPort int   `json:"sftp_port"`
}

// Firewall manages UFW. Only one change runs at a time.
type Firewall struct {
	mu sync.Mutex

	// Ports of Wings, which must stay reachable for the Panel.
	ApiPort  int
	SftpPort int
}

var (
	firewallPortPattern = regexp.MustCompile(`^\d{1,5}(:\d{1,5})?(,\d{1,5}(:\d{1,5})?)*$`)
	firewallCommentText = regexp.MustCompile(`^[\p{L}\p{N} .,:;_/()+#@-]{0,100}$`)
	firewallShowLine    = regexp.MustCompile(`^ufw (.+?)(?: comment '(.*)')?$`)
)

// commandEnv is the environment for commands of this package: english output
// and no prompts from apt.
func commandEnv() []string {
	return append(os.Environ(), "LANG=C", "LC_ALL=C", "DEBIAN_FRONTEND=noninteractive")
}

// runCommand runs a command and returns its combined output.
func runCommand(ctx context.Context, name string, args ...string) (string, error) {
	cmd := exec.CommandContext(ctx, name, args...)
	cmd.Env = commandEnv()
	out, err := cmd.CombinedOutput()
	return string(out), err
}

func ufwPath() string {
	for _, p := range []string{"/usr/sbin/ufw", "/sbin/ufw", "/usr/bin/ufw"} {
		if _, err := os.Stat(p); err == nil {
			return p
		}
	}
	if p, err := exec.LookPath("ufw"); err == nil {
		return p
	}
	return ""
}

func (f *Firewall) ufw(ctx context.Context, args ...string) (string, error) {
	path := ufwPath()
	if path == "" {
		return "", firewallError("ufw_missing", "")
	}
	out, err := runCommand(ctx, path, args...)
	if err != nil {
		return out, firewallError("command_failed", out)
	}
	return out, nil
}

// State returns the current state of UFW and its rules.
func (f *Firewall) State(ctx context.Context) (*FirewallState, error) {
	f.mu.Lock()
	defer f.mu.Unlock()
	return f.state(ctx)
}

func (f *Firewall) state(ctx context.Context) (*FirewallState, error) {
	st := &FirewallState{
		Rules:    []FirewallRule{},
		SSHPorts: sshPorts(ctx),
		ApiPort:  f.ApiPort,
		SftpPort: f.SftpPort,
	}
	if ufwPath() == "" {
		return st, nil
	}
	st.Installed = true

	out, err := f.ufw(ctx, "status")
	if err != nil {
		return nil, err
	}
	st.Active = strings.Contains(out, "Status: active")
	st.Incoming, st.Outgoing = ufwDefaults()

	rules, err := f.rules(ctx)
	if err != nil {
		return nil, err
	}
	st.Rules = rules
	return st, nil
}

func (f *Firewall) rules(ctx context.Context) ([]FirewallRule, error) {
	out, err := f.ufw(ctx, "show", "added")
	if err != nil {
		return nil, err
	}

	vital := append(slices.Clone(sshPorts(ctx)), f.ApiPort, f.SftpPort)
	rules := []FirewallRule{}
	sc := bufio.NewScanner(strings.NewReader(out))
	for sc.Scan() {
		m := firewallShowLine.FindStringSubmatch(strings.TrimSpace(sc.Text()))
		if m == nil {
			continue
		}
		r := FirewallRule{Rule: m[1], Comment: m[2]}
		r.Action = strings.Fields(r.Rule)[0]
		r.Managed = r.Comment == MysqlRuleComment
		r.Protected = (r.Action == "allow" || r.Action == "limit") && ruleOpensPort(r.Rule, vital)
		rules = append(rules, r)
	}
	return rules, nil
}

// ruleOpensPort reports whether an incoming rule names one of the ports, e.g.
// "allow 22/tcp" or "allow from any to any port 8080 proto tcp".
func ruleOpensPort(rule string, ports []int) bool {
	fields := strings.Fields(rule)
	if slices.Contains(fields, "out") {
		return false
	}
	for i, field := range fields {
		var value string
		switch {
		case i == 1 || (i == 2 && fields[1] == "in"):
			value = strings.Split(field, "/")[0]
		case i > 0 && fields[i-1] == "port":
			value = field
		default:
			continue
		}
		for _, part := range strings.Split(value, ",") {
			lo, hi, _ := strings.Cut(part, ":")
			from, err := strconv.Atoi(lo)
			if err != nil {
				continue
			}
			to := from
			if hi != "" {
				if to, err = strconv.Atoi(hi); err != nil {
					continue
				}
			}
			for _, p := range ports {
				if p >= from && p <= to {
					return true
				}
			}
		}
	}
	return false
}

// ufwDefaults reads the default policies for incoming and outgoing traffic.
func ufwDefaults() (string, string) {
	in, out := "", ""
	b, err := os.ReadFile("/etc/default/ufw")
	if err != nil {
		return in, out
	}
	policy := func(v string) string {
		switch strings.Trim(v, `"' `) {
		case "DROP":
			return "deny"
		case "REJECT":
			return "reject"
		case "ACCEPT":
			return "allow"
		}
		return ""
	}
	for _, line := range strings.Split(string(b), "\n") {
		k, v, ok := strings.Cut(strings.TrimSpace(line), "=")
		if !ok {
			continue
		}
		switch k {
		case "DEFAULT_INPUT_POLICY":
			in = policy(v)
		case "DEFAULT_OUTPUT_POLICY":
			out = policy(v)
		}
	}
	return in, out
}

// sshPorts returns the ports of the SSH daemon of the node, 22 if they cannot
// be read.
func sshPorts(ctx context.Context) []int {
	var ports []int
	if out, err := runCommand(ctx, "sshd", "-T"); err == nil {
		for _, line := range strings.Split(out, "\n") {
			if v, ok := strings.CutPrefix(strings.TrimSpace(line), "port "); ok {
				if p, err := strconv.Atoi(strings.TrimSpace(v)); err == nil && !slices.Contains(ports, p) {
					ports = append(ports, p)
				}
			}
		}
	}
	if len(ports) == 0 {
		ports = []int{22}
	}
	return ports
}

// Install installs UFW with apt. It is not enabled.
func (f *Firewall) Install(ctx context.Context) error {
	f.mu.Lock()
	defer f.mu.Unlock()
	return installUfw(ctx)
}

func installUfw(ctx context.Context) error {
	if ufwPath() != "" {
		return nil
	}
	return aptInstall(ctx, "ufw")
}

func aptInstall(ctx context.Context, packages ...string) error {
	if _, err := exec.LookPath("apt-get"); err != nil {
		return firewallError("unsupported_os", "")
	}
	if out, err := runCommand(ctx, "apt-get", "update", "-q"); err != nil {
		return firewallError("command_failed", out)
	}
	args := append([]string{"install", "-y", "-q", "-o", "Dpkg::Options::=--force-confold"}, packages...)
	if out, err := runCommand(ctx, "apt-get", args...); err != nil {
		return firewallError("command_failed", out)
	}
	return nil
}

// Enable allows SSH, Wings and SFTP and then enables the firewall, so the
// node stays reachable.
func (f *Firewall) Enable(ctx context.Context) error {
	f.mu.Lock()
	defer f.mu.Unlock()
	return f.enable(ctx)
}

func (f *Firewall) enable(ctx context.Context) error {
	if ufwPath() == "" {
		return firewallError("ufw_missing", "")
	}
	for _, p := range sshPorts(ctx) {
		if _, err := f.ufw(ctx, "allow", strconv.Itoa(p)+"/tcp", "comment", "SSH"); err != nil {
			return err
		}
	}
	if _, err := f.ufw(ctx, "allow", strconv.Itoa(f.ApiPort)+"/tcp", "comment", "Hydra Wings"); err != nil {
		return err
	}
	if _, err := f.ufw(ctx, "allow", strconv.Itoa(f.SftpPort)+"/tcp", "comment", "Hydra SFTP"); err != nil {
		return err
	}
	_, err := f.ufw(ctx, "--force", "enable")
	return err
}

// Disable turns the firewall off. The rules are kept.
func (f *Firewall) Disable(ctx context.Context) error {
	f.mu.Lock()
	defer f.mu.Unlock()
	_, err := f.ufw(ctx, "disable")
	return err
}

// NewFirewallRule is a rule to add, as sent by the Panel.
type NewFirewallRule struct {
	Action    string `json:"action"`
	Direction string `json:"direction"`
	Protocol  string `json:"protocol"`
	Port      string `json:"port"`
	Address   string `json:"address"`
	Comment   string `json:"comment"`
}

// Args validates the rule and turns it into arguments for ufw.
func (r NewFirewallRule) Args() ([]string, error) {
	invalid := func(field string) error { return firewallError("invalid_rule", field) }

	if !slices.Contains([]string{"allow", "deny", "reject", "limit"}, r.Action) {
		return nil, invalid("action")
	}
	if r.Direction != "in" && r.Direction != "out" {
		return nil, invalid("direction")
	}
	if r.Action == "limit" && r.Direction != "in" {
		return nil, invalid("direction")
	}
	if r.Protocol != "any" && r.Protocol != "tcp" && r.Protocol != "udp" {
		return nil, invalid("protocol")
	}
	if r.Port != "" {
		if !firewallPortPattern.MatchString(r.Port) {
			return nil, invalid("port")
		}
		for _, part := range strings.FieldsFunc(r.Port, func(c rune) bool { return c == ',' || c == ':' }) {
			if p, _ := strconv.Atoi(part); p < 1 || p > 65535 {
				return nil, invalid("port")
			}
		}
		// ufw only takes port ranges and lists together with a protocol.
		if strings.ContainsAny(r.Port, ":,") && r.Protocol == "any" {
			return nil, invalid("protocol")
		}
	}
	address := "any"
	if r.Address != "" && r.Address != "any" {
		if !validAddress(r.Address) {
			return nil, invalid("address")
		}
		address = r.Address
	}
	if !firewallCommentText.MatchString(r.Comment) || r.Comment == MysqlRuleComment {
		return nil, invalid("comment")
	}
	if r.Port == "" && address == "any" {
		// This would allow or block everything, which is what the default
		// policy is for.
		return nil, invalid("port")
	}

	args := []string{r.Action, r.Direction}
	if r.Direction == "in" {
		args = append(args, "from", address, "to", "any")
	} else {
		args = append(args, "to", address)
	}
	if r.Port != "" {
		args = append(args, "port", r.Port)
	}
	if r.Protocol != "any" {
		args = append(args, "proto", r.Protocol)
	}
	if r.Comment != "" {
		args = append(args, "comment", r.Comment)
	}
	return args, nil
}

func validAddress(v string) bool {
	if net.ParseIP(v) != nil {
		return true
	}
	_, _, err := net.ParseCIDR(v)
	return err == nil
}

// AddRule adds a rule.
func (f *Firewall) AddRule(ctx context.Context, rule NewFirewallRule) error {
	args, err := rule.Args()
	if err != nil {
		return err
	}
	f.mu.Lock()
	defer f.mu.Unlock()
	_, err = f.ufw(ctx, args...)
	return err
}

// DeleteRule removes a rule as listed in FirewallRule.Rule. The rule has to
// exist, so nothing else than an existing rule is ever passed to ufw.
func (f *Firewall) DeleteRule(ctx context.Context, rule string) error {
	f.mu.Lock()
	defer f.mu.Unlock()

	rules, err := f.rules(ctx)
	if err != nil {
		return err
	}
	if !slices.ContainsFunc(rules, func(r FirewallRule) bool { return r.Rule == rule }) {
		return firewallError("rule_not_found", "")
	}
	_, err = f.ufw(ctx, append([]string{"--force", "delete"}, strings.Fields(rule)...)...)
	return err
}

// SyncMysqlRules opens the database port for exactly these addresses (and the
// Docker network of this node, so servers here reach the database as well)
// and removes rules for addresses that are no longer listed.
func (f *Firewall) SyncMysqlRules(ctx context.Context, port int, sources []string) error {
	f.mu.Lock()
	defer f.mu.Unlock()
	return f.syncMysqlRules(ctx, port, sources)
}

func (f *Firewall) syncMysqlRules(ctx context.Context, port int, sources []string) error {
	if port < 1 || port > 65535 {
		return firewallError("invalid_rule", "port")
	}
	normalized := []string{}
	for _, s := range sources {
		n, ok := NormalizeAddress(s)
		if !ok {
			return firewallError("invalid_rule", "address")
		}
		if !slices.Contains(normalized, n) {
			normalized = append(normalized, n)
		}
	}
	sources = normalized

	want := map[string]bool{}
	for _, s := range sources {
		want[fmt.Sprintf("allow from %s to any port %d proto tcp", s, port)] = true
	}

	rules, err := f.rules(ctx)
	if err != nil {
		return err
	}
	for _, r := range rules {
		if !r.Managed {
			continue
		}
		if want[r.Rule] {
			delete(want, r.Rule)
			continue
		}
		if _, err := f.ufw(ctx, append([]string{"--force", "delete"}, strings.Fields(r.Rule)...)...); err != nil {
			return err
		}
	}
	for _, s := range sources {
		rule := fmt.Sprintf("allow from %s to any port %d proto tcp", s, port)
		if !want[rule] {
			continue
		}
		delete(want, rule)
		if _, err := f.ufw(ctx, append(strings.Fields(rule), "comment", MysqlRuleComment)...); err != nil {
			return err
		}
	}
	return nil
}

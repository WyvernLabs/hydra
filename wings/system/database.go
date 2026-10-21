package system

import (
	"context"
	"errors"
	"fmt"
	"net"
	"os"
	"os/exec"
	"regexp"
	"strconv"
	"strings"
	"sync"
	"time"
)

// Hydra: sets up MariaDB on the node as a database host for the Panel in one
// go: install, listen on all addresses, create the user of the Panel and open
// the port in the firewall for the panel and the nodes only.
//
// The setup can take a few minutes (apt), so it runs in the background and
// the Panel polls its state.

// DatabaseSetupRequest is sent by the Panel.
type DatabaseSetupRequest struct {
	ID       string   `json:"id"`
	Username string   `json:"username"`
	Password string   `json:"password"`
	Port     int      `json:"port"`
	Sources  []string `json:"sources"`
	// Enable UFW if it is off; without it the database port is open to
	// everybody.
	EnableFirewall bool `json:"enable_firewall"`
}

// DatabaseSetupState is the state of the last setup.
type DatabaseSetupState struct {
	ID       string    `json:"id"`
	Running  bool      `json:"running"`
	Success  bool      `json:"success"`
	Step     string    `json:"step"`
	Error    string    `json:"error"`
	Log      string    `json:"log"`
	Started  time.Time `json:"started_at"`
	Finished time.Time `json:"finished_at,omitempty"`
}

// DatabaseState is the state of the database server of the node.
type DatabaseState struct {
	Installed bool                `json:"installed"`
	Running   bool                `json:"running"`
	Version   string              `json:"version"`
	Setup     *DatabaseSetupState `json:"setup"`
}

var (
	databaseUserPattern     = regexp.MustCompile(`^[a-z][a-z0-9_]{0,31}$`)
	databasePasswordPattern = regexp.MustCompile(`^[A-Za-z0-9]{24,128}$`)
	databaseSetupIDPattern  = regexp.MustCompile(`^[A-Za-z0-9-]{1,64}$`)
)

// ErrDatabaseSetupRunning is returned while a setup is running.
var ErrDatabaseSetupRunning = errors.New("a database setup is already running")

// Database sets up the database server of the node.
type Database struct {
	Firewall *Firewall

	mu    sync.Mutex
	setup *DatabaseSetupState
}

func databaseClient() string {
	for _, name := range []string{"mariadb", "mysql"} {
		if p, err := exec.LookPath(name); err == nil {
			return p
		}
	}
	return ""
}

func databaseService(ctx context.Context) string {
	for _, name := range []string{"mariadb", "mysql"} {
		out, _ := runCommand(ctx, "systemctl", "list-unit-files", name+".service", "--no-legend")
		if strings.Contains(out, name+".service") {
			return name
		}
	}
	return ""
}

// State returns whether the database server is installed and running, and
// the state of the last setup.
func (d *Database) State(ctx context.Context) DatabaseState {
	st := DatabaseState{}
	if client := databaseClient(); client != "" {
		st.Installed = databaseService(ctx) != ""
		if out, err := runCommand(ctx, client, "--version"); err == nil {
			st.Version = strings.TrimSpace(out)
		}
	}
	if service := databaseService(ctx); service != "" {
		out, _ := runCommand(ctx, "systemctl", "is-active", service)
		st.Running = strings.TrimSpace(out) == "active"
	}

	d.mu.Lock()
	if d.setup != nil {
		s := *d.setup
		st.Setup = &s
	}
	d.mu.Unlock()
	return st
}

// Validate checks the request before the setup is started.
func (r DatabaseSetupRequest) Validate() error {
	if !databaseSetupIDPattern.MatchString(r.ID) {
		return firewallError("invalid_request", "id")
	}
	if !databaseUserPattern.MatchString(r.Username) {
		return firewallError("invalid_request", "username")
	}
	if !databasePasswordPattern.MatchString(r.Password) {
		return firewallError("invalid_request", "password")
	}
	if r.Port < 1 || r.Port > 65535 {
		return firewallError("invalid_request", "port")
	}
	for _, s := range r.Sources {
		if !validAddress(s) {
			return firewallError("invalid_request", "sources")
		}
	}
	return nil
}

// Start runs the setup in the background.
func (d *Database) Start(r DatabaseSetupRequest, dockerSubnets []string) error {
	if err := r.Validate(); err != nil {
		return err
	}

	d.mu.Lock()
	defer d.mu.Unlock()
	if d.setup != nil && d.setup.Running {
		return ErrDatabaseSetupRunning
	}
	d.setup = &DatabaseSetupState{ID: r.ID, Running: true, Started: time.Now()}

	go d.run(r, dockerSubnets)
	return nil
}

func (d *Database) update(fn func(s *DatabaseSetupState)) {
	d.mu.Lock()
	defer d.mu.Unlock()
	fn(d.setup)
}

func (d *Database) step(name string) {
	d.update(func(s *DatabaseSetupState) {
		s.Step = name
		s.Log += "==> " + name + "\n"
	})
}

func (d *Database) log(out string) {
	if out = strings.TrimSpace(out); out == "" {
		return
	}
	d.update(func(s *DatabaseSetupState) {
		s.Log += out + "\n"
		if len(s.Log) > 64*1024 {
			s.Log = s.Log[len(s.Log)-64*1024:]
		}
	})
}

func (d *Database) run(r DatabaseSetupRequest, dockerSubnets []string) {
	ctx, cancel := context.WithTimeout(context.Background(), 20*time.Minute)
	defer cancel()

	err := d.setUp(ctx, r, dockerSubnets)
	d.update(func(s *DatabaseSetupState) {
		s.Running = false
		s.Finished = time.Now()
		if err == nil {
			s.Success = true
			s.Step = "done"
			return
		}
		var fe *FirewallError
		if errors.As(err, &fe) {
			s.Error = fe.Code
			if fe.Detail != "" {
				s.Log += fe.Detail + "\n"
			}
		} else {
			s.Error = "command_failed"
			s.Log += err.Error() + "\n"
		}
	})
}

func (d *Database) setUp(ctx context.Context, r DatabaseSetupRequest, dockerSubnets []string) error {
	// 1. MariaDB (or an existing MySQL) and UFW.
	d.step("install")
	packages := []string{}
	if databaseClient() == "" || databaseService(ctx) == "" {
		packages = append(packages, "mariadb-server")
	}
	if ufwPath() == "" {
		packages = append(packages, "ufw")
	}
	if len(packages) > 0 {
		d.log("apt-get install " + strings.Join(packages, " "))
		if err := aptInstall(ctx, packages...); err != nil {
			return err
		}
	}
	service := databaseService(ctx)
	client := databaseClient()
	if service == "" || client == "" {
		return firewallError("database_missing", "")
	}

	// 2. Listen on all addresses and the chosen port.
	d.step("configure")
	path := databaseConfigPath()
	config := fmt.Sprintf("# Managed by Hydra: the database host of this node for the Panel.\n[mysqld]\nbind-address = 0.0.0.0\nport = %d\n", r.Port)
	if err := os.WriteFile(path, []byte(config), 0o644); err != nil {
		return firewallError("command_failed", err.Error())
	}
	d.log("wrote " + path)

	// 3. Restart, so the configuration is used.
	d.step("restart")
	if out, err := runCommand(ctx, "systemctl", "enable", service); err != nil {
		return firewallError("command_failed", out)
	}
	if out, err := runCommand(ctx, "systemctl", "restart", service); err != nil {
		return firewallError("command_failed", out)
	}
	if err := waitForDatabase(ctx, client); err != nil {
		return err
	}

	// 4. The user of the Panel. Root logs in over the socket, as set up by the
	// packages of Debian and Ubuntu.
	d.step("user")
	sql := fmt.Sprintf(
		"CREATE USER IF NOT EXISTS '%[1]s'@'%%' IDENTIFIED BY '%[2]s';\n"+
			"ALTER USER '%[1]s'@'%%' IDENTIFIED BY '%[2]s';\n"+
			"GRANT ALL PRIVILEGES ON *.* TO '%[1]s'@'%%' WITH GRANT OPTION;\n"+
			"FLUSH PRIVILEGES;\n",
		r.Username, r.Password,
	)
	cmd := exec.CommandContext(ctx, client, "--protocol=socket", "-uroot")
	cmd.Env = commandEnv()
	cmd.Stdin = strings.NewReader(sql)
	if out, err := cmd.CombinedOutput(); err != nil {
		return firewallError("database_root_access", string(out))
	}

	// The user has to be able to log in over the network.
	cmd = exec.CommandContext(ctx, client, "--protocol=tcp", "-h127.0.0.1", "-P"+strconv.Itoa(r.Port), "-u"+r.Username, "-e", "SELECT 1")
	cmd.Env = append(commandEnv(), "MYSQL_PWD="+r.Password)
	if out, err := cmd.CombinedOutput(); err != nil {
		return firewallError("database_login", string(out))
	}
	d.log("user " + r.Username + " can log in")

	// 5. Firewall: the port is only open for the panel and the nodes.
	d.step("firewall")
	sources := append(append([]string{}, r.Sources...), dockerSubnets...)
	d.Firewall.mu.Lock()
	defer d.Firewall.mu.Unlock()
	if err := d.Firewall.syncMysqlRules(ctx, r.Port, sources); err != nil {
		return err
	}
	d.log("port " + strconv.Itoa(r.Port) + " open for " + strings.Join(sources, ", "))

	out, err := d.Firewall.ufw(ctx, "status")
	if err != nil {
		return err
	}
	if !strings.Contains(out, "Status: active") {
		if !r.EnableFirewall {
			d.log("ufw is inactive, the database port is reachable from everywhere")
			return nil
		}
		if err := d.Firewall.enable(ctx); err != nil {
			return err
		}
		d.log("ufw enabled (SSH, Wings and SFTP allowed)")
	}
	return nil
}

// databaseConfigPath is a file that is read after the configuration of the
// package, so its settings win.
func databaseConfigPath() string {
	for _, dir := range []string{"/etc/mysql/mariadb.conf.d", "/etc/mysql/mysql.conf.d", "/etc/mysql/conf.d", "/etc/my.cnf.d"} {
		if st, err := os.Stat(dir); err == nil && st.IsDir() {
			return dir + "/99-hydra.cnf"
		}
	}
	return "/etc/mysql/conf.d/99-hydra.cnf"
}

func waitForDatabase(ctx context.Context, client string) error {
	var out string
	for i := 0; i < 30; i++ {
		var err error
		if out, err = runCommand(ctx, client, "--protocol=socket", "-uroot", "-e", "SELECT 1"); err == nil {
			return nil
		}
		select {
		case <-ctx.Done():
			return ctx.Err()
		case <-time.After(time.Second):
		}
	}
	return firewallError("database_root_access", out)
}

// NormalizeAddress returns an address the way ufw lists it: a single host
// without a prefix, networks with their network address.
func NormalizeAddress(v string) (string, bool) {
	if ip := net.ParseIP(v); ip != nil {
		return ip.String(), true
	}
	ip, network, err := net.ParseCIDR(v)
	if err != nil {
		return "", false
	}
	if ones, bits := network.Mask.Size(); ones == bits {
		return ip.String(), true
	}
	return network.String(), true
}

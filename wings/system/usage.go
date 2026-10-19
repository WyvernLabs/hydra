package system

import (
	"bufio"
	"os"
	"runtime"
	"strconv"
	"strings"
	"sync"
	"syscall"
	"time"
)

// Hydra: live resource usage of the node for the admin area of the Panel.
// A sampler reads /proc once a second and keeps the last minute, so the Panel
// can draw graphs right away instead of waiting for its own samples.

const usageHistory = 60

// UsageSample is one second of CPU and network usage.
type UsageSample struct {
	Time  int64   `json:"time"`
	CPU   float64 `json:"cpu"`
	RxBps float64 `json:"rx_bps"`
	TxBps float64 `json:"tx_bps"`
}

// Usage is the current resource usage of the node.
type Usage struct {
	// CPU is the usage of all threads together, 0–100.
	CPU         float64       `json:"cpu"`
	CPUThreads  int           `json:"cpu_threads"`
	MemoryUsed  uint64        `json:"memory_used"`
	MemoryTotal uint64        `json:"memory_total"`
	SwapUsed    uint64        `json:"swap_used"`
	SwapTotal   uint64        `json:"swap_total"`
	DiskUsed    uint64        `json:"disk_used"`
	DiskTotal   uint64        `json:"disk_total"`
	RxBps       float64       `json:"rx_bps"`
	TxBps       float64       `json:"tx_bps"`
	RxTotal     uint64        `json:"rx_total"`
	TxTotal     uint64        `json:"tx_total"`
	Load        [3]float64    `json:"load"`
	Uptime      int64         `json:"uptime"`
	History     []UsageSample `json:"history"`
}

type usageSampler struct {
	mu      sync.Mutex
	history []UsageSample
	cpu     cpuTimes
	rx, tx  uint64
	at      time.Time
}

type cpuTimes struct{ idle, total uint64 }

var (
	sampler     = &usageSampler{}
	samplerOnce sync.Once
)

// GetUsage returns the current usage of the node. The disk usage is measured
// for the partition holding dataPath, the directory of the servers.
func GetUsage(dataPath string) Usage {
	samplerOnce.Do(sampler.start)

	u := Usage{CPUThreads: runtime.NumCPU()}

	sampler.mu.Lock()
	u.History = append([]UsageSample(nil), sampler.history...)
	u.RxTotal, u.TxTotal = sampler.rx, sampler.tx
	sampler.mu.Unlock()
	if n := len(u.History); n > 0 {
		last := u.History[n-1]
		u.CPU, u.RxBps, u.TxBps = last.CPU, last.RxBps, last.TxBps
	}

	mem := readKeyValues("/proc/meminfo")
	u.MemoryTotal = mem["MemTotal"] * 1024
	u.MemoryUsed = u.MemoryTotal - min(mem["MemAvailable"]*1024, u.MemoryTotal)
	u.SwapTotal = mem["SwapTotal"] * 1024
	u.SwapUsed = u.SwapTotal - min(mem["SwapFree"]*1024, u.SwapTotal)

	var fs syscall.Statfs_t
	if syscall.Statfs(dataPath, &fs) == nil || syscall.Statfs("/", &fs) == nil {
		// Like "df": used blocks against what is usable without the reserved blocks.
		used := (fs.Blocks - fs.Bfree) * uint64(fs.Bsize)
		u.DiskUsed = used
		u.DiskTotal = used + fs.Bavail*uint64(fs.Bsize)
	}

	if b, err := os.ReadFile("/proc/loadavg"); err == nil {
		f := strings.Fields(string(b))
		for i := 0; i < 3 && i < len(f); i++ {
			u.Load[i], _ = strconv.ParseFloat(f[i], 64)
		}
	}
	if b, err := os.ReadFile("/proc/uptime"); err == nil {
		if f := strings.Fields(string(b)); len(f) > 0 {
			up, _ := strconv.ParseFloat(f[0], 64)
			u.Uptime = int64(up)
		}
	}

	return u
}

func (s *usageSampler) start() {
	s.cpu = readCPUTimes()
	s.rx, s.tx = readNetworkBytes()
	s.at = time.Now()

	go func() {
		ticker := time.NewTicker(time.Second)
		defer ticker.Stop()
		for range ticker.C {
			s.sample()
		}
	}()
}

func (s *usageSampler) sample() {
	cpu := readCPUTimes()
	rx, tx := readNetworkBytes()
	now := time.Now()

	s.mu.Lock()
	defer s.mu.Unlock()

	sample := UsageSample{Time: now.Unix()}
	if total := cpu.total - s.cpu.total; total > 0 && cpu.total >= s.cpu.total {
		busy := total - min(cpu.idle-s.cpu.idle, total)
		sample.CPU = float64(busy) / float64(total) * 100
	}
	if secs := now.Sub(s.at).Seconds(); secs > 0 {
		// Counters can go back when an interface goes away; skip that second.
		if rx >= s.rx {
			sample.RxBps = float64(rx-s.rx) / secs
		}
		if tx >= s.tx {
			sample.TxBps = float64(tx-s.tx) / secs
		}
	}

	s.cpu, s.rx, s.tx, s.at = cpu, rx, tx, now
	s.history = append(s.history, sample)
	if len(s.history) > usageHistory {
		s.history = s.history[len(s.history)-usageHistory:]
	}
}

// readCPUTimes reads the overall CPU times from /proc/stat.
func readCPUTimes() cpuTimes {
	f, err := os.Open("/proc/stat")
	if err != nil {
		return cpuTimes{}
	}
	defer f.Close()

	scanner := bufio.NewScanner(f)
	if !scanner.Scan() {
		return cpuTimes{}
	}
	fields := strings.Fields(scanner.Text())
	if len(fields) < 5 || fields[0] != "cpu" {
		return cpuTimes{}
	}

	var t cpuTimes
	for i, v := range fields[1:] {
		// guest and guest_nice are already part of user and nice.
		if i >= 8 {
			break
		}
		n, _ := strconv.ParseUint(v, 10, 64)
		t.total += n
		// idle and iowait
		if i == 3 || i == 4 {
			t.idle += n
		}
	}
	return t
}

// readNetworkBytes sums the received and sent bytes of the host's own network
// interfaces, leaving out loopback and the virtual interfaces of Docker.
func readNetworkBytes() (rx, tx uint64) {
	f, err := os.Open("/proc/net/dev")
	if err != nil {
		return 0, 0
	}
	defer f.Close()

	scanner := bufio.NewScanner(f)
	for scanner.Scan() {
		name, data, ok := strings.Cut(scanner.Text(), ":")
		if !ok {
			continue
		}
		name = strings.TrimSpace(name)
		if isVirtualInterface(name) {
			continue
		}
		fields := strings.Fields(data)
		if len(fields) < 9 {
			continue
		}
		r, _ := strconv.ParseUint(fields[0], 10, 64)
		t, _ := strconv.ParseUint(fields[8], 10, 64)
		rx += r
		tx += t
	}
	return rx, tx
}

func isVirtualInterface(name string) bool {
	for _, prefix := range []string{"lo", "docker", "veth", "br-", "pterodactyl", "virbr", "cni", "flannel", "kube"} {
		if strings.HasPrefix(name, prefix) {
			return true
		}
	}
	return false
}

// readKeyValues reads files like /proc/meminfo ("Key:   123 kB") into a map.
func readKeyValues(path string) map[string]uint64 {
	out := make(map[string]uint64)
	f, err := os.Open(path)
	if err != nil {
		return out
	}
	defer f.Close()

	scanner := bufio.NewScanner(f)
	for scanner.Scan() {
		key, value, ok := strings.Cut(scanner.Text(), ":")
		if !ok {
			continue
		}
		if fields := strings.Fields(value); len(fields) > 0 {
			out[key], _ = strconv.ParseUint(fields[0], 10, 64)
		}
	}
	return out
}

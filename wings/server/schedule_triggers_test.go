package server

import (
	"context"
	"sync"
	"testing"
	"time"

	"github.com/pterodactyl/wings/remote"
)

// triggerClient records the schedules the server asked the panel to run.
type triggerClient struct {
	remote.Client
	mu    sync.Mutex
	fired []int
}

func (c *triggerClient) TriggerSchedule(_ context.Context, _ string, schedule int, _ string) error {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.fired = append(c.fired, schedule)
	return nil
}

func (c *triggerClient) count() int {
	c.mu.Lock()
	defer c.mu.Unlock()
	return len(c.fired)
}

func waitFor(t *testing.T, c *triggerClient, n int) {
	t.Helper()
	deadline := time.Now().Add(2 * time.Second)
	for c.count() < n && time.Now().Before(deadline) {
		time.Sleep(10 * time.Millisecond)
	}
	// Give a trigger that should not fire the chance to do so.
	time.Sleep(50 * time.Millisecond)
	if got := c.count(); got != n {
		t.Fatalf("expected %d triggered schedules, got %d (%v)", n, got, c.fired)
	}
}

func TestScheduleTriggers(t *testing.T) {
	client := &triggerClient{}
	s, err := New(client)
	if err != nil {
		t.Fatal(err)
	}
	s.cfg.HydraTriggers = []ScheduleTrigger{
		{Schedule: 1, Type: "state", Value: "offline"},
		{Schedule: 2, Type: "console", Value: "Player joined"},
		{Schedule: 3, Type: "console", Value: `^\[\d+:\d+\] Done \(`, Regex: true},
		{Schedule: 4, Type: "console", Value: "([", Regex: true},
	}

	s.checkStateTriggers("running")
	waitFor(t, client, 0)
	s.checkStateTriggers("offline")
	waitFor(t, client, 1)

	// Text is found anywhere in the line, ignoring case and colors.
	s.checkConsoleTriggers([]byte("\x1b[32m[12:00] player JOINED the game\x1b[0m"))
	waitFor(t, client, 2)
	// Within the cooldown the same schedule does not run again.
	s.checkConsoleTriggers([]byte("Player joined again"))
	waitFor(t, client, 2)

	s.checkConsoleTriggers([]byte("[12:01] Done (3.2s)!"))
	waitFor(t, client, 3)
	s.checkConsoleTriggers([]byte("Something else [12:01] Done ("))
	waitFor(t, client, 3)
}

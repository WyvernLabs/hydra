package server

import (
	"bytes"
	"context"
	"regexp"
	"strings"
	"sync"
	"time"
)

// ScheduleTrigger (Hydra) starts a schedule of the server when it reaches a
// state or when a line in its console matches. The panel sends them with the
// configuration of the server; the node only reports matches back.
type ScheduleTrigger struct {
	// Id of the schedule in the panel.
	Schedule int `json:"schedule"`
	// "state" or "console".
	Type string `json:"type"`
	// The state (running, offline, starting, stopping) or the console text.
	Value string `json:"value"`
	// Value is a regular expression instead of text the line has to contain.
	Regex bool `json:"regex"`
	// Seconds before the trigger fires again.
	Cooldown int `json:"cooldown"`
}

// minTriggerCooldown keeps a console line the schedule itself causes from
// starting it over and over.
const minTriggerCooldown = 5 * time.Second

type scheduleTriggers struct {
	mu      sync.Mutex
	last    map[int]time.Time
	regexes map[string]*regexp.Regexp
}

func (s *Server) triggers() *scheduleTriggers {
	s.triggerOnce.Do(func() {
		s.triggerState = &scheduleTriggers{last: map[int]time.Time{}, regexes: map[string]*regexp.Regexp{}}
	})
	return s.triggerState
}

// scheduleTriggers returns the triggers of the given type.
func (s *Server) scheduleTriggers(kind string) []ScheduleTrigger {
	s.cfg.mu.RLock()
	defer s.cfg.mu.RUnlock()
	var list []ScheduleTrigger
	for _, t := range s.cfg.HydraTriggers {
		if t.Type == kind && t.Schedule > 0 && t.Value != "" {
			list = append(list, t)
		}
	}
	return list
}

// checkStateTriggers fires the triggers waiting for the new state.
func (s *Server) checkStateTriggers(state string) {
	for _, t := range s.scheduleTriggers("state") {
		if t.Value == state {
			s.fireScheduleTrigger(t, "state:"+state)
		}
	}
}

// checkConsoleTriggers fires the triggers whose text or pattern is in the line.
func (s *Server) checkConsoleTriggers(line []byte) {
	list := s.scheduleTriggers("console")
	if len(list) == 0 {
		return
	}
	v := stripAnsiRegex.ReplaceAll(line, nil)
	for _, t := range list {
		if t.Regex {
			re := s.triggers().regex(t.Value)
			if re == nil || !re.Match(v) {
				continue
			}
		} else if !bytes.Contains(bytes.ToLower(v), []byte(strings.ToLower(t.Value))) {
			continue
		}
		s.fireScheduleTrigger(t, "console")
	}
}

// regex compiles a pattern once; invalid patterns never match.
func (st *scheduleTriggers) regex(pattern string) *regexp.Regexp {
	st.mu.Lock()
	defer st.mu.Unlock()
	if re, ok := st.regexes[pattern]; ok {
		return re
	}
	re, err := regexp.Compile(pattern)
	if err != nil {
		re = nil
	}
	if len(st.regexes) > 100 {
		st.regexes = map[string]*regexp.Regexp{}
	}
	st.regexes[pattern] = re
	return re
}

// fireScheduleTrigger tells the panel to run the schedule, unless it fired
// within its cooldown.
func (s *Server) fireScheduleTrigger(t ScheduleTrigger, reason string) {
	cooldown := time.Duration(t.Cooldown) * time.Second
	if cooldown < minTriggerCooldown {
		cooldown = minTriggerCooldown
	}

	st := s.triggers()
	st.mu.Lock()
	if last, ok := st.last[t.Schedule]; ok && time.Since(last) < cooldown {
		st.mu.Unlock()
		return
	}
	st.last[t.Schedule] = time.Now()
	st.mu.Unlock()

	go func() {
		ctx, cancel := context.WithTimeout(s.Context(), 30*time.Second)
		defer cancel()
		if err := s.client.TriggerSchedule(ctx, s.ID(), t.Schedule, reason); err != nil {
			s.Log().WithField("schedule", t.Schedule).WithField("error", err).Warn("failed to trigger schedule on panel")
		}
	}()
}

package system

import (
	"slices"
	"testing"
)

func TestRuleOpensPort(t *testing.T) {
	ports := []int{22, 8080, 2022}
	cases := map[string]bool{
		"allow 22/tcp":        true,
		"limit 22/tcp":        true,
		"allow 8000:9000/tcp": true,
		"allow 80,443/tcp":    false,
		"allow from 1.2.3.4 to any port 2022 proto tcp":        true,
		"allow from 1.2.3.4 to any port 3306 proto tcp":        false,
		"allow out to 1.2.3.4 port 22 proto tcp":               false,
		"allow from 22.0.0.1":                                  false,
		"allow in from 10.0.0.0/8 to any port 22,25 proto tcp": true,
	}
	for rule, want := range cases {
		if got := ruleOpensPort(rule, ports); got != want {
			t.Errorf("ruleOpensPort(%q) = %v, want %v", rule, got, want)
		}
	}
}

func TestNewFirewallRuleArgs(t *testing.T) {
	args, err := NewFirewallRule{Action: "allow", Direction: "in", Protocol: "tcp", Port: "25565", Address: "1.2.3.0/24", Comment: "Minecraft"}.Args()
	if err != nil {
		t.Fatal(err)
	}
	want := []string{"allow", "in", "from", "1.2.3.0/24", "to", "any", "port", "25565", "proto", "tcp", "comment", "Minecraft"}
	if !slices.Equal(args, want) {
		t.Errorf("got %v, want %v", args, want)
	}

	args, err = NewFirewallRule{Action: "deny", Direction: "out", Protocol: "any", Address: "9.9.9.9"}.Args()
	if err != nil {
		t.Fatal(err)
	}
	if !slices.Equal(args, []string{"deny", "out", "to", "9.9.9.9"}) {
		t.Errorf("got %v", args)
	}

	invalid := []NewFirewallRule{
		{Action: "accept", Direction: "in", Protocol: "tcp", Port: "22"},
		{Action: "allow", Direction: "in", Protocol: "tcp", Port: "22; reboot"},
		{Action: "allow", Direction: "in", Protocol: "tcp", Port: "70000"},
		{Action: "allow", Direction: "in", Protocol: "any", Port: "1000:2000"},
		{Action: "allow", Direction: "in", Protocol: "tcp", Port: "22", Address: "example.com"},
		{Action: "allow", Direction: "in", Protocol: "tcp", Port: "22", Comment: "it's"},
		{Action: "allow", Direction: "in", Protocol: "tcp", Port: "22", Comment: MysqlRuleComment},
		{Action: "allow", Direction: "in", Protocol: "any"},
		{Action: "limit", Direction: "out", Protocol: "tcp", Port: "22"},
	}
	for _, r := range invalid {
		if _, err := r.Args(); err == nil {
			t.Errorf("expected %+v to be invalid", r)
		}
	}
}

func TestNormalizeAddress(t *testing.T) {
	cases := map[string]string{
		"1.2.3.4":       "1.2.3.4",
		"1.2.3.4/32":    "1.2.3.4",
		"172.18.0.1/16": "172.18.0.0/16",
		"2a01:4f8:0::1": "2a01:4f8::1",
		"2a01:4f8::/64": "2a01:4f8::/64",
	}
	for in, want := range cases {
		if got, ok := NormalizeAddress(in); !ok || got != want {
			t.Errorf("NormalizeAddress(%q) = %q, want %q", in, got, want)
		}
	}
	if _, ok := NormalizeAddress("nope"); ok {
		t.Error("expected invalid address")
	}
}

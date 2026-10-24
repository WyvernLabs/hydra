package motd

import (
	"regexp"
	"strings"
)

// Minecraft text with & or § codes (colors 0-9 a-f, formats k-o, reset r and
// hex colors &#RRGGBB) as a chat component, which every client since 1.16
// shows including the hex colors.

var colorNames = map[byte]string{
	'0': "black", '1': "dark_blue", '2': "dark_green", '3': "dark_aqua",
	'4': "dark_red", '5': "dark_purple", '6': "gold", '7': "gray",
	'8': "dark_gray", '9': "blue", 'a': "green", 'b': "aqua",
	'c': "red", 'd': "light_purple", 'e': "yellow", 'f': "white",
}

var formats = map[byte]string{'k': "obfuscated", 'l': "bold", 'm': "strikethrough", 'n': "underlined", 'o': "italic"}

var hexColor = regexp.MustCompile(`^#[0-9a-fA-F]{6}`)

type style struct {
	color  string
	format map[string]bool
}

// Component turns text with codes into a chat component.
func Component(text string) map[string]any {
	var parts []map[string]any
	current := style{format: map[string]bool{}}
	var buf strings.Builder

	flush := func() {
		if buf.Len() == 0 {
			return
		}
		part := map[string]any{"text": buf.String()}
		if current.color != "" {
			part["color"] = current.color
		}
		// Every part sets every format, so formats of the parts before do not carry over.
		for _, name := range formats {
			part[name] = current.format[name]
		}
		parts = append(parts, part)
		buf.Reset()
	}

	for i := 0; i < len(text); i++ {
		c := text[i]
		marker := c == '&' || c == 0xc2 && i+1 < len(text) && text[i+1] == 0xa7
		if !marker {
			buf.WriteByte(c)
			continue
		}
		next := i + 1
		if c == 0xc2 {
			next = i + 2
		}
		if next >= len(text) {
			buf.WriteByte(c)
			continue
		}
		code := text[next]
		if code >= 'A' && code <= 'Z' {
			code += 'a' - 'A'
		}
		switch {
		case colorNames[code] != "":
			flush()
			current = style{color: colorNames[code], format: map[string]bool{}}
			i = next
		case formats[code] != "":
			flush()
			current.format[formats[code]] = true
			i = next
		case code == 'r':
			flush()
			current = style{format: map[string]bool{}}
			i = next
		case code == '#' && hexColor.MatchString(text[next:]):
			flush()
			current = style{color: strings.ToUpper(text[next : next+7]), format: map[string]bool{}}
			i = next + 6
		default:
			buf.WriteByte(c)
		}
	}
	flush()

	return map[string]any{"text": "", "extra": partsOrEmpty(parts)}
}

func partsOrEmpty(parts []map[string]any) []map[string]any {
	if len(parts) == 0 {
		return []map[string]any{{"text": ""}}
	}
	return parts
}

// Legacy turns & codes into § codes, for texts that are not components
// (the version label). Hex colors are not possible there and are dropped.
func Legacy(text string) string {
	var b strings.Builder
	for i := 0; i < len(text); i++ {
		if text[i] == '&' && i+1 < len(text) {
			code := text[i+1]
			lower := code
			if lower >= 'A' && lower <= 'Z' {
				lower += 'a' - 'A'
			}
			if colorNames[lower] != "" || formats[lower] != "" || lower == 'r' {
				b.WriteString("§")
				b.WriteByte(lower)
				i++
				continue
			}
			if code == '#' && hexColor.MatchString(text[i+1:]) {
				i += 7
				continue
			}
		}
		b.WriteByte(text[i])
	}
	return b.String()
}

// Fill replaces the placeholders of the server.
func Fill(text, name string) string {
	return strings.ReplaceAll(text, "{name}", name)
}

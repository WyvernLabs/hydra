package motd

import (
	"bufio"
	"bytes"
	"encoding/json"
	"io"
	"net"
	"time"

	"emperror.dev/errors"
)

// The server list ping of Minecraft Java Edition:
// https://minecraft.wiki/w/Java_Edition_protocol/Server_List_Ping
//
// A player's client sends a handshake with the state it wants: 1 asks for the
// status (MOTD, picture, players), 2 is a login, which is answered with a
// disconnect showing the same texts.

const maxPacket = 4096

var errPacket = errors.New("motd: invalid packet")

func readVarInt(r io.ByteReader) (int32, error) {
	var value uint32
	for i := 0; i < 5; i++ {
		b, err := r.ReadByte()
		if err != nil {
			return 0, err
		}
		value |= uint32(b&0x7f) << (7 * i)
		if b&0x80 == 0 {
			return int32(value), nil
		}
	}
	return 0, errPacket
}

func appendVarInt(b []byte, v int32) []byte {
	u := uint32(v)
	for {
		if u&^0x7f == 0 {
			return append(b, byte(u))
		}
		b = append(b, byte(u&0x7f|0x80))
		u >>= 7
	}
}

func appendString(b []byte, s string) []byte {
	b = appendVarInt(b, int32(len(s)))
	return append(b, s...)
}

// readPacket returns the id and the data of the next packet.
func readPacket(r *bufio.Reader) (int32, *bytes.Reader, error) {
	length, err := readVarInt(r)
	if err != nil {
		return 0, nil, err
	}
	if length <= 0 || length > maxPacket {
		return 0, nil, errPacket
	}
	data := make([]byte, length)
	if _, err := io.ReadFull(r, data); err != nil {
		return 0, nil, err
	}
	body := bytes.NewReader(data)
	id, err := readVarInt(body)
	return id, body, err
}

func writePacket(w io.Writer, id int32, data []byte) error {
	payload := appendVarInt(nil, id)
	payload = append(payload, data...)
	_, err := w.Write(append(appendVarInt(nil, int32(len(payload))), payload...))
	return err
}

// Answer is what the MOTD shows for a server.
type Answer struct {
	Lines   string
	Label   string
	Favicon string
}

// Serve answers one connection of a client.
func Serve(conn net.Conn, answer Answer) error {
	_ = conn.SetDeadline(time.Now().Add(10 * time.Second))
	r := bufio.NewReader(conn)

	// Clients before 1.7 ping with 0xFE, they are not answered.
	if first, err := r.Peek(1); err != nil || first[0] == 0xfe {
		return err
	}

	id, body, err := readPacket(r)
	if err != nil || id != 0x00 {
		return errPacket
	}
	protocol, err := readVarInt(body)
	if err != nil {
		return err
	}
	addressLength, err := readVarInt(body)
	if err != nil || addressLength < 0 || addressLength > 255+64 {
		return errPacket
	}
	if _, err := body.Seek(int64(addressLength)+2, io.SeekCurrent); err != nil {
		return errPacket
	}
	next, err := readVarInt(body)
	if err != nil {
		return err
	}

	description := Component(answer.Lines)
	if next != 1 {
		// Login or transfer: show the texts as the reason of the disconnect.
		text, _ := json.Marshal(description)
		return writePacket(conn, 0x00, appendString(nil, string(text)))
	}

	// Status request.
	if id, _, err := readPacket(r); err != nil || id != 0x00 {
		return errPacket
	}
	status := map[string]any{
		// A protocol the client does not speak makes it show the label
		// instead of the number of players.
		"version":            map[string]any{"name": Legacy(answer.Label), "protocol": -1},
		"players":            map[string]any{"max": 0, "online": 0},
		"description":        description,
		"enforcesSecureChat": false,
	}
	if answer.Label == "" {
		status["version"] = map[string]any{"name": "Hydra", "protocol": protocol}
	}
	if answer.Favicon != "" {
		status["favicon"] = "data:image/png;base64," + answer.Favicon
	}
	text, err := json.Marshal(status)
	if err != nil {
		return err
	}
	if err := writePacket(conn, 0x00, appendString(nil, string(text))); err != nil {
		return err
	}

	// Ping: the client measures the latency with an echoed number.
	id, body, err = readPacket(r)
	if err != nil {
		return nil
	}
	if id == 0x01 {
		payload := make([]byte, 8)
		if _, err := io.ReadFull(body, payload); err == nil {
			return writePacket(conn, 0x01, payload)
		}
	}
	return nil
}

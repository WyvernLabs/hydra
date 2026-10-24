<?php

namespace Pterodactyl\Services\Hydra\Minecraft;

/**
 * Hydra: the status request of the Minecraft Java server list ("server list
 * ping"), as the multiplayer screen of the game sends it.
 */
class ServerListPing
{
    // Any protocol works for the status; this is 1.21.
    private const PROTOCOL = 767;

    /**
     * @return array{online: int, max: int, protocol: int}|null null when nothing answered
     */
    public function status(string $host, int $port, float $timeout = 2.0): ?array
    {
        $address = str_contains($host, ':') ? "[$host]" : $host;
        $socket = @stream_socket_client("tcp://$address:$port", $errno, $error, $timeout);
        if (!$socket) {
            return null;
        }

        try {
            stream_set_timeout($socket, (int) ceil($timeout));
            $handshake = $this->varInt(0x00) . $this->varInt(self::PROTOCOL) . $this->string($host) . pack('n', $port) . $this->varInt(1);
            fwrite($socket, $this->varInt(strlen($handshake)) . $handshake . $this->varInt(1) . $this->varInt(0x00));

            $length = $this->readVarInt($socket);
            if ($length === null || $length <= 0 || $length > 1048576 || $this->readVarInt($socket) !== 0x00) {
                return null;
            }
            $size = $this->readVarInt($socket);
            if ($size === null || $size <= 0 || $size > 1048576) {
                return null;
            }

            $json = '';
            while (strlen($json) < $size) {
                $chunk = fread($socket, $size - strlen($json));
                if ($chunk === false || $chunk === '') {
                    return null;
                }
                $json .= $chunk;
            }

            $status = json_decode($json, true);
            if (!is_array($status) || !isset($status['players']['online'])) {
                return null;
            }

            return [
                'online' => max(0, (int) $status['players']['online']),
                'max' => max(0, (int) ($status['players']['max'] ?? 0)),
                'protocol' => (int) ($status['version']['protocol'] ?? 0),
            ];
        } finally {
            fclose($socket);
        }
    }

    private function varInt(int $value): string
    {
        $value &= 0xFFFFFFFF;
        $bytes = '';
        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            $bytes .= chr($value ? $byte | 0x80 : $byte);
        } while ($value);

        return $bytes;
    }

    private function string(string $value): string
    {
        return $this->varInt(strlen($value)) . $value;
    }

    /**
     * @param resource $socket
     */
    private function readVarInt($socket): ?int
    {
        $value = 0;
        for ($i = 0; $i < 5; ++$i) {
            $byte = fread($socket, 1);
            if ($byte === false || $byte === '') {
                return null;
            }
            $byte = ord($byte);
            $value |= ($byte & 0x7F) << (7 * $i);
            if (!($byte & 0x80)) {
                return $value;
            }
        }

        return null;
    }
}

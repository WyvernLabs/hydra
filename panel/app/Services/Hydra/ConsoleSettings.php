<?php

namespace Pterodactyl\Services\Hydra;

use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Hydra: texts of the server console set under Design > Console.
 *
 * - "offline_message": shown in the console while the server is offline, one
 *   per panel language (JSON), the panel language is used as fallback. Spaces
 *   are kept for ASCII art; {name}, {id}, {address} and {node} are filled in
 *   and Minecraft color codes (&a, &l, …) work, both in the frontend.
 * - "prefix": put in front of messages of the panel, e.g. "Server marked as
 *   running".
 * - "daemon_prefix": replaces "[… Daemon]:" in front of messages of Wings;
 *   empty keeps the one Wings sends.
 *
 * Prefixes are one text for every language, like the output of the games.
 */
class ConsoleSettings
{
    public const DEFAULT_PREFIX = 'container@hydra~';

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    /**
     * @return array<string, string> by language code
     */
    public function offlineMessages(): array
    {
        $messages = config('hydra.console.offline_message');
        $messages = is_array($messages) ? $messages : json_decode((string) $messages, true);

        return is_array($messages) ? array_filter($messages, fn ($text) => is_string($text) && trim($text) !== '') : [];
    }

    public function offlineMessage(?string $locale): ?string
    {
        $messages = $this->offlineMessages();

        return $messages[$locale] ?? $messages[config('app.locale')] ?? (reset($messages) ?: null);
    }

    public function prefix(): string
    {
        return (string) (config('hydra.console.prefix') ?: self::DEFAULT_PREFIX);
    }

    public function daemonPrefix(): ?string
    {
        return config('hydra.console.daemon_prefix') ?: null;
    }

    /**
     * @param array<string, ?string> $messages by language code
     */
    public function save(array $messages, ?string $prefix, ?string $daemonPrefix): void
    {
        // Leading spaces belong to ASCII art, only empty lines at the end go.
        $messages = array_filter(
            array_map(fn ($text) => rtrim(str_replace(["\r\n", "\r"], "\n", (string) $text)), $messages),
            fn ($text) => trim($text) !== ''
        );

        $this->set('offline_message', empty($messages) ? null : json_encode($messages, JSON_UNESCAPED_UNICODE));
        $this->set('prefix', trim((string) $prefix) ?: null);
        $this->set('daemon_prefix', trim((string) $daemonPrefix) ?: null);
    }

    /**
     * Data for the React frontend (window.SiteConfiguration.console).
     */
    public function toFrontend(?string $locale): array
    {
        return [
            'offlineMessage' => $this->offlineMessage($locale),
            'prefix' => $this->prefix(),
            'daemonPrefix' => $this->daemonPrefix(),
        ];
    }

    private function set(string $key, ?string $value): void
    {
        if (is_null($value)) {
            $this->settings->forget("settings::hydra:console:$key");
        } else {
            $this->settings->set("settings::hydra:console:$key", $value);
        }

        config()->set("hydra.console.$key", $value);
    }
}

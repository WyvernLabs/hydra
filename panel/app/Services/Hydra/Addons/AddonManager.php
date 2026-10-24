<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Hydra: every addon of the panel (Design > Addons).
 */
class AddonManager
{
    /**
     * Installed addons in the order they are shown.
     *
     * @var class-string<Addon>[]
     */
    public const ADDONS = [
        TurnstileAddon::class,
        RegistrationAddon::class,
        PhpMyAdminAddon::class,
        DashboardAddon::class,
        AutoDeleteAddon::class,
        LockdownAddon::class,
        TicketsAddon::class,
        CpuAddon::class,
        McLogsAddon::class,
        StartupCommandAddon::class,
        PropertiesAddon::class,
        SftpBackupsAddon::class,
        SubdomainsAddon::class,
        ProxyAddon::class,
        GamesAddon::class,
        TrashAddon::class,
        EnvAddon::class,
        VersionsAddon::class,
    ];

    public function __construct(private SettingsRepositoryInterface $settings, private Encrypter $encrypter)
    {
    }

    /**
     * @return Addon[] keyed by addon key
     */
    public static function all(): array
    {
        $list = [];
        foreach (self::ADDONS as $class) {
            $addon = app($class);
            $list[$addon->key()] = $addon;
        }

        return $list;
    }

    public static function find(string $key): ?Addon
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Setting keys for the SettingsServiceProvider, e.g. "hydra:addons:turnstile:enabled".
     *
     * @return array{0: string[], 1: string[]} all keys and the encrypted ones
     */
    public static function settingKeys(): array
    {
        $keys = [];
        $encrypted = [];
        foreach (self::ADDONS as $class) {
            /** @var Addon $addon */
            $addon = new $class();
            foreach ($addon->settings() as $setting) {
                $keys[] = "hydra:addons:{$addon->key()}:$setting";
            }
            foreach ($addon->encrypted() as $setting) {
                $encrypted[] = "hydra:addons:{$addon->key()}:$setting";
            }
        }

        return [$keys, $encrypted];
    }

    /**
     * Stores the settings of an addon and puts them into the running config.
     * Encrypted settings left empty keep their old value.
     */
    public function save(Addon $addon, array $values): void
    {
        $values = $addon->prepare($values);

        foreach ($addon->booleans() as $setting) {
            $values[$setting] = !empty($values[$setting]);
        }

        foreach ($values as $setting => $value) {
            if (!in_array($setting, $addon->settings(), true) || $setting === 'enabled') {
                continue;
            }

            if (in_array($setting, $addon->encrypted(), true)) {
                if (blank($value)) {
                    continue;
                }
                $this->store($addon, $setting, $this->encrypter->encrypt($value), $value);
                continue;
            }

            $this->store($addon, $setting, $this->stringify($value), $value);
        }
    }

    public function setEnabled(Addon $addon, bool $enabled): void
    {
        $this->store($addon, 'enabled', $enabled ? 'true' : 'false', $enabled);
    }

    private function store(Addon $addon, string $setting, string $stored, mixed $value): void
    {
        $this->settings->set("settings::hydra:addons:{$addon->key()}:$setting", $stored);
        config()->set("hydra.addons.{$addon->key()}.$setting", $value);
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            // Lists, e.g. the categories of the tickets addon.
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            is_null($value) || $value === '' => '(empty)',
            default => (string) $value,
        };
    }
}

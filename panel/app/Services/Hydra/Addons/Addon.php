<?php

namespace Pterodactyl\Services\Hydra\Addons;

/**
 * Hydra: a feature that can be switched on and configured in the admin area
 * under Design > Addons.
 *
 * Every setting of an addon is stored in the settings table as
 * "settings::hydra:addons:<key>:<setting>" and loaded into the config as
 * "hydra.addons.<key>.<setting>" by the SettingsServiceProvider. Defaults
 * live in config/hydra.php. Names and descriptions come from
 * lang/<locale>/admin/addons.php.
 */
abstract class Addon
{
    /**
     * Unique key, used in URLs, settings and translations.
     */
    abstract public function key(): string;

    /**
     * Name of an icon of the <x-hydra.icon> component.
     */
    abstract public function icon(): string;

    /**
     * Validation rules of the settings form, keyed by setting name. The
     * "enabled" switch is handled by the addon manager.
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Cleans up the validated values of the settings form before they are
     * stored, e.g. to drop empty entries of a list.
     */
    public function prepare(array $values): array
    {
        return $values;
    }

    /**
     * Runs after the settings and the switch were saved, e.g. to send them to
     * the nodes.
     */
    public function saved(): void
    {
    }

    /**
     * Settings that are stored encrypted, e.g. secret keys. They are only
     * replaced when the admin enters a new value.
     */
    public function encrypted(): array
    {
        return [];
    }

    /**
     * Settings that are checkboxes, so a missing value means "false".
     */
    public function booleans(): array
    {
        return [];
    }

    /**
     * Returns why the addon cannot be enabled with its current settings, or
     * null when nothing is missing.
     */
    public function missing(): ?string
    {
        return null;
    }

    /**
     * Short facts shown on the box of the addon, e.g. which forms are protected.
     *
     * @return string[]
     */
    public function summary(): array
    {
        return [];
    }

    public function enabled(): bool
    {
        return (bool) $this->get('enabled', false);
    }

    public function get(string $setting, mixed $default = null): mixed
    {
        return config("hydra.addons.{$this->key()}.$setting", $default);
    }

    public function name(): string
    {
        return trans("admin/addons.{$this->key()}.name");
    }

    public function description(): string
    {
        return trans("admin/addons.{$this->key()}.description");
    }

    /**
     * Blade view with the settings form of the addon.
     */
    public function view(): string
    {
        return "admin.design.addons.settings.{$this->key()}";
    }

    /**
     * All setting names, including "enabled".
     *
     * @return string[]
     */
    public function settings(): array
    {
        // Rules for the entries of a list ("categories.*") are no settings of their own.
        $keys = array_filter(array_keys($this->rules()), fn (string $key) => !str_contains($key, '.'));

        return array_values(array_unique(array_merge(['enabled'], $keys, $this->booleans())));
    }
}

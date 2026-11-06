<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Models\HydraDiscordLink;

/**
 * Hydra: connects a custom Discord bot. Users link their Discord account with a
 * code the bot sends them; the panel then gives them roles on the Discord server
 * and tells them about ticket answers. Only the REST API of Discord is used, so
 * no extra process has to run.
 */
class DiscordAddon extends Addon
{
    // Discord IDs are numbers of 17 to 20 digits.
    public const SNOWFLAKE = '/^\d{17,20}$/';

    // Key of the root admins in the list of panel roles.
    public const ROOT_ROLE = 'root';

    public function key(): string
    {
        return 'discord';
    }

    public function icon(): string
    {
        return 'discord';
    }

    public function rules(): array
    {
        $role = ['nullable', 'string', 'regex:' . self::SNOWFLAKE];

        return [
            'bot_token' => 'nullable|string|max:191',
            'guild_id' => $role,
            'role_verified' => $role,
            'role_server' => $role,
            'role_map' => 'nullable|array',
            'role_map.*' => $role,
            'node_map' => 'nullable|array',
            'node_map.*' => $role,
        ];
    }

    public function encrypted(): array
    {
        return ['bot_token'];
    }

    public function booleans(): array
    {
        return ['ticket_notifications'];
    }

    public function prepare(array $values): array
    {
        foreach (['role_map', 'node_map'] as $map) {
            $values[$map] = array_filter($values[$map] ?? []);
        }

        return $values;
    }

    public function missing(): ?string
    {
        return blank($this->get('bot_token')) || blank($this->get('guild_id')) ? trans('admin/addons.discord.missing') : null;
    }

    public function summary(): array
    {
        return [trans('admin/addons.discord.summary', ['count' => HydraDiscordLink::query()->count()])];
    }

    public function token(): string
    {
        return (string) $this->get('bot_token');
    }

    public function guild(): string
    {
        return (string) $this->get('guild_id');
    }

    /**
     * Discord role per panel role id, "root" for root admins.
     *
     * @return array<string, string>
     */
    public function roleMap(): array
    {
        return $this->map('role_map');
    }

    /**
     * Discord role per node id, for users with a server on that node.
     *
     * @return array<string, string>
     */
    public function nodeMap(): array
    {
        return $this->map('node_map');
    }

    public function notifiesTickets(): bool
    {
        return (bool) $this->get('ticket_notifications', true);
    }

    /**
     * Data for the React frontend (window.SiteConfiguration.discord).
     */
    public function toArray(): array
    {
        return ['enabled' => $this->enabled(), 'tickets' => $this->enabled() && $this->notifiesTickets()];
    }

    private function map(string $setting): array
    {
        $value = $this->get($setting, []);
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? array_filter($value) : [];
    }
}

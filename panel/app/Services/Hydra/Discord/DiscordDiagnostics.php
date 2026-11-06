<?php

namespace Pterodactyl\Services\Hydra\Discord;

use Pterodactyl\Services\Hydra\Addons\DiscordAddon;

/**
 * Hydra: checks the settings of the bot for the "test connection" button, including
 * the things Discord only complains about later: missing rights and role order.
 */
class DiscordDiagnostics
{
    private const ADMINISTRATOR = 1 << 3;
    private const MANAGE_ROLES = 1 << 28;

    public function __construct(private DiscordAddon $addon, private DiscordClient $client)
    {
    }

    /**
     * @return array{ok: string[], problems: string[]}
     */
    public function run(): array
    {
        $ok = [];
        $problems = [];

        try {
            $bot = $this->client->me();
            $ok[] = trans('admin/addons.discord.test.bot', ['name' => $bot['username'] ?? '?']);
        } catch (DiscordApiException $exception) {
            return ['ok' => [], 'problems' => [trans('admin/addons.discord.test.bot_failed', ['error' => $exception->getMessage()])]];
        }

        $guild = $this->addon->guild();
        try {
            $ok[] = trans('admin/addons.discord.test.guild', ['name' => $this->client->guild($guild)['name'] ?? '?']);
            $roles = collect($this->client->roles($guild))->keyBy('id');
            $member = $this->client->member($guild, (string) $bot['id']);
        } catch (DiscordApiException $exception) {
            return ['ok' => $ok, 'problems' => [trans('admin/addons.discord.test.guild_failed', ['error' => $exception->getMessage()])]];
        }

        $own = collect($member['roles'] ?? [])->map(fn ($id) => $roles->get($id))->filter();
        $permissions = $own->reduce(fn (int $sum, array $role) => $sum | (int) $role['permissions'], 0);
        if (($permissions & (self::ADMINISTRATOR | self::MANAGE_ROLES)) === 0) {
            $problems[] = trans('admin/addons.discord.test.no_manage_roles');
        }

        $highest = (int) $own->max('position');
        foreach ($this->configured() as $id) {
            $role = $roles->get($id);
            if (is_null($role)) {
                $problems[] = trans('admin/addons.discord.test.role_unknown', ['id' => $id]);
            } elseif ((int) $role['position'] >= $highest) {
                $problems[] = trans('admin/addons.discord.test.role_too_high', ['name' => $role['name']]);
            }
        }

        if (empty($problems)) {
            $ok[] = trans('admin/addons.discord.test.roles');
        }

        return ['ok' => $ok, 'problems' => $problems];
    }

    /**
     * @return string[]
     */
    private function configured(): array
    {
        return array_values(array_unique(array_filter(array_merge(
            [(string) $this->addon->get('role_verified'), (string) $this->addon->get('role_server')],
            array_values($this->addon->roleMap()),
            array_values($this->addon->nodeMap()),
        ))));
    }
}

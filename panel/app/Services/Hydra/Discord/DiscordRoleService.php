<?php

namespace Pterodactyl\Services\Hydra\Discord;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\HydraDiscordLink;
use Pterodactyl\Services\Hydra\Addons\DiscordAddon;

/**
 * Hydra: gives linked users their roles on the Discord server: one for being
 * linked, one for having a server, one per panel role and one per node they have
 * a server on. Other roles on Discord are never touched.
 */
class DiscordRoleService
{
    public function __construct(private DiscordAddon $addon, private DiscordClient $client)
    {
    }

    /**
     * The Discord roles the user should have.
     *
     * @return string[]
     */
    public function wanted(User $user): array
    {
        $roles = [(string) $this->addon->get('role_verified')];

        $nodes = Server::query()->where('owner_id', $user->id)->distinct()->pluck('node_id');
        if ($nodes->isNotEmpty()) {
            $roles[] = (string) $this->addon->get('role_server');
        }
        foreach ($nodes as $node) {
            $roles[] = $this->addon->nodeMap()[$node] ?? '';
        }

        $map = $this->addon->roleMap();
        foreach ($user->adminRoles()->pluck('admin_roles.id') as $role) {
            $roles[] = $map[$role] ?? '';
        }
        if ($user->root_admin) {
            $roles[] = $map[DiscordAddon::ROOT_ROLE] ?? '';
        }

        return array_values(array_unique(array_filter($roles)));
    }

    /**
     * Every role this addon hands out at the moment.
     *
     * @return string[]
     */
    private function managed(): array
    {
        return array_values(array_unique(array_filter(array_merge(
            [(string) $this->addon->get('role_verified'), (string) $this->addon->get('role_server')],
            array_values($this->addon->roleMap()),
            array_values($this->addon->nodeMap()),
        ))));
    }

    /**
     * Brings the roles of a linked user in line. Users who have not joined the
     * Discord server are skipped, they get their roles at a later sync.
     *
     * @throws DiscordApiException
     */
    public function sync(HydraDiscordLink $link): void
    {
        $guild = $this->addon->guild();
        $member = $this->client->member($guild, $link->discord_id);
        if (is_null($member)) {
            return;
        }

        $current = array_map('strval', $member['roles'] ?? []);
        $wanted = $this->wanted($link->user);
        // Also roles it gave earlier, which may no longer be in the settings.
        $ours = array_merge($this->managed(), array_map('strval', $link->roles ?? []));

        foreach (array_diff($wanted, $current) as $role) {
            $this->client->addRole($guild, $link->discord_id, $role);
        }
        foreach (array_diff(array_intersect($current, $ours), $wanted) as $role) {
            $this->client->removeRole($guild, $link->discord_id, $role);
        }

        $link->update(['roles' => $wanted]);
    }

    /**
     * Takes the roles away when the accounts are no longer linked.
     *
     * @throws DiscordApiException
     */
    public function removeAll(HydraDiscordLink $link): void
    {
        $guild = $this->addon->guild();
        $member = $this->client->member($guild, $link->discord_id);
        if (is_null($member)) {
            return;
        }

        $ours = array_merge($this->managed(), array_map('strval', $link->roles ?? []));
        foreach (array_intersect(array_map('strval', $member['roles'] ?? []), $ours) as $role) {
            $this->client->removeRole($guild, $link->discord_id, $role);
        }
    }
}

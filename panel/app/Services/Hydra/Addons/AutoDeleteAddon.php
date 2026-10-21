<?php

namespace Pterodactyl\Services\Hydra\Addons;

/**
 * Hydra: deletes servers that have been suspended for a while and accounts
 * that have been banned permanently for a while. Runs every hour.
 */
class AutoDeleteAddon extends Addon
{
    public function key(): string
    {
        return 'autodelete';
    }

    public function icon(): string
    {
        return 'trash';
    }

    public function rules(): array
    {
        return [
            'server_days' => 'required|integer|between:1,3650',
            'user_days' => 'required|integer|between:1,3650',
        ];
    }

    public function booleans(): array
    {
        return ['servers', 'users'];
    }

    public function missing(): ?string
    {
        return $this->get('servers', true) || $this->get('users', true) ? null : trans('admin/addons.autodelete.nothing');
    }

    public function summary(): array
    {
        return [
            $this->get('servers', true)
                ? trans('admin/addons.autodelete.summary_servers', ['days' => $this->serverDays()])
                : trans('admin/addons.autodelete.summary_servers_off'),
            $this->get('users', true)
                ? trans('admin/addons.autodelete.summary_users', ['days' => $this->userDays()])
                : trans('admin/addons.autodelete.summary_users_off'),
        ];
    }

    public function deletesSuspendedServers(): bool
    {
        return $this->enabled() && (bool) $this->get('servers', true);
    }

    public function deletesBannedUsers(): bool
    {
        return $this->enabled() && (bool) $this->get('users', true);
    }

    public function serverDays(): int
    {
        return max(1, (int) $this->get('server_days', 30));
    }

    public function userDays(): int
    {
        return max(1, (int) $this->get('user_days', 14));
    }
}

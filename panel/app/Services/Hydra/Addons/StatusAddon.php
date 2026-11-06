<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Models\HydraStatusService;

/**
 * Hydra: a public status page under /status with the nodes and own applications
 * and their availability per hour of the last 7 days. Checked every minute by
 * the command hydra:status:check.
 */
class StatusAddon extends Addon
{
    public function key(): string
    {
        return 'status';
    }

    public function icon(): string
    {
        return 'activity';
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:64',
            'description' => 'nullable|string|max:500',
        ];
    }

    public function booleans(): array
    {
        return ['private_nodes'];
    }

    public function summary(): array
    {
        return [trans('admin/addons.status.summary', ['count' => HydraStatusService::query()->where('enabled', true)->count()])];
    }

    /**
     * Whether nodes that are not public are listed too.
     */
    public function showsPrivateNodes(): bool
    {
        return (bool) $this->get('private_nodes', false);
    }
}

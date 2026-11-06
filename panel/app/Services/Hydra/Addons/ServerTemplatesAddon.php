<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Models\HydraServerTemplate;

/**
 * Hydra: admins save named settings for new servers and create a server from
 * them by entering only its name and owner. See ServerTemplateService.
 */
class ServerTemplatesAddon extends Addon
{
    public function key(): string
    {
        return 'server_templates';
    }

    public function icon(): string
    {
        return 'server';
    }

    public function summary(): array
    {
        return [trans('admin/addons.server_templates.summary', ['count' => HydraServerTemplate::query()->count()])];
    }
}

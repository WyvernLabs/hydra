<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Services\Hydra\PhpMyAdminService;

/**
 * Hydra: phpMyAdmin on the panel server. Users with the permission open their
 * databases in it from the client area and are logged in right away.
 */
class PhpMyAdminAddon extends Addon
{
    public function key(): string
    {
        return 'phpmyadmin';
    }

    public function icon(): string
    {
        return 'database';
    }

    public function missing(): ?string
    {
        return app(PhpMyAdminService::class)->isInstalled() ? null : trans('admin/addons.phpmyadmin.not_installed');
    }

    public function summary(): array
    {
        $status = app(PhpMyAdminService::class)->status();

        return [
            !empty($status['installed'])
                ? trans('admin/addons.phpmyadmin.summary_installed', ['version' => $status['version'] ?? '?'])
                : trans('admin/addons.phpmyadmin.summary_not_installed'),
        ];
    }
}

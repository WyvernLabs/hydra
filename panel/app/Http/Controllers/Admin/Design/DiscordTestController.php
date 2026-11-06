<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\Discord\DiscordDiagnostics;

/**
 * Hydra: tests the bot of the addon "discord" with the saved settings.
 */
class DiscordTestController extends Controller
{
    public function __invoke(AlertsMessageBag $alert, DiscordDiagnostics $diagnostics): RedirectResponse
    {
        $result = $diagnostics->run();

        foreach ($result['ok'] as $message) {
            $alert->success($message)->flash();
        }
        foreach ($result['problems'] as $message) {
            $alert->danger($message)->flash();
        }

        return redirect()->route('admin.design.addons.view', 'discord');
    }
}

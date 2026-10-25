<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\Response;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\Nodes\NodeInstallService;

/**
 * Hydra: hands out the wings installer of a one-time install command
 * (bash <(curl -fsSL https://panel/install/node/<code>)).
 */
class NodeInstallScriptController extends Controller
{
    public function __invoke(NodeInstallService $install, string $code): Response
    {
        $script = preg_match('/^[A-Za-z0-9]{40}$/', $code) ? $install->scriptForCode($code) : null;

        // Always a script, so bash shows a message instead of failing silently.
        $script ??= "#!/usr/bin/env bash\necho " . escapeshellarg(__('admin/nodes.install.command.expired')) . " >&2\nexit 1\n";

        return response($script, 200, [
            'Content-Type' => 'text/x-shellscript; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}

<?php

namespace Pterodactyl\Exceptions\Service\Hydra;

use Pterodactyl\Exceptions\PterodactylException;

/**
 * Hydra: Wings could not change the firewall or set up the database. The code
 * is translated in lang/<locale>/admin/nodes.php (errors), the detail is the
 * output of the command that failed on the node.
 */
class NodeFirewallException extends PterodactylException
{
    public function __construct(public readonly string $errorCode, public readonly string $detail = '')
    {
        parent::__construct(self::translate($errorCode));
    }

    public static function translate(string $code): string
    {
        $key = 'admin/nodes.errors.' . $code;

        return trans()->has($key) ? __($key) : __('admin/nodes.errors.command_failed');
    }
}

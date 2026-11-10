<?php

namespace Pterodactyl\Services\Hydra\Addons;

/**
 * Hydra: support area in the admin area (Community Management > Game
 * support). Staff open a server by its UUID in a tab with console, usage and
 * power buttons, without opening the server itself.
 */
class GameSupportAddon extends Addon
{
    public function key(): string
    {
        return 'gamesupport';
    }

    public function icon(): string
    {
        return 'headset';
    }
}

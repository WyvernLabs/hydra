<?php

namespace Pterodactyl\Tests\Fixtures;

use Pterodactyl\Models\Nest;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Services\Eggs\Sharing\EggImporterService;

/**
 * Most integration tests are built around the BungeeCord egg of Pterodactyl, which
 * Hydra no longer ships. It is imported into the Minecraft nest for the tests only.
 */
class BungeecordEggSeeder extends Seeder
{
    public function run(EggImporterService $importer): void
    {
        $nest = Nest::query()->where('author', 'support@pterodactyl.io')->where('name', 'Minecraft')->firstOrFail();
        if ($nest->eggs()->where('author', 'support@pterodactyl.io')->where('name', 'Bungeecord')->exists()) {
            return;
        }

        $path = __DIR__ . '/eggs/egg-bungeecord.json';
        $importer->handle(new UploadedFile($path, basename($path), 'application/json'), $nest->id);
    }
}

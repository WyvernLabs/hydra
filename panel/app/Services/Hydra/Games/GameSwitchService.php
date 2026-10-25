<?php

namespace Pterodactyl\Services\Hydra\Games;

use Ramsey\Uuid\Uuid;
use Carbon\CarbonImmutable;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerVariable;
use Pterodactyl\Models\HydraGameProfile;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Addons\GamesAddon;
use Pterodactyl\Services\Servers\ReinstallServerService;
use Pterodactyl\Services\Hydra\ServerStopper;
use Pterodactyl\Services\Hydra\Network\PortAssignmentService;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Repositories\Wings\DaemonProfileRepository;

/**
 * Hydra: switches the game of a server (addon "games"). The server is
 * stopped, its files are kept as a profile or removed, and then either a new
 * egg is installed or a profile is loaded back.
 */
class GameSwitchService
{
    public function __construct(
        private ConnectionInterface $connection,
        private GamesAddon $addon,
        private ServerStopper $stopper,
        private DaemonServerRepository $daemon,
        private DaemonProfileRepository $profiles,
        private ReinstallServerService $reinstall,
        private PortAssignmentService $ports,
    ) {
    }

    /**
     * Installs another egg on the server.
     *
     * @throws \Throwable
     */
    public function switchTo(Server $server, Egg $egg, bool $keep, ?string $name): void
    {
        if (!$this->addon->offers($egg)) {
            throw new DisplayException(__('server/games.not_offered'));
        }
        if ($egg->id === $server->egg_id) {
            throw new DisplayException(__('server/games.already_installed'));
        }

        $this->clear($server, $keep, $name);

        $this->connection->transaction(function () use ($server, $egg) {
            $server->forceFill([
                'egg_id' => $egg->id,
                'nest_id' => $egg->nest_id,
                'startup' => (string) $egg->startup,
                'image' => (string) (array_values($egg->docker_images ?? [])[0] ?? $server->image),
                'skip_scripts' => false,
                'hydra_startup_done' => null,
            ])->save();

            ServerVariable::query()->where('server_id', $server->id)->delete();
            foreach ($egg->variables as $variable) {
                ServerVariable::query()->create([
                    'server_id' => $server->id,
                    'variable_id' => $variable->id,
                    'variable_value' => (string) ($variable->default_value ?? ''),
                ]);
            }

            $this->ports->assign($server->refresh());
        });

        // Reinstalling loads the new configuration on the node and runs the install script.
        $this->reinstall->handle($server->refresh());
    }

    /**
     * Loads a profile back onto the server, without installing anything.
     *
     * @throws \Throwable
     */
    public function load(Server $server, HydraGameProfile $profile, bool $keep, ?string $name): void
    {
        if ($profile->server_id !== $server->id) {
            throw new DisplayException(__('server/games.not_found'));
        }
        $egg = $profile->egg;
        if (!$egg) {
            throw new DisplayException(__('server/games.egg_missing'));
        }

        $this->clear($server, $keep, $name);
        $this->profiles->setServer($server)->load($profile->uuid);

        $this->connection->transaction(function () use ($server, $egg, $profile) {
            $server->forceFill([
                'egg_id' => $egg->id,
                'nest_id' => $egg->nest_id,
                'startup' => $profile->startup,
                'image' => $profile->image,
                'hydra_startup_done' => $profile->startup_done,
            ])->save();

            ServerVariable::query()->where('server_id', $server->id)->delete();
            foreach ($egg->variables as $variable) {
                ServerVariable::query()->create([
                    'server_id' => $server->id,
                    'variable_id' => $variable->id,
                    'variable_value' => (string) ($profile->variables[$variable->env_variable] ?? $variable->default_value ?? ''),
                ]);
            }

            $this->ports->assign($server->refresh());

            $profile->delete();
        });

        $this->daemon->setServer($server->refresh())->sync();
    }

    /**
     * Removes a profile and its files.
     *
     * @throws \Throwable
     */
    public function delete(HydraGameProfile $profile): void
    {
        $this->profiles->setServer($profile->server)->delete($profile->uuid);
        $profile->delete();
    }

    /**
     * Stops the server and keeps its files as a profile, or removes them.
     * Also used by the importer before it copies another server.
     *
     * @throws \Throwable
     */
    public function clear(Server $server, bool $keep, ?string $name): void
    {
        if (!is_null($server->status)) {
            throw new DisplayException(__('server/games.busy'));
        }
        if ($keep && $server->gameProfiles()->count() >= $this->addon->maxProfiles()) {
            throw new DisplayException(__('server/games.profile_limit', ['limit' => $this->addon->maxProfiles()]));
        }

        $this->stopper->stop($server, __('server/games.stop_failed'));

        if (!$keep) {
            $this->profiles->setServer($server)->wipe();

            return;
        }

        $uuid = Uuid::uuid4()->toString();
        $size = $this->profiles->setServer($server)->save($uuid);
        $server->loadMissing('egg', 'variables');

        HydraGameProfile::query()->create([
            'server_id' => $server->id,
            'uuid' => $uuid,
            'name' => trim((string) $name) ?: sprintf('%s (%s)', $server->egg->name, CarbonImmutable::now()->format('Y-m-d')),
            'egg_id' => $server->egg_id,
            'startup' => (string) $server->startup,
            'image' => (string) $server->image,
            'variables' => $server->variables->mapWithKeys(fn ($v) => [$v->env_variable => (string) ($v->server_value ?? $v->default_value ?? '')])->all(),
            'startup_done' => $server->hydra_startup_done,
            'size' => $size,
        ]);
    }
}

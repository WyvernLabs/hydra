<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Eggs\EggConfigurationService;
use Pterodactyl\Services\Hydra\Addons\StartupCommandAddon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Services\Servers\StartupCommandService;
use Pterodactyl\Repositories\Eloquent\ServerVariableRepository;
use Pterodactyl\Transformers\Api\Client\EggVariableTransformer;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\GetStartupRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\UpdateStartupDoneRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\UpdateStartupCommandRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\UpdateStartupVariableRequest;

class StartupController extends ClientApiController
{
    /**
     * StartupController constructor.
     */
    public function __construct(
        private StartupCommandService $startupCommandService,
        private ServerVariableRepository $repository,
    ) {
        parent::__construct();
    }

    /**
     * Returns the startup information for the server including all the variables.
     */
    public function index(GetStartupRequest $request, Server $server): array
    {
        $startup = $this->startupCommandService->handle($server);

        return $this->fractal->collection(
            $server->variables()->where('user_viewable', true)->get()
        )
            ->transformWith($this->getTransformer(EggVariableTransformer::class))
            ->addMeta([
                'startup_command' => $startup,
                'docker_images' => $server->egg->docker_images,
                'raw_startup_command' => $server->startup,
                // Hydra: messages that mark the server as running.
                'startup_done' => $server->startupDone(),
                'egg_startup_done' => EggConfigurationService::eggDone($server),
                // Hydra: the command of the egg and whether users may change it (addon).
                'egg_startup_command' => $server->egg->startup,
                'startup_editable' => app(StartupCommandAddon::class)->enabled(),
            ])
            ->toArray();
    }

    /**
     * Hydra: sets the console messages that mark the server as running, or
     * goes back to those of the egg. Wings picks them up on the next start.
     */
    public function done(UpdateStartupDoneRequest $request, Server $server): JsonResponse
    {
        $original = $server->startupDone();
        $done = array_values(array_filter(array_map('trim', $request->input('done') ?? []), fn ($line) => $line !== ''));

        $server->forceFill(['hydra_startup_done' => empty($done) ? null : implode("\n", $done)])->saveOrFail();

        if ($original !== $server->startupDone()) {
            Activity::event('server:startup.done')
                ->property(['old' => implode(' | ', $original ?? EggConfigurationService::eggDone($server)), 'new' => implode(' | ', $server->startupDone() ?? EggConfigurationService::eggDone($server))])
                ->log();
        }

        return new JsonResponse(['startup_done' => $server->startupDone()]);
    }

    /**
     * Hydra: changes the startup command of the server, or goes back to the
     * one of the egg (addon "Startup Command"). Wings uses it from the next start.
     */
    public function command(UpdateStartupCommandRequest $request, Server $server): array
    {
        if (!app(StartupCommandAddon::class)->enabled()) {
            throw new NotFoundHttpException();
        }

        $original = $server->startup;
        $startup = trim((string) $request->input('startup')) ?: $server->egg->startup;

        if ($startup !== $original) {
            $server->forceFill(['startup' => $startup])->saveOrFail();

            Activity::event('server:startup.command')->property(['old' => $original, 'new' => $startup])->log();
        }

        return [
            'startup_command' => $this->startupCommandService->handle($server->refresh()),
            'raw_startup_command' => $server->startup,
        ];
    }

    /**
     * Updates a single variable for a server.
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(UpdateStartupVariableRequest $request, Server $server): array
    {
        $variable = $server->variables()->where('env_variable', $request->input('key'))->first();

        if (is_null($variable) || !$variable->user_viewable) {
            throw new BadRequestHttpException('The environment variable you are trying to edit does not exist.');
        } elseif (!$variable->user_editable) {
            throw new BadRequestHttpException('The environment variable you are trying to edit is read-only.');
        }

        $original = $variable->server_value;

        // Revalidate the variable value using the egg variable specific validation rules for it.
        $this->validate($request, ['value' => $variable->rules]);

        $this->repository->updateOrCreate([
            'server_id' => $server->id,
            'variable_id' => $variable->id,
        ], [
            'variable_value' => $request->input('value') ?? '',
        ]);

        $variable = $variable->refresh();
        $variable->server_value = $request->input('value');

        $startup = $this->startupCommandService->handle($server);

        if ($original !== $request->input('value')) {
            Activity::event('server:startup.edit')
                ->subject($variable)
                ->property([
                    'variable' => $variable->env_variable,
                    'old' => $original,
                    'new' => $request->input('value') ?? '',
                ])
                ->log();
        }

        return $this->fractal->item($variable)
            ->transformWith($this->getTransformer(EggVariableTransformer::class))
            ->addMeta([
                'startup_command' => $startup,
                'raw_startup_command' => $server->startup,
            ])
            ->toArray();
    }
}

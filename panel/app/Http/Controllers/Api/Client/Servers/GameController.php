<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Egg;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\HydraGameProfile;
use Pterodactyl\Services\Hydra\BackgroundService;
use Pterodactyl\Services\Hydra\Addons\GamesAddon;
use Illuminate\Auth\Access\AuthorizationException;
use Pterodactyl\Services\Hydra\Games\GameSwitchService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;

/**
 * Hydra: game library (addon "games"). Lists the offered eggs by nest and the
 * profiles of the server, switches the game and loads or deletes profiles.
 */
class GameController extends ClientApiController
{
    public function __construct(
        private GamesAddon $addon,
        private GameSwitchService $service,
        private BackgroundService $backgrounds,
    ) {
        parent::__construct();
    }

    public function index(Request $request, Server $server): array
    {
        $this->check($request, $server);

        $nests = $this->addon->eggs()
            ->groupBy('nest_id')
            ->map(fn ($eggs) => [
                'id' => $eggs->first()->nest->id,
                'name' => $eggs->first()->nest->name,
                'eggs' => $eggs->map(fn (Egg $egg) => [
                    'id' => $egg->id,
                    'name' => $egg->name,
                    'description' => $egg->description,
                    'image' => $this->image($egg),
                ])->values(),
            ])
            ->sortBy('name')
            ->values();

        return [
            'current' => $server->egg_id,
            'max_profiles' => $this->addon->maxProfiles(),
            'nests' => $nests,
            'profiles' => $server->gameProfiles()->with('egg')->latest()->get()->map(fn (HydraGameProfile $profile) => [
                'id' => $profile->id,
                'name' => $profile->name,
                'egg' => $profile->egg?->name,
                'image' => $this->image($profile->egg),
                'size' => $profile->size,
                'created_at' => $profile->created_at->toAtomString(),
            ])->values(),
        ];
    }

    /**
     * @throws \Throwable
     */
    public function switch(Request $request, Server $server): JsonResponse
    {
        $this->check($request, $server);
        $data = $request->validate([
            'egg' => 'required|integer',
            'keep' => 'required|boolean',
            'name' => 'nullable|string|max:191',
        ]);
        set_time_limit(180);

        /** @var Egg $egg */
        $egg = Egg::query()->findOrFail($data['egg']);
        $from = $server->egg->name;
        $this->service->switchTo($server, $egg, (bool) $data['keep'], $data['name'] ?? null);

        Activity::event('server:game.switch')->property(['from' => $from, 'to' => $egg->name, 'kept' => (bool) $data['keep']])->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @throws \Throwable
     */
    public function load(Request $request, Server $server, int $profile): JsonResponse
    {
        $this->check($request, $server);
        $data = $request->validate([
            'keep' => 'required|boolean',
            'name' => 'nullable|string|max:191',
        ]);
        set_time_limit(180);

        /** @var HydraGameProfile $model */
        $model = $server->gameProfiles()->findOrFail($profile);
        $name = $model->name;
        $this->service->load($server, $model, (bool) $data['keep'], $data['name'] ?? null);

        Activity::event('server:game.load')->property(['name' => $name, 'kept' => (bool) $data['keep']])->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @throws \Throwable
     */
    public function delete(Request $request, Server $server, int $profile): JsonResponse
    {
        $this->check($request, $server);

        /** @var HydraGameProfile $model */
        $model = $server->gameProfiles()->findOrFail($profile);
        $name = $model->name;
        $this->service->delete($model);

        Activity::event('server:game.delete')->property('name', $name)->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Background of the egg, or its banner or list image.
     */
    private function image(?Egg $egg): ?string
    {
        if (!$egg) {
            return null;
        }

        return $this->backgrounds->forEgg($egg)['image'] ?? $this->backgrounds->imageForEgg($egg, 'banner') ?? $this->backgrounds->imageForEgg($egg, 'list');
    }

    private function check(Request $request, Server $server): void
    {
        if (!$request->user()->can(Permission::ACTION_SETTINGS_GAME, $server)) {
            throw new AuthorizationException();
        }
    }
}

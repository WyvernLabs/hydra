<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Hydra\Effects\SeasonalEffects;

/**
 * Hydra: what the user wants to see of the running seasonal event.
 */
class EffectsController extends ClientApiController
{
    public function __construct(private SeasonalEffects $effects)
    {
        parent::__construct();
    }

    public function index(Request $request): array
    {
        return $this->state($request->user());
    }

    public function update(Request $request): array
    {
        $data = $request->validate([
            'values' => 'required|array',
            'values.*' => 'boolean',
        ]);

        $this->effects->save($request->user(), $data['values']);

        return $this->state($request->user());
    }

    private function state(User $user): array
    {
        return [
            'event' => $this->effects->event(),
            'options' => $this->effects->options(),
            'values' => (object) $this->effects->values($user),
        ];
    }
}

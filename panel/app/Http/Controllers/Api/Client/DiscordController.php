<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\User;
use Pterodactyl\Models\HydraDiscordLink;
use Pterodactyl\Services\Hydra\Addons\DiscordAddon;
use Pterodactyl\Services\Hydra\Discord\DiscordLinkService;

/**
 * Hydra: connecting the account to Discord (addon "discord").
 */
class DiscordController extends ClientApiController
{
    public function __construct(private DiscordLinkService $service)
    {
        parent::__construct();
    }

    public function index(Request $request): array
    {
        return $this->state($request->user());
    }

    public function sendCode(Request $request): array
    {
        $data = $request->validate(['discord_id' => ['required', 'string', 'regex:' . DiscordAddon::SNOWFLAKE]]);

        $this->service->sendCode($request->user(), $data['discord_id']);

        return $this->state($request->user());
    }

    public function confirm(Request $request): array
    {
        $data = $request->validate(['code' => 'required|string|max:32']);

        $this->service->confirm($request->user(), $data['code']);

        return $this->state($request->user());
    }

    public function cancel(Request $request): array
    {
        $this->service->cancel($request->user());

        return $this->state($request->user());
    }

    public function update(Request $request): array
    {
        $data = $request->validate(['notify_tickets' => 'required|boolean']);

        HydraDiscordLink::query()->where('user_id', $request->user()->id)->firstOrFail()->update(['notify_tickets' => $data['notify_tickets']]);

        return $this->state($request->user());
    }

    public function delete(Request $request): Response
    {
        $this->service->unlink($request->user());

        return $this->returnNoContent();
    }

    private function state(User $user): array
    {
        $link = HydraDiscordLink::query()->where('user_id', $user->id)->first();

        return [
            'link' => $link ? [
                'discord_id' => $link->discord_id,
                'username' => $link->username,
                'notify_tickets' => $link->notify_tickets,
            ] : null,
            'pending' => $link ? null : $this->service->pending($user),
        ];
    }
}

<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Models\ApiKey;

/**
 * Hydra: remembers when a user was last active in the panel, so the admin area
 * can show who is online. Requests made with an API key do not count, and the
 * database is written to at most once a minute per user.
 */
class TrackLastSeen
{
    public function handle(Request $request, \Closure $next): mixed
    {
        $user = $request->user();

        if ($user instanceof User && !$user->currentAccessToken() instanceof ApiKey) {
            if (is_null($user->last_seen_at) || $user->last_seen_at->lt(now()->subMinute())) {
                $now = now();
                // Neither touch updated_at nor run the model validation for this.
                User::query()->whereKey($user->id)->toBase()->update(['last_seen_at' => $now]);
                $user->setAttribute('last_seen_at', $now)->syncOriginalAttribute('last_seen_at');
            }
        }

        return $next($request);
    }
}

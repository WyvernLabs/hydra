<?php

namespace Pterodactyl\Services\Users;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Notifications\AccountDeleted;
use Illuminate\Support\Facades\Notification;
use Pterodactyl\Exceptions\DisplayException;

class UserDeletionService
{
    /**
     * Delete a user from the panel only if they have no servers attached to their account.
     *
     * @throws DisplayException
     */
    public function handle(int|User $user): ?bool
    {
        $user = $user instanceof User ? $user : User::query()->findOrFail($user);

        $servers = Server::query()->where('owner_id', $user->id)->count();
        if ($servers > 0) {
            throw new DisplayException(trans('admin/user.exceptions.user_has_servers'));
        }

        // Hydra: profile pictures and banners go with the account.
        ProfileMediaService::removeFiles($user);

        $deleted = $user->delete();

        // Hydra: the user is told once the account is gone.
        if ($deleted) {
            Notification::route('mail', $user->email)
                ->notify((new AccountDeleted($user->name_first ?: $user->username))->locale($user->preferredLocale()));
        }

        return $deleted;
    }
}

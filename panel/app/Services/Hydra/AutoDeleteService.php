<?php

namespace Pterodactyl\Services\Hydra;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\UserSanction;
use Pterodactyl\Services\Users\UserDeletionService;
use Pterodactyl\Services\Servers\ServerDeletionService;
use Pterodactyl\Services\Hydra\Addons\AutoDeleteAddon;

/**
 * Hydra: the autodelete addon. Servers suspended for longer than the set days
 * and accounts banned permanently for longer than the set days are deleted.
 * Root admins are never deleted.
 */
class AutoDeleteService
{
    public function __construct(
        private AutoDeleteAddon $addon,
        private ServerDeletionService $servers,
        private UserDeletionService $users,
    ) {
    }

    /**
     * Servers that are due for deletion.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Server>
     */
    public function dueServers()
    {
        return Server::query()
            ->where('status', Server::STATUS_SUSPENDED)
            ->whereNotNull('hydra_suspended_at')
            ->where('hydra_suspended_at', '<=', now()->subDays($this->addon->serverDays()));
    }

    /**
     * Accounts that are due for deletion.
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function dueUsers()
    {
        return User::query()
            ->where('root_admin', false)
            ->whereHas('sanctions', fn ($query) => $query->activeBans()
                ->whereNull('expires_at')
                ->where('created_at', '<=', now()->subDays($this->addon->userDays())));
    }

    /**
     * @return array{servers: int, users: int, failed: int}
     */
    public function run(): array
    {
        $result = ['servers' => 0, 'users' => 0, 'failed' => 0];

        if ($this->addon->deletesBannedUsers()) {
            $this->dueUsers()->each(function (User $user) use (&$result) {
                try {
                    // An account can only be deleted without servers.
                    Server::query()->where('owner_id', $user->id)->each(fn (Server $server) => $this->deleteServer($server, 'banned-user'));
                    Activity::event('user:account.auto-deleted')->property(['username' => $user->username, 'email' => $user->email])->log();
                    $this->users->handle($user);
                    ++$result['users'];
                } catch (\Throwable $exception) {
                    Log::warning($exception);
                    ++$result['failed'];
                }
            });
        }

        if ($this->addon->deletesSuspendedServers()) {
            $this->dueServers()->each(function (Server $server) use (&$result) {
                try {
                    $this->deleteServer($server, 'suspended');
                    ++$result['servers'];
                } catch (\Throwable $exception) {
                    Log::warning($exception);
                    ++$result['failed'];
                }
            });
        }

        return $result;
    }

    /**
     * Deletes a server. A node that is offline does not stop the deletion,
     * like "force delete" in the admin area.
     */
    private function deleteServer(Server $server, string $reason): void
    {
        Activity::event('server:auto-deleted')
            ->property(['name' => $server->name, 'uuid' => $server->uuid, 'reason' => $reason])
            ->log();

        $this->servers->withForce()->handle($server);
    }
}

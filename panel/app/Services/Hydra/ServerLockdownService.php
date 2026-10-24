<?php

namespace Pterodactyl\Services\Hydra;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Jobs\RevokeSftpAccessJob;
use Pterodactyl\Repositories\Wings\DaemonRevocationRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: lockdown of a server. While it lasts only the owner and admins with
 * "servers.lockdown" may use the server, everyone else is thrown out right
 * away (console, SFTP, running downloads and uploads). A suspension does not
 * end it, it is in place again once the server is unsuspended.
 */
class ServerLockdownService
{
    public function __construct(private DaemonRevocationRepository $revocation)
    {
    }

    public function enable(Server $server): void
    {
        if ($server->isLockedDown()) {
            return;
        }

        $server->forceFill(['hydra_lockdown_at' => CarbonImmutable::now()])->save();

        $reachable = true;
        foreach ($this->lockedOutUsers($server) as $user) {
            $reachable = $reachable && $this->disconnect($server, $user);
            if (!$reachable) {
                RevokeSftpAccessJob::dispatch($user->uuid, $server);
            }
        }
    }

    public function disable(Server $server): void
    {
        $server->forceFill(['hydra_lockdown_at' => null])->save();
    }

    /**
     * Everyone who could use the server until now but may not during the
     * lockdown: subusers and admins who may open every server.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function lockedOutUsers(Server $server): \Illuminate\Support\Collection
    {
        return User::query()
            ->whereIn('id', $server->subusers()->select('user_id'))
            ->orWhere('root_admin', true)
            ->orWhereHas('adminRoles')
            ->get()
            ->filter(fn (User $user) => !$server->lockdownAllows($user))
            ->filter(fn (User $user) => $user->canAccessAllServers() || $server->subusers->contains('user_id', $user->id))
            ->values();
    }

    /**
     * Ends the user's console, SFTP sessions and running transfers on the
     * node and makes Wings refuse every token they got before. Returns false
     * when the node cannot be reached, the caller then retries it in the
     * background.
     */
    private function disconnect(Server $server, User $user): bool
    {
        try {
            $this->revocation->setNode($server->node)->deauthorize($user->uuid, [$server->uuid]);
        } catch (DaemonConnectionException) {
            return false;
        }

        return true;
    }

}

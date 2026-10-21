<?php

namespace Pterodactyl\Services\Hydra\Users;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\UserSanction;
use Pterodactyl\Jobs\RevokeSftpAccessJob;
use Pterodactyl\Notifications\AccountBanned;
use Pterodactyl\Notifications\AccountWarned;
use Pterodactyl\Services\Servers\SuspensionService;

/**
 * Hydra: warnings and bans of user accounts.
 *
 * A ban suspends every server the user owns and disconnects them from the
 * websockets and SFTP right away; the next request of a logged in session is
 * logged out by the EnsureUserIsNotBanned middleware. When the ban ends (lifted
 * by an admin or expired) the servers it suspended are unsuspended again.
 */
class SanctionService
{
    public function __construct(private SuspensionService $suspension)
    {
    }

    public function warn(User $user, ?User $issuer, string $reason): UserSanction
    {
        /** @var UserSanction $warning */
        $warning = UserSanction::query()->create([
            'user_id' => $user->id,
            'type' => UserSanction::TYPE_WARNING,
            'reason' => $reason,
            'issued_by' => $issuer?->id,
        ]);

        Activity::event('user:account.warned')->subject($user)->property('reason', $reason)->log();
        $user->notify(new AccountWarned($reason));

        return $warning;
    }

    /**
     * @param int|null $days null bans permanently
     */
    public function ban(User $user, ?User $issuer, string $reason, ?int $days): UserSanction
    {
        // A new ban replaces one that is still running.
        if ($current = $user->activeBan()) {
            $current->forceFill(['lifted_at' => now(), 'lifted_by' => $issuer?->id])->save();
        }

        $suspended = array_values(array_unique(array_merge(
            $current?->suspended_servers ?? [],
            $this->suspendServers($user),
        )));

        /** @var UserSanction $ban */
        $ban = UserSanction::query()->create([
            'user_id' => $user->id,
            'type' => UserSanction::TYPE_BAN,
            'reason' => $reason,
            'issued_by' => $issuer?->id,
            'expires_at' => $days ? now()->addDays($days) : null,
            'suspended_servers' => $suspended,
        ]);

        $this->disconnect($user);

        Activity::event('user:account.banned')
            ->subject($user)
            ->property(['reason' => $reason, 'days' => $days])
            ->log();
        $user->notify(new AccountBanned($reason, $ban->expires_at));

        return $ban;
    }

    /**
     * Ends a ban and unsuspends the servers it suspended.
     */
    public function lift(UserSanction $ban, ?User $by): void
    {
        if (!$ban->isBan() || !is_null($ban->lifted_at)) {
            return;
        }

        $ban->forceFill(['lifted_at' => now(), 'lifted_by' => $by?->id])->save();
        $this->unsuspendServers($ban);

        Activity::event($by ? 'user:account.unbanned' : 'user:account.ban-expired')->subject($ban->user)->log();
    }

    /**
     * Ends bans whose time is up. Called by the scheduler.
     */
    public function liftExpired(): int
    {
        $count = 0;
        UserSanction::query()
            ->where('type', UserSanction::TYPE_BAN)
            ->whereNull('lifted_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->each(function (UserSanction $ban) use (&$count) {
                $this->lift($ban, null);
                ++$count;
            });

        return $count;
    }

    /**
     * @return int[] ids of the servers that were suspended
     */
    private function suspendServers(User $user): array
    {
        $ids = [];
        Server::query()->where('owner_id', $user->id)->each(function (Server $server) use (&$ids) {
            if ($server->isSuspended()) {
                return;
            }

            try {
                $this->suspension->toggle($server, SuspensionService::ACTION_SUSPEND);
            } catch (\Throwable $exception) {
                // The node is offline or the server is being transferred: suspend
                // it in the panel anyway, Wings follows when it syncs.
                Log::warning($exception);
                $server->forceFill(['status' => Server::STATUS_SUSPENDED, 'hydra_suspended_at' => now()])->save();
            }
            $ids[] = $server->id;
        });

        return $ids;
    }

    private function unsuspendServers(UserSanction $ban): void
    {
        foreach (Server::query()->whereIn('id', $ban->suspended_servers ?? [])->get() as $server) {
            if (!$server->isSuspended()) {
                continue;
            }

            try {
                $this->suspension->toggle($server, SuspensionService::ACTION_UNSUSPEND);
            } catch (\Throwable $exception) {
                Log::warning($exception);
                $server->forceFill(['status' => null, 'hydra_suspended_at' => null])->save();
            }
        }
    }

    /**
     * Closes the user's websockets and SFTP sessions on every node they use.
     */
    private function disconnect(User $user): void
    {
        Node::query()
            ->whereIn('nodes.id', $user->accessibleServers()->select('servers.node_id')->distinct())
            ->each(fn (Node $node) => RevokeSftpAccessJob::dispatch($user->uuid, $node));
    }
}

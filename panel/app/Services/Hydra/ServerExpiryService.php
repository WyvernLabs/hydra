<?php

namespace Pterodactyl\Services\Hydra;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Servers\SuspensionService;

/**
 * Hydra: servers with a limited runtime. An admin sets a date (optional);
 * once it has passed, hydra:servers:expire suspends the server. Setting a new
 * date in the future lifts a suspension that came from the runtime again.
 *
 * The date is entered in the panel's time zone (config app.timezone) and
 * stored like every other timestamp.
 */
class ServerExpiryService
{
    /**
     * Format of <input type="datetime-local">.
     */
    public const INPUT_FORMAT = 'Y-m-d\TH:i';

    public function __construct(private SuspensionService $suspension)
    {
    }

    /**
     * Parses the value of the form field; empty = no runtime.
     */
    public static function parse(?string $value): ?CarbonImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $date = CarbonImmutable::createFromFormat(self::INPUT_FORMAT, substr($value, 0, 16), config('app.timezone'));

        return $date ?: null;
    }

    /**
     * Value for the form field.
     */
    public static function inputValue(?\DateTimeInterface $date): string
    {
        return $date ? CarbonImmutable::instance($date)->setTimezone(config('app.timezone'))->format(self::INPUT_FORMAT) : '';
    }

    /**
     * Sets or removes the runtime of a server.
     *
     * @throws \Throwable
     */
    public function set(Server $server, ?\DateTimeInterface $expiresAt): void
    {
        $before = $server->hydra_expires_at;
        $wasExpired = $this->suspendedByExpiry($server);

        $server->forceFill([
            'hydra_expires_at' => $expiresAt,
            // A new date (or none) starts over; a date in the past is handled by the next run.
            'hydra_expired_at' => null,
        ])->saveOrFail();

        // Extended: the suspension came from the runtime, so lift it again.
        if ($wasExpired && $server->isSuspended() && (is_null($expiresAt) || $expiresAt > now())) {
            $this->suspension->toggle($server, SuspensionService::ACTION_UNSUSPEND);
        }

        if ($before?->getTimestamp() !== $expiresAt?->getTimestamp()) {
            Activity::event($expiresAt ? 'server:expiry.update' : 'server:expiry.clear')
                ->subject($server)
                ->property('until', $expiresAt ? CarbonImmutable::instance($expiresAt)->setTimezone(config('app.timezone'))->format('d.m.Y H:i') : null)
                ->log();
        }
    }

    /**
     * Whether the current suspension was caused by the runtime (and not by an
     * admin before or after it): both happened in the same run.
     */
    public function suspendedByExpiry(Server $server): bool
    {
        return $server->isSuspended()
            && $server->hydra_expired_at
            && $server->hydra_suspended_at
            && abs($server->hydra_suspended_at->getTimestamp() - $server->hydra_expired_at->getTimestamp()) <= 60;
    }

    /**
     * Suspends every server whose runtime is over.
     *
     * @return array{suspended: int, failed: int}
     */
    public function expire(): array
    {
        $result = ['suspended' => 0, 'failed' => 0];

        Server::query()
            ->whereNotNull('hydra_expires_at')
            ->where('hydra_expires_at', '<=', now())
            ->whereNull('hydra_expired_at')
            ->with('transfer', 'user')
            ->chunkById(100, function ($servers) use (&$result) {
                /** @var Server $server */
                foreach ($servers as $server) {
                    try {
                        $this->suspension->toggle($server, SuspensionService::ACTION_SUSPEND);
                    } catch (\Throwable $exception) {
                        // E.g. the node is offline or a transfer is running: try again next minute.
                        Log::warning("Hydra: could not suspend expired server $server->uuid: " . $exception->getMessage());
                        ++$result['failed'];
                        continue;
                    }

                    $server->forceFill(['hydra_expired_at' => now()])->save();
                    Activity::event('server:expiry.expired')->subject($server)->log();
                    ++$result['suspended'];
                }
            });

        return $result;
    }
}

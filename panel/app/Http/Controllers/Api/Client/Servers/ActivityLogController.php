<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\ActivityLog;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Eloquent\Relations\Relation;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;
use Pterodactyl\Transformers\Api\Client\ActivityLogTransformer;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;

class ActivityLogController extends ClientApiController
{
    /**
     * Returns the activity logs for a server.
     *
     * Hydra: besides the upstream "event" filter (part of the event, used by
     * the links in the log) the log can be filtered by "actor" (user ID),
     * "action" (exact event) and "search" (text in the properties or the IP).
     */
    public function __invoke(ClientApiRequest $request, Server $server): array
    {
        $this->authorize(Permission::ACTION_ACTIVITY_READ, $server);

        $activity = QueryBuilder::for($this->query($server))
            ->with('actor')
            ->allowedSorts(['timestamp'])
            ->allowedFilters([
                AllowedFilter::partial('event'),
                AllowedFilter::callback('action', fn (Builder $query, $value) => $query->where('activity_logs.event', (string) $value)),
                AllowedFilter::callback('actor', function (Builder $query, $value) {
                    $query->where('activity_logs.actor_type', (new User())->getMorphClass())
                        ->where('activity_logs.actor_id', (int) $value);
                }),
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $text = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], Str::limit(trim((string) $value), 100, '')) . '%';
                    $query->where(function (Builder $query) use ($text) {
                        $query->where('activity_logs.properties', 'like', $text)
                            ->orWhere('activity_logs.ip', 'like', $text)
                            ->orWhere('activity_logs.event', 'like', $text);
                    });
                }),
            ])
            ->paginate(min($request->query('per_page', 25), 100))
            ->appends($request->query());

        return $this->fractal->collection($activity)
            ->transformWith($this->getTransformer(ActivityLogTransformer::class))
            ->toArray();
    }

    /**
     * Hydra: the users and actions that appear in the log of the server, for
     * the filter of the activity page. Actions carry a readable name in the
     * language of the user.
     */
    public function filters(ClientApiRequest $request, Server $server): array
    {
        $this->authorize(Permission::ACTION_ACTIVITY_READ, $server);

        $events = $this->query($server)
            ->reorder()
            ->select('activity_logs.event')
            ->distinct()
            ->pluck('activity_logs.event')
            ->sort()
            ->values();

        $actorIds = $this->query($server)
            ->reorder()
            ->where('activity_logs.actor_type', (new User())->getMorphClass())
            ->select('activity_logs.actor_id')
            ->distinct()
            ->pluck('activity_logs.actor_id');

        $actors = User::query()
            ->whereIn('id', $actorIds)
            ->orderBy('username')
            ->get(['id', 'username'])
            ->map(fn (User $user) => ['id' => $user->id, 'username' => $user->username])
            ->values();

        return [
            'actors' => $actors,
            'events' => $events->map(fn (string $event) => ['event' => $event, 'label' => self::label($event)])
                ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                ->values(),
        ];
    }

    /**
     * A readable name of an event from the activity texts, with the
     * placeholders left out, e.g. "Datei … gelöscht".
     */
    public static function label(string $event): string
    {
        $key = 'activity.' . str_replace(':', '.', $event);
        $text = null;
        // Texts with plural forms are stored as "<key>_one" / "<key>_other" (i18next).
        foreach ([$key, "{$key}_other", "{$key}_one"] as $candidate) {
            $found = trans($candidate);
            if (is_string($found) && $found !== $candidate) {
                $text = $found;
                break;
            }
        }
        if ($text === null) {
            return $event;
        }

        // Plural forms: the first one is enough.
        $text = explode('|', $text)[0];
        $text = preg_replace('/^\s*[{\[][^}\]]*[}\]]\s*/', '', $text);
        $text = preg_replace('/:[a-z_]+(?:\.[a-z0-9_]+)*/i', '…', $text);
        $text = preg_replace('/…+/u', '…', $text);
        $text = preg_replace('/\s*\(\s*…\s*\)/u', '', $text);

        return trim(preg_replace('/\s+/', ' ', $text)) ?: $event;
    }

    /**
     * The activity of the server the user may see.
     */
    private function query(Server $server): Builder|Relation
    {
        return $server->activity()
            ->whereNotIn('activity_logs.event', ActivityLog::DISABLED_EVENTS)
            ->when(config('activity.hide_admin_activity'), function (Builder $builder) use ($server) {
                // We could do this with a query and a lot of joins, but that gets pretty
                // painful so for now we'll execute a simpler query.
                $subusers = $server->subusers()->pluck('user_id')->merge($server->owner_id);

                $builder->select('activity_logs.*')
                    ->leftJoin('users', function (JoinClause $join) {
                        $join->on('users.id', 'activity_logs.actor_id')
                            ->where('activity_logs.actor_type', (new User())->getMorphClass());
                    })
                    ->where(function (Builder $builder) use ($subusers) {
                        $builder->whereNull('users.id')
                            ->orWhere('users.root_admin', 0)
                            ->orWhereIn('users.id', $subusers);
                    });
            });
    }
}

<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Pterodactyl\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\HydraAnnouncement;

/**
 * Hydra: the announcements a user sees. The dashboard gets the general ones and
 * those of the nodes with the user's servers, a server page those of its node.
 */
class AnnouncementController extends ClientApiController
{
    public function index(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $identifier = $request->query('server');

        $nodes = is_string($identifier) ? $this->serverNode($user, $identifier) : $user->accessibleServers()->pluck('servers.node_id')->map(fn ($id) => (int) $id)->unique()->all();
        $dismissed = DB::table('hydra_announcement_dismissals')
            ->where('user_id', $user->id)
            ->where('dismissed_until', '>', now())
            ->pluck('announcement_id')
            ->all();

        return [
            'data' => HydraAnnouncement::query()->visible()->get()
                ->filter(fn (HydraAnnouncement $item) => $item->isForNodes($nodes) || (!is_string($identifier) && $item->isGeneral()))
                // Once closing is switched off an announcement is back for everyone.
                ->reject(fn (HydraAnnouncement $item) => $item->dismissible && in_array($item->id, $dismissed))
                ->map(fn (HydraAnnouncement $item) => $item->toClientArray())
                ->values()
                ->all(),
        ];
    }

    /**
     * Hides an announcement for the user for the next hours, also on other devices.
     */
    public function dismiss(Request $request, int $announcement): Response
    {
        $item = HydraAnnouncement::query()->visible()->where('dismissible', true)->findOrFail($announcement);

        DB::table('hydra_announcement_dismissals')->updateOrInsert(
            ['announcement_id' => $item->id, 'user_id' => $request->user()->id],
            ['dismissed_until' => now()->addHours(HydraAnnouncement::DISMISS_HOURS)],
        );

        return $this->returnNoContent();
    }

    /**
     * @return int[] the node of a server the user may open
     */
    private function serverNode(User $user, string $identifier): array
    {
        $query = $user->hasAdminPermission('servers.access') ? Server::query() : $user->accessibleServers();
        $server = $query->where('servers.uuid', $identifier)->first();

        return $server ? [(int) $server->node_id] : [];
    }
}

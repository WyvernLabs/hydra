<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\HydraConfigStash;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Pterodactyl\Services\Hydra\Addons\ConfigSyncAddon;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;

/**
 * Hydra: storage of configs of a server (addon "config_sync"). The editor puts
 * the content of a config here and later syncs its values into another one;
 * the sync itself runs in the browser.
 */
class ConfigStashController extends ClientApiController
{
    public function __construct(private ConfigSyncAddon $addon)
    {
        parent::__construct();
    }

    public function index(Request $request, Server $server): array
    {
        $this->check($request, $server);

        return [
            'entries' => HydraConfigStash::query()
                ->where('server_id', $server->id)
                ->with('creator:id,username')
                ->latest('id')
                ->get(['id', 'server_id', 'name', 'path', 'size', 'created_by', 'created_at'])
                ->map(fn (HydraConfigStash $entry) => $this->entry($entry))
                ->values(),
            'max_entries' => $this->addon->maxEntries(),
            'max_size' => $this->addon->maxSize(),
        ];
    }

    public function show(Request $request, Server $server, int $stash): array
    {
        $this->check($request, $server);

        $entry = $this->find($server, $stash);

        return $this->entry($entry) + ['content' => $entry->content];
    }

    public function store(Request $request, Server $server): array
    {
        $this->check($request, $server);
        $data = $request->validate([
            'name' => 'nullable|string|max:64',
            'path' => 'required|string|max:512',
            'content' => 'present|nullable|string',
        ]);

        $content = (string) ($data['content'] ?? '');
        if (strlen($content) > $this->addon->maxSize() * 1024) {
            throw ValidationException::withMessages(['content' => trans('server/config_sync.errors.too_large', ['size' => $this->addon->maxSize()])]);
        }
        if (HydraConfigStash::query()->where('server_id', $server->id)->count() >= $this->addon->maxEntries()) {
            throw ValidationException::withMessages(['content' => trans('server/config_sync.errors.full', ['max' => $this->addon->maxEntries()])]);
        }

        $path = '/' . ltrim($data['path'], '/');
        $name = trim((string) ($data['name'] ?? '')) ?: basename($path);

        /** @var HydraConfigStash $entry */
        $entry = HydraConfigStash::query()->create([
            'server_id' => $server->id,
            'name' => mb_substr($name, 0, 64),
            'path' => $path,
            'content' => $content,
            'size' => strlen($content),
            'created_by' => $request->user()->id,
        ]);

        Activity::event('server:config-sync.store')->property(['name' => $entry->name, 'path' => $path])->log();

        return $this->entry($entry->load('creator:id,username'));
    }

    public function delete(Request $request, Server $server, int $stash): JsonResponse
    {
        $this->check($request, $server);

        $entry = $this->find($server, $stash);
        $entry->delete();

        Activity::event('server:config-sync.delete')->property(['name' => $entry->name, 'path' => $entry->path])->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @throws AuthorizationException
     */
    private function check(Request $request, Server $server): void
    {
        if (!$request->user()->can(Permission::ACTION_FILE_CONFIG_SYNC, $server)) {
            throw new AuthorizationException();
        }
    }

    private function find(Server $server, int $id): HydraConfigStash
    {
        /* @var HydraConfigStash */
        return HydraConfigStash::query()->where('server_id', $server->id)->findOrFail($id);
    }

    private function entry(HydraConfigStash $entry): array
    {
        return [
            'id' => $entry->id,
            'name' => $entry->name,
            'path' => $entry->path,
            'size' => $entry->size,
            'created_by' => $entry->creator?->username,
            'created_at' => $entry->created_at->toAtomString(),
        ];
    }
}

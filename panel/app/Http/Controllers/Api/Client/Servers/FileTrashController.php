<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Files\AllowedPaths;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Addons\TrashAddon;
use Pterodactyl\Repositories\Wings\DaemonTrashRepository;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\ListTrashRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\DeleteTrashRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\RestoreTrashRequest;

/**
 * Hydra: trash of the file manager. Subusers limited to certain folders only
 * see and handle what was deleted inside them.
 */
class FileTrashController extends ClientApiController
{
    public function __construct(private DaemonTrashRepository $repository, private TrashAddon $addon)
    {
        parent::__construct();
    }

    public function index(ListTrashRequest $request, Server $server): array
    {
        $entries = array_map(fn (array $entry) => [
            'id' => $entry['id'],
            'path' => $entry['path'],
            'name' => basename($entry['path']),
            'directory' => (bool) $entry['directory'],
            'size' => (int) $entry['size'],
            'deleted_at' => $entry['deleted_at'],
            'expires_at' => $entry['expires_at'],
        ], $this->entries($request, $server));

        return [
            'data' => $entries,
            'meta' => $this->addon->toArray(),
        ];
    }

    public function restore(RestoreTrashRequest $request, Server $server): array
    {
        $ids = $this->allowedIds($request, $server, $request->input('ids'));

        $restored = $this->repository->setServer($server)->restore($ids);

        Activity::event('server:file.trash-restore')
            ->property('files', array_values($restored))
            ->log();

        return ['restored' => $restored];
    }

    public function delete(DeleteTrashRequest $request, Server $server): JsonResponse
    {
        $repository = $this->repository->setServer($server);

        if ($request->boolean('all') && !AllowedPaths::for($server, $request->user())->isRestricted()) {
            $repository->emptyTrash();
            Activity::event('server:file.trash-empty')->log();

            return new JsonResponse([], Response::HTTP_NO_CONTENT);
        }

        $entries = $this->entries($request, $server);
        $ids = $request->boolean('all') ? array_column($entries, 'id') : $this->allowedIds($request, $server, $request->input('ids'), $entries);
        if (!empty($ids)) {
            $repository->delete($ids);
        }

        Activity::event('server:file.trash-delete')
            ->property('files', array_values(array_map(
                fn (array $entry) => $entry['path'],
                array_filter($entries, fn (array $entry) => in_array($entry['id'], $ids, true))
            )))
            ->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Entries of the trash the user may see.
     */
    private function entries(Request $request, Server $server): array
    {
        $paths = AllowedPaths::for($server, $request->user());

        return array_values(array_filter(
            $this->repository->setServer($server)->list(),
            fn (array $entry) => $paths->allows($entry['path'] ?? '')
        ));
    }

    /**
     * The requested ids, each of which has to be an entry the user may handle.
     *
     * @throws DisplayException
     */
    private function allowedIds(Request $request, Server $server, array $ids, ?array $entries = null): array
    {
        $known = array_column($entries ?? $this->entries($request, $server), 'id');
        $ids = array_values(array_unique($ids));
        if (array_diff($ids, $known)) {
            throw new DisplayException(__('client/files.trash.not_found'));
        }

        return $ids;
    }
}

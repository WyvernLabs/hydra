<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\Request;
use Pterodactyl\Models\Database;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Pterodactyl\Services\Hydra\Databases\DatabaseTransfer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Http\Controllers\Api\Client\Servers\DatabaseManagerController;

/**
 * Hydra: download of a database export. The link is created by the client API
 * (with the permission check) and works once, for the same user, for a minute.
 */
class DatabaseExportController extends Controller
{
    public function __invoke(Request $request, DatabaseTransfer $transfer, string $token): StreamedResponse
    {
        $data = Cache::pull(DatabaseManagerController::EXPORT_CACHE . $token);
        if (!is_array($data) || $data['user'] !== $request->user()->id) {
            throw new NotFoundHttpException();
        }

        $database = Database::query()->with('host', 'server')->findOrFail($data['database']);
        // Hydra: the server may have gone into lockdown since the link was made.
        if (!$database->server->lockdownAllows($request->user())) {
            throw new NotFoundHttpException();
        }

        $writer = $transfer->export($database);

        @set_time_limit(0);

        $name = sprintf('%s-%s.sql.gz', $database->database, now()->format('Y-m-d-His'));

        return new StreamedResponse($writer, 200, [
            'Content-Type' => 'application/gzip',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-store',
        ]);
    }
}

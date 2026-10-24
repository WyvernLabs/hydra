<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Database;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Services\Hydra\PhpMyAdminService;
use Pterodactyl\Services\Hydra\Databases\DatabaseBrowser;
use Pterodactyl\Services\Hydra\Databases\DatabaseTransfer;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\GetDatabasesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\Hydra\EditDatabaseRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\Hydra\BrowseDatabaseRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\Hydra\ExportDatabaseRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\Hydra\ImportDatabaseRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\Hydra\PhpMyAdminDatabaseRequest;

/**
 * Hydra: tables and data of a server database, import, export and the login
 * to phpMyAdmin.
 */
class DatabaseManagerController extends ClientApiController
{
    public const EXPORT_CACHE = 'hydra:database-export:';

    public function __construct(
        private DatabaseBrowser $browser,
        private DatabaseTransfer $transfer,
        private PhpMyAdminService $phpMyAdmin,
    ) {
        parent::__construct();
    }

    /**
     * Size and number of tables for the list of databases; null when the
     * database server cannot be reached.
     */
    public function stats(GetDatabasesRequest $request, Server $server, Database $database): array
    {
        try {
            return $this->browser->for($database)->stats();
        } catch (\Throwable) {
            return ['tables' => null, 'size' => null];
        }
    }

    public function tables(BrowseDatabaseRequest $request, Server $server, Database $database): array
    {
        return [
            'tables' => $this->browser->for($database)->tables(),
            'phpmyadmin' => $this->phpMyAdmin->isOffered(),
        ];
    }

    public function structure(BrowseDatabaseRequest $request, Server $server, Database $database): array
    {
        $table = (string) $request->validate(['table' => 'required|string|max:64'])['table'];
        $browser = $this->browser->for($database);

        return ['columns' => $browser->columns($table), 'indexes' => $browser->indexes($table)];
    }

    public function rows(BrowseDatabaseRequest $request, Server $server, Database $database): array
    {
        $data = $request->validate([
            'table' => 'required|string|max:64',
            'page' => 'sometimes|integer|min:1',
            'sort' => 'sometimes|nullable|string|max:64',
            'direction' => 'sometimes|in:asc,desc',
        ]);

        return $this->browser->for($database)->rows(
            $data['table'],
            (int) ($data['page'] ?? 1),
            $data['sort'] ?? null,
            $data['direction'] ?? 'asc',
        );
    }

    /**
     * A single row with full values, for the edit form.
     */
    public function row(BrowseDatabaseRequest $request, Server $server, Database $database): array
    {
        $data = $request->validate(['table' => 'required|string|max:64', 'key' => 'required|array']);

        return ['values' => $this->browser->for($database)->row($data['table'], $data['key'])];
    }

    public function insert(EditDatabaseRequest $request, Server $server, Database $database): Response
    {
        $data = $request->validate(['table' => 'required|string|max:64', 'values' => 'present|array']);
        $this->browser->for($database)->insert($data['table'], $this->values($request->input('values', [])));

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function update(EditDatabaseRequest $request, Server $server, Database $database): Response
    {
        $data = $request->validate(['table' => 'required|string|max:64', 'key' => 'required|array', 'values' => 'required|array']);
        $this->browser->for($database)->update($data['table'], $request->input('key'), $this->values($request->input('values')));

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function delete(EditDatabaseRequest $request, Server $server, Database $database): Response
    {
        $data = $request->validate(['table' => 'required|string|max:64', 'key' => 'required|array']);
        $this->browser->for($database)->delete($data['table'], $request->input('key'));

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Runs one statement of the SQL console.
     */
    public function query(EditDatabaseRequest $request, Server $server, Database $database): array
    {
        $sql = (string) $request->validate(['sql' => 'required|string|max:100000'])['sql'];

        Activity::event('server:database.query')->subject($database)->property('name', $database->database)->log();

        return $this->browser->for($database)->query($sql);
    }

    public function import(ImportDatabaseRequest $request, Server $server, Database $database): Response
    {
        $request->validate(['file' => 'required|file|max:' . (100 * 1024)]);
        $file = $request->file('file');

        @set_time_limit(0);
        ignore_user_abort(true);

        Activity::event('server:database.import')
            ->subject($database)
            ->property(['name' => $database->database, 'file' => mb_substr($file->getClientOriginalName(), 0, 191)])
            ->transaction(fn () => $this->transfer->import($database, $file->getRealPath()));

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * A one-time link for the download, so it works as a normal browser
     * download.
     */
    public function export(ExportDatabaseRequest $request, Server $server, Database $database): array
    {
        $token = Str::random(48);
        Cache::put(self::EXPORT_CACHE . $token, ['database' => $database->id, 'user' => $request->user()->id], now()->addMinute());

        Activity::event('server:database.export')->subject($database)->property('name', $database->database)->log();

        return ['url' => route('databases.export', $token)];
    }

    public function phpMyAdmin(PhpMyAdminDatabaseRequest $request, Server $server, Database $database): JsonResponse
    {
        if (!$this->phpMyAdmin->isOffered()) {
            return new JsonResponse(['errors' => [['code' => 'PhpMyAdminUnavailable', 'status' => '409', 'detail' => __('server/databases.errors.phpmyadmin')]]], JsonResponse::HTTP_CONFLICT);
        }

        Activity::event('server:database.phpmyadmin')->subject($database)->property('name', $database->database)->log();

        return new JsonResponse(['url' => $this->phpMyAdmin->loginUrl($database)]);
    }

    /**
     * Values of a form: null stays NULL, everything else is a string.
     */
    private function values(mixed $values): array
    {
        $result = [];
        foreach (is_array($values) ? $values : [] as $column => $value) {
            $result[(string) $column] = is_null($value) ? null : (is_bool($value) ? (string) (int) $value : (string) $value);
        }

        return $result;
    }
}

<?php

use Pterodactyl\Enum\ResourceLimit;
use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\Client;
use Pterodactyl\Http\Middleware\Activity\ServerSubject;
use Pterodactyl\Http\Middleware\Activity\AccountSubject;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;

/*
|--------------------------------------------------------------------------
| Client Control API
|--------------------------------------------------------------------------
|
| Endpoint: /api/client
|
*/
Route::get('/', [Client\ClientController::class, 'index'])->name('api:client.index');
Route::get('/permissions', [Client\ClientController::class, 'permissions']);
// Hydra: pages written in the admin area.
Route::get('/pages/{slug}', [Client\PageController::class, 'view'])->where('slug', '[a-z0-9-]+');

Route::prefix('/account')->middleware(AccountSubject::class)->group(function () {
    Route::prefix('/')->withoutMiddleware(RequireTwoFactorAuthentication::class)->group(function () {
        Route::get('/', [Client\AccountController::class, 'index'])->name('api:client.account');
        Route::get('/two-factor', [Client\TwoFactorController::class, 'index']);
        Route::post('/two-factor', [Client\TwoFactorController::class, 'store']);
        Route::post('/two-factor/disable', [Client\TwoFactorController::class, 'delete']);
    });

    Route::put('/email', [Client\AccountController::class, 'updateEmail'])
        ->middleware('throttle')
        ->name('api:client.account.update-email');
    Route::put('/password', [Client\AccountController::class, 'updatePassword'])->name('api:client.account.update-password');
    Route::put('/language', [Client\AccountController::class, 'updateLanguage'])->name('api:client.account.update-language');
    // Hydra: profile and deleting the own account.
    Route::post('/profile', [Client\ProfileController::class, 'update'])->name('api:client.account.profile');
    Route::delete('/', [Client\ProfileController::class, 'delete'])->middleware('throttle:5,1')->name('api:client.account.delete');
    // Hydra: warnings from the admins.
    Route::post('/warnings/{warning}/acknowledge', [Client\WarningController::class, 'acknowledge'])->whereNumber('warning');

    Route::get('/activity', Client\ActivityLogController::class)->name('api:client.account.activity');

    // Hydra: resource pool with the user's own servers (resource_pool addon).
    Route::prefix('/pool')->middleware('addon:resource_pool')->group(function () {
        Route::get('/', [Client\ResourcePoolController::class, 'index'])->name('api:client.account.pool');
        Route::post('/servers', [Client\ResourcePoolController::class, 'store'])->middleware('throttle:10,1');
        Route::patch('/servers/{identifier}', [Client\ResourcePoolController::class, 'update'])->middleware('throttle:30,1');
        Route::delete('/servers/{identifier}', [Client\ResourcePoolController::class, 'delete'])->middleware('throttle:10,1');
    });

    // Hydra: support tickets (tickets addon).
    Route::prefix('/tickets')->middleware('addon:tickets')->group(function () {
        Route::get('/', [Client\TicketController::class, 'index'])->name('api:client.account.tickets');
        Route::post('/', [Client\TicketController::class, 'store'])->middleware('throttle:10,1');
        Route::post('/attachments', [Client\TicketController::class, 'upload'])->middleware('throttle:30,1');
        Route::post('/preview', [Client\TicketController::class, 'preview'])->middleware('throttle:60,1');
        Route::get('/{ticket}', [Client\TicketController::class, 'view'])->whereNumber('ticket');
        Route::post('/{ticket}/messages', [Client\TicketController::class, 'reply'])->whereNumber('ticket')->middleware('throttle:20,1');
        Route::post('/{ticket}/close', [Client\TicketController::class, 'close'])->whereNumber('ticket');
    });

    Route::get('/api-keys', [Client\ApiKeyController::class, 'index']);
    Route::post('/api-keys', [Client\ApiKeyController::class, 'store']);
    Route::delete('/api-keys/{identifier}', [Client\ApiKeyController::class, 'delete']);

    Route::prefix('/ssh-keys')->group(function () {
        Route::get('/', [Client\SSHKeyController::class, 'index']);
        Route::post('/', [Client\SSHKeyController::class, 'store']);
        Route::post('/remove', [Client\SSHKeyController::class, 'delete']);
    });
});

/*
|--------------------------------------------------------------------------
| Client Control API
|--------------------------------------------------------------------------
|
| Endpoint: /api/client/servers/{server}
|
*/
Route::group([
    'prefix' => '/servers/{server}',
    'middleware' => [
        ServerSubject::class,
        AuthenticateServerAccess::class,
        ResourceBelongsToServer::class,
    ],
], function () {
    Route::get('/', [Client\Servers\ServerController::class, 'index'])->name('api:client:server.view');
    Route::middleware([ResourceLimit::Websocket->middleware()])
        ->get('/websocket', Client\Servers\WebsocketController::class)
        ->name('api:client:server.ws');
    Route::get('/resources', Client\Servers\ResourceUtilizationController::class)->name('api:client:server.resources');
    Route::get('/activity', Client\Servers\ActivityLogController::class)->name('api:client:server.activity');
    // Hydra: players online over time (addon "player_stats").
    Route::get('/players', Client\Servers\PlayerStatsController::class)->middleware('addon:player_stats');

    Route::post('/command', [Client\Servers\CommandController::class, 'index']);
    Route::post('/power', [Client\Servers\PowerController::class, 'index']);

    Route::group(['prefix' => '/databases'], function () {
        Route::get('/', [Client\Servers\DatabaseController::class, 'index']);
        Route::middleware([ResourceLimit::Database->middleware()])
            ->post('/', [Client\Servers\DatabaseController::class, 'store']);
        Route::post('/{database}/rotate-password', [Client\Servers\DatabaseController::class, 'rotatePassword']);
        Route::delete('/{database}', [Client\Servers\DatabaseController::class, 'delete']);

        // Hydra: database manager, import, export and phpMyAdmin.
        Route::get('/{database}/stats', [Client\Servers\DatabaseManagerController::class, 'stats']);
        Route::get('/{database}/tables', [Client\Servers\DatabaseManagerController::class, 'tables']);
        Route::get('/{database}/structure', [Client\Servers\DatabaseManagerController::class, 'structure']);
        Route::get('/{database}/rows', [Client\Servers\DatabaseManagerController::class, 'rows']);
        Route::post('/{database}/rows/show', [Client\Servers\DatabaseManagerController::class, 'row']);
        Route::post('/{database}/rows', [Client\Servers\DatabaseManagerController::class, 'insert']);
        Route::patch('/{database}/rows', [Client\Servers\DatabaseManagerController::class, 'update']);
        Route::delete('/{database}/rows', [Client\Servers\DatabaseManagerController::class, 'delete']);
        Route::post('/{database}/query', [Client\Servers\DatabaseManagerController::class, 'query']);
        Route::post('/{database}/import', [Client\Servers\DatabaseManagerController::class, 'import']);
        Route::post('/{database}/export', [Client\Servers\DatabaseManagerController::class, 'export']);
        Route::post('/{database}/phpmyadmin', [Client\Servers\DatabaseManagerController::class, 'phpMyAdmin']);
    });

    Route::group(['prefix' => '/files'], function () {
        Route::get('/list', [Client\Servers\FileController::class, 'directory']);
        Route::get('/contents', [Client\Servers\FileController::class, 'contents']);
        // Hydra: uploads the log file to mclo.gs (addon).
        Route::post('/mclogs', Client\Servers\McLogsController::class)->middleware(['addon:mclogs', 'throttle:5,1']);
        Route::get('/download', [Client\Servers\FileController::class, 'download']);
        // Hydra: images, videos and music in the file manager, and its trash.
        Route::get('/preview', [Client\Servers\FileController::class, 'preview']);
        Route::get('/trash', [Client\Servers\FileTrashController::class, 'index']);
        Route::post('/trash/restore', [Client\Servers\FileTrashController::class, 'restore']);
        Route::post('/trash/delete', [Client\Servers\FileTrashController::class, 'delete']);
        Route::put('/rename', [Client\Servers\FileController::class, 'rename']);
        Route::post('/copy', [Client\Servers\FileController::class, 'copy']);
        Route::post('/write', [Client\Servers\FileController::class, 'write']);
        Route::post('/compress', [Client\Servers\FileController::class, 'compress']);
        Route::post('/decompress', [Client\Servers\FileController::class, 'decompress']);
        Route::post('/delete', [Client\Servers\FileController::class, 'delete']);
        Route::post('/create-folder', [Client\Servers\FileController::class, 'create']);
        Route::post('/chmod', [Client\Servers\FileController::class, 'chmod']);
        Route::middleware([ResourceLimit::FilePull->middleware()])
            ->post('/pull', [Client\Servers\FileController::class, 'pull']);
        Route::get('/upload', Client\Servers\FileUploadController::class);
    });

    Route::group(['prefix' => '/schedules'], function () {
        Route::get('/', [Client\Servers\ScheduleController::class, 'index']);
        Route::middleware([ResourceLimit::Schedule->middleware()])
            ->post('/', [Client\Servers\ScheduleController::class, 'store']);
        Route::get('/{schedule}', [Client\Servers\ScheduleController::class, 'view']);
        Route::post('/{schedule}', [Client\Servers\ScheduleController::class, 'update']);
        Route::post('/{schedule}/execute', [Client\Servers\ScheduleController::class, 'execute']);
        Route::delete('/{schedule}', [Client\Servers\ScheduleController::class, 'delete']);

        Route::post('/{schedule}/tasks', [Client\Servers\ScheduleTaskController::class, 'store']);
        Route::post('/{schedule}/tasks/{task}', [Client\Servers\ScheduleTaskController::class, 'update']);
        Route::delete('/{schedule}/tasks/{task}', [Client\Servers\ScheduleTaskController::class, 'delete']);
    });

    Route::group(['prefix' => '/network'], function () {
        Route::get('/allocations', [Client\Servers\NetworkAllocationController::class, 'index']);
        Route::middleware([ResourceLimit::Allocation->middleware()])
            ->post('/allocations', [Client\Servers\NetworkAllocationController::class, 'store']);
        Route::post('/allocations/{allocation}', [Client\Servers\NetworkAllocationController::class, 'update']);
        Route::post('/allocations/{allocation}/primary', [Client\Servers\NetworkAllocationController::class, 'setPrimary']);
        Route::delete('/allocations/{allocation}', [Client\Servers\NetworkAllocationController::class, 'delete']);
        // Hydra: own ports on a dedicated node, subdomains and reverse proxies (addons).
        Route::post('/allocations-dedicated', [Client\Servers\NetworkAllocationController::class, 'storeDedicated'])->middleware('throttle:20,1');
        Route::get('/hydra', [Client\Servers\NetworkHydraController::class, 'index']);
        Route::post('/subdomains', [Client\Servers\NetworkHydraController::class, 'storeSubdomain'])->middleware('throttle:10,1');
        Route::delete('/subdomains/{subdomain}', [Client\Servers\NetworkHydraController::class, 'deleteSubdomain'])->whereNumber('subdomain');
        Route::post('/proxies', [Client\Servers\NetworkHydraController::class, 'storeProxy'])->middleware('throttle:10,1');
        Route::delete('/proxies/{proxy}', [Client\Servers\NetworkHydraController::class, 'deleteProxy'])->whereNumber('proxy');
    });

    // Hydra: roles of the server for its subusers.
    Route::group(['prefix' => '/roles'], function () {
        Route::get('/', [Client\Servers\ServerRoleController::class, 'index']);
        Route::post('/', [Client\Servers\ServerRoleController::class, 'store']);
        Route::patch('/{role}', [Client\Servers\ServerRoleController::class, 'update'])->whereNumber('role');
        Route::delete('/{role}', [Client\Servers\ServerRoleController::class, 'delete'])->whereNumber('role');
    });

    Route::group(['prefix' => '/users'], function () {
        Route::get('/', [Client\Servers\SubuserController::class, 'index']);
        Route::middleware([ResourceLimit::Subuser->middleware()])
            ->post('/', [Client\Servers\SubuserController::class, 'store']);
        Route::get('/{user}', [Client\Servers\SubuserController::class, 'view']);
        Route::post('/{user}', [Client\Servers\SubuserController::class, 'update']);
        Route::delete('/{user}', [Client\Servers\SubuserController::class, 'delete']);
    });

    Route::group(['prefix' => '/backups'], function () {
        Route::get('/', [Client\Servers\BackupController::class, 'index']);
        Route::post('/', [Client\Servers\BackupController::class, 'store']);
        // Hydra: own SFTP server for backups (addon).
        Route::prefix('/sftp')->middleware('addon:sftp_backups')->group(function () {
            Route::get('/', [Client\Servers\SftpBackupController::class, 'show']);
            Route::put('/', [Client\Servers\SftpBackupController::class, 'update'])->middleware('throttle:10,1');
            Route::delete('/', [Client\Servers\SftpBackupController::class, 'delete']);
            Route::get('/files', [Client\Servers\SftpBackupController::class, 'files'])->middleware('throttle:20,1');
            Route::post('/import', [Client\Servers\SftpBackupController::class, 'import']);
        });
        Route::get('/{backup}', [Client\Servers\BackupController::class, 'view']);
        Route::get('/{backup}/download', [Client\Servers\BackupController::class, 'download']);
        Route::post('/{backup}/lock', [Client\Servers\BackupController::class, 'toggleLock']);
        Route::middleware([ResourceLimit::Backup->middleware()])
            ->post('/{backup}/restore', [Client\Servers\BackupController::class, 'restore']);
        Route::delete('/{backup}', [Client\Servers\BackupController::class, 'delete']);
    });

    // Hydra: import of another server over SFTP (addon "importer").
    Route::prefix('/import')->middleware('addon:importer')->group(function () {
        Route::get('/', [Client\Servers\ImportController::class, 'status']);
        Route::post('/test', [Client\Servers\ImportController::class, 'test'])->middleware('throttle:10,1');
        Route::post('/', [Client\Servers\ImportController::class, 'start'])->middleware('throttle:5,1');
        Route::delete('/', [Client\Servers\ImportController::class, 'cancel']);
    });

    // Hydra: game library (addon "games").
    Route::prefix('/games')->middleware('addon:games')->group(function () {
        Route::get('/', [Client\Servers\GameController::class, 'index']);
        Route::post('/switch', [Client\Servers\GameController::class, 'switch'])->middleware('throttle:5,1');
        Route::post('/profiles/{profile}/load', [Client\Servers\GameController::class, 'load'])->whereNumber('profile')->middleware('throttle:5,1');
        Route::delete('/profiles/{profile}', [Client\Servers\GameController::class, 'delete'])->whereNumber('profile');
    });

    // Hydra: Minecraft version changer (addon "versions").
    Route::prefix('/versions')->middleware('addon:versions')->group(function () {
        Route::get('/', [Client\Servers\MinecraftVersionController::class, 'index']);
        Route::get('/icons/{type}', [Client\Servers\MinecraftVersionController::class, 'icon'])->where('type', '[A-Z0-9_]+');
        Route::post('/install', [Client\Servers\MinecraftVersionController::class, 'install'])->middleware('throttle:5,1');
        Route::get('/{type}', [Client\Servers\MinecraftVersionController::class, 'versions'])->where('type', '[A-Z0-9_]+');
        Route::get('/{type}/{version}', [Client\Servers\MinecraftVersionController::class, 'builds'])
            ->where(['type' => '[A-Z0-9_]+', 'version' => '[A-Za-z0-9._+-]+']);
    });

    Route::group(['prefix' => '/startup'], function () {
        Route::get('/', [Client\Servers\StartupController::class, 'index']);
        Route::put('/variable', [Client\Servers\StartupController::class, 'update']);
        Route::put('/done', [Client\Servers\StartupController::class, 'done']);
        Route::put('/command', [Client\Servers\StartupController::class, 'command']);
    });

    Route::group(['prefix' => '/settings'], function () {
        Route::post('/rename', [Client\Servers\SettingsController::class, 'rename']);
        Route::post('/reinstall', [Client\Servers\SettingsController::class, 'reinstall']);
        Route::put('/docker-image', [Client\Servers\SettingsController::class, 'dockerImage']);
        Route::post('/lockdown', [Client\Servers\SettingsController::class, 'lockdown']);
    });
});

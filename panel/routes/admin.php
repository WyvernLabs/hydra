<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Admin;
use Pterodactyl\Http\Middleware\Admin\Servers\ServerInstalled;

Route::get('/', [Admin\BaseController::class, 'index'])->name('admin.index');

/*
|--------------------------------------------------------------------------
| Location Controller Routes
|--------------------------------------------------------------------------
|
| Endpoint: /admin/api
|
*/
Route::group(['prefix' => 'api'], function () {
    Route::get('/', [Admin\ApiController::class, 'index'])->name('admin.api.index')->middleware('admin.can:api.view');
    Route::get('/new', [Admin\ApiController::class, 'create'])->name('admin.api.new')->middleware('admin.can:api.create');

    Route::post('/new', [Admin\ApiController::class, 'store'])->middleware('admin.can:api.create');

    Route::delete('/revoke/{identifier}', [Admin\ApiController::class, 'delete'])->name('admin.api.delete')->middleware('admin.can:api.delete');
});

/*
|--------------------------------------------------------------------------
| Location Controller Routes
|--------------------------------------------------------------------------
|
| Endpoint: /admin/locations
|
*/
Route::group(['prefix' => 'locations'], function () {
    Route::get('/', [Admin\LocationController::class, 'index'])->name('admin.locations')->middleware('admin.can:locations.view');
    Route::get('/view/{location:id}', [Admin\LocationController::class, 'view'])->name('admin.locations.view')->middleware('admin.can:locations.view');

    Route::post('/', [Admin\LocationController::class, 'create'])->middleware('admin.can:locations.create');
    Route::patch('/view/{location:id}', [Admin\LocationController::class, 'update'])->middleware('admin.can:locations.update|locations.delete');
});

/*
|--------------------------------------------------------------------------
| Database Controller Routes
|--------------------------------------------------------------------------
|
| Endpoint: /admin/databases
|
*/
Route::group(['prefix' => 'databases'], function () {
    Route::get('/', [Admin\DatabaseController::class, 'index'])->name('admin.databases')->middleware('admin.can:databases.view');
    Route::get('/view/{host:id}', [Admin\DatabaseController::class, 'view'])->name('admin.databases.view')->middleware('admin.can:databases.view');

    Route::post('/', [Admin\DatabaseController::class, 'create'])->middleware('admin.can:databases.create');
    Route::patch('/view/{host:id}', [Admin\DatabaseController::class, 'update'])->middleware('admin.can:databases.update');
    Route::delete('/view/{host:id}', [Admin\DatabaseController::class, 'delete'])->middleware('admin.can:databases.delete');
});

/*
|--------------------------------------------------------------------------
| Settings Controller Routes
|--------------------------------------------------------------------------
|
| Endpoint: /admin/settings
|
*/
Route::group(['prefix' => 'settings'], function () {
    Route::get('/', [Admin\Settings\IndexController::class, 'index'])->name('admin.settings')->middleware('admin.can:settings.view');
    Route::get('/mail', [Admin\Settings\MailController::class, 'index'])->name('admin.settings.mail')->middleware('admin.can:settings.view');
    Route::get('/advanced', [Admin\Settings\AdvancedController::class, 'index'])->name('admin.settings.advanced')->middleware('admin.can:settings.view');
    Route::get('/appearance', [Admin\Settings\AppearanceController::class, 'index'])->name('admin.settings.appearance')->middleware('admin.can:settings.view');

    Route::post('/mail/test', [Admin\Settings\MailController::class, 'test'])->name('admin.settings.mail.test')->middleware('admin.can:settings.update');

    Route::patch('/', [Admin\Settings\IndexController::class, 'update'])->middleware('admin.can:settings.update');
    Route::patch('/mail', [Admin\Settings\MailController::class, 'update'])->middleware('admin.can:settings.update');
    Route::patch('/advanced', [Admin\Settings\AdvancedController::class, 'update'])->middleware('admin.can:settings.update');
    Route::patch('/appearance', [Admin\Settings\AppearanceController::class, 'update'])->middleware('admin.can:settings.update');
});

/*
|--------------------------------------------------------------------------
| User Controller Routes
|--------------------------------------------------------------------------
|
| Endpoint: /admin/users
|
*/
Route::group(['prefix' => 'users'], function () {
    Route::get('/', [Admin\UserController::class, 'index'])->name('admin.users')->middleware('admin.can:users.view');
    Route::get('/accounts.json', [Admin\UserController::class, 'json'])->name('admin.users.json')->middleware('admin.can:users.view|servers.create|servers.details');
    Route::get('/new', [Admin\UserController::class, 'create'])->name('admin.users.new')->middleware('admin.can:users.create');
    Route::get('/view/{user:id}', [Admin\UserController::class, 'view'])->name('admin.users.view')->middleware('admin.can:users.view');

    Route::post('/new', [Admin\UserController::class, 'store'])->middleware('admin.can:users.create');

    Route::patch('/view/{user:id}', [Admin\UserController::class, 'update'])->middleware('admin.can:users.update');
    Route::delete('/view/{user:id}/media/{type}', [Admin\UserController::class, 'removeMedia'])->name('admin.users.media.delete')->where('type', 'avatar|banner')->middleware('admin.can:users.update');
    Route::delete('/view/{user:id}', [Admin\UserController::class, 'delete'])->name('admin.users.delete')->middleware('admin.can:users.delete');
});

/*
|--------------------------------------------------------------------------
| Role Controller Routes (Hydra)
|--------------------------------------------------------------------------
|
| Endpoint: /admin/roles
|
*/
Route::group(['prefix' => 'roles'], function () {
    Route::get('/', [Admin\RoleController::class, 'index'])->name('admin.roles')->middleware('admin.can:roles.view');
    Route::get('/new', [Admin\RoleController::class, 'create'])->name('admin.roles.new')->middleware('admin.can:roles.create');
    Route::get('/view/{role:id}', [Admin\RoleController::class, 'view'])->name('admin.roles.view')->middleware('admin.can:roles.view');

    Route::post('/new', [Admin\RoleController::class, 'store'])->middleware('admin.can:roles.create');

    Route::patch('/view/{role:id}', [Admin\RoleController::class, 'update'])->middleware('admin.can:roles.update');
    Route::delete('/view/{role:id}', [Admin\RoleController::class, 'delete'])->name('admin.roles.delete')->middleware('admin.can:roles.delete');
});

/*
|--------------------------------------------------------------------------
| Server Controller Routes
|--------------------------------------------------------------------------
|
| Endpoint: /admin/servers
|
*/
Route::group(['prefix' => 'servers'], function () {
    Route::get('/', [Admin\Servers\ServerController::class, 'index'])->name('admin.servers')->middleware('admin.can:servers.view');
    Route::get('/new', [Admin\Servers\CreateServerController::class, 'index'])->name('admin.servers.new')->middleware('admin.can:servers.create');
    Route::get('/view/{server:id}', [Admin\Servers\ServerViewController::class, 'index'])->name('admin.servers.view')->middleware('admin.can:servers.view');

    Route::group(['middleware' => [ServerInstalled::class]], function () {
        Route::get('/view/{server:id}/details', [Admin\Servers\ServerViewController::class, 'details'])->name('admin.servers.view.details')->middleware('admin.can:servers.details');
        Route::get('/view/{server:id}/build', [Admin\Servers\ServerViewController::class, 'build'])->name('admin.servers.view.build')->middleware('admin.can:servers.build');
        Route::get('/view/{server:id}/startup', [Admin\Servers\ServerViewController::class, 'startup'])->name('admin.servers.view.startup')->middleware('admin.can:servers.startup');
        Route::get('/view/{server:id}/database', [Admin\Servers\ServerViewController::class, 'database'])->name('admin.servers.view.database')->middleware('admin.can:servers.databases');
        Route::get('/view/{server:id}/mounts', [Admin\Servers\ServerViewController::class, 'mounts'])->name('admin.servers.view.mounts')->middleware('admin.can:servers.mounts');
    });

    Route::get('/view/{server:id}/manage', [Admin\Servers\ServerViewController::class, 'manage'])->name('admin.servers.view.manage')->middleware('admin.can:servers.manage|servers.transfer');
    Route::get('/view/{server:id}/delete', [Admin\Servers\ServerViewController::class, 'delete'])->name('admin.servers.view.delete')->middleware('admin.can:servers.delete');

    Route::post('/new', [Admin\Servers\CreateServerController::class, 'store'])->middleware('admin.can:servers.create');
    Route::post('/view/{server:id}/build', [Admin\ServersController::class, 'updateBuild'])->middleware('admin.can:servers.build');
    Route::post('/view/{server:id}/startup', [Admin\ServersController::class, 'saveStartup'])->middleware('admin.can:servers.startup');
    Route::post('/view/{server:id}/database', [Admin\ServersController::class, 'newDatabase'])->middleware('admin.can:servers.databases');
    Route::post('/view/{server:id}/mounts', [Admin\ServersController::class, 'addMount'])->name('admin.servers.view.mounts.store')->middleware('admin.can:servers.mounts');
    Route::post('/view/{server:id}/manage/toggle', [Admin\ServersController::class, 'toggleInstall'])->name('admin.servers.view.manage.toggle')->middleware('admin.can:servers.manage');
    Route::post('/view/{server:id}/manage/suspension', [Admin\ServersController::class, 'manageSuspension'])->name('admin.servers.view.manage.suspension')->middleware('admin.can:servers.manage');
    Route::post('/view/{server:id}/manage/reinstall', [Admin\ServersController::class, 'reinstallServer'])->name('admin.servers.view.manage.reinstall')->middleware('admin.can:servers.manage');
    Route::post('/view/{server:id}/manage/transfer', [Admin\Servers\ServerTransferController::class, 'transfer'])->name('admin.servers.view.manage.transfer')->middleware('admin.can:servers.transfer');
    Route::post('/view/{server:id}/delete', [Admin\ServersController::class, 'delete'])->middleware('admin.can:servers.delete');

    Route::patch('/view/{server:id}/details', [Admin\ServersController::class, 'setDetails'])->middleware('admin.can:servers.details');
    Route::patch('/view/{server:id}/database', [Admin\ServersController::class, 'resetDatabasePassword'])->middleware('admin.can:servers.databases');

    Route::delete('/view/{server:id}/database/{database:id}/delete', [Admin\ServersController::class, 'deleteDatabase'])->name('admin.servers.view.database.delete')->middleware('admin.can:servers.databases');
    Route::delete('/view/{server:id}/mounts/{mount:id}', [Admin\ServersController::class, 'deleteMount'])
        ->name('admin.servers.view.mounts.delete')->middleware('admin.can:servers.mounts');
});

/*
|--------------------------------------------------------------------------
| Node Controller Routes
|--------------------------------------------------------------------------
|
| Endpoint: /admin/nodes
|
*/
Route::group(['prefix' => 'nodes'], function () {
    Route::get('/', [Admin\Nodes\NodeController::class, 'index'])->name('admin.nodes')->middleware('admin.can:nodes.view');
    Route::get('/new', [Admin\NodesController::class, 'create'])->name('admin.nodes.new')->middleware('admin.can:nodes.create');
    Route::get('/view/{node:id}', [Admin\Nodes\NodeViewController::class, 'index'])->name('admin.nodes.view')->middleware('admin.can:nodes.view');
    Route::get('/view/{node:id}/settings', [Admin\Nodes\NodeViewController::class, 'settings'])->name('admin.nodes.view.settings')->middleware('admin.can:nodes.update');
    Route::get('/view/{node:id}/configuration', [Admin\Nodes\NodeViewController::class, 'configuration'])->name('admin.nodes.view.configuration')->middleware('admin.can:nodes.configuration');
    Route::get('/view/{node:id}/allocation', [Admin\Nodes\NodeViewController::class, 'allocations'])->name('admin.nodes.view.allocation')->middleware('admin.can:nodes.allocations');
    Route::get('/view/{node:id}/servers', [Admin\Nodes\NodeViewController::class, 'servers'])->name('admin.nodes.view.servers')->middleware('admin.can:nodes.view');
    Route::get('/view/{node:id}/system-information', Admin\Nodes\SystemInformationController::class)->middleware('admin.can:nodes.view');

    Route::post('/new', [Admin\NodesController::class, 'store'])->middleware('admin.can:nodes.create');
    Route::post('/view/{node:id}/allocation', [Admin\NodesController::class, 'createAllocation'])->middleware('admin.can:nodes.allocations');
    Route::post('/view/{node:id}/allocation/remove', [Admin\NodesController::class, 'allocationRemoveBlock'])->name('admin.nodes.view.allocation.removeBlock')->middleware('admin.can:nodes.allocations');
    Route::post('/view/{node:id}/allocation/alias', [Admin\NodesController::class, 'allocationSetAlias'])->name('admin.nodes.view.allocation.setAlias')->middleware('admin.can:nodes.allocations');
    Route::post('/view/{node:id}/settings/token', Admin\NodeAutoDeployController::class)->name('admin.nodes.view.configuration.token')->middleware('admin.can:nodes.configuration');

    Route::patch('/view/{node:id}/settings', [Admin\NodesController::class, 'updateSettings'])->middleware('admin.can:nodes.update');

    Route::delete('/view/{node:id}/delete', [Admin\NodesController::class, 'delete'])->name('admin.nodes.view.delete')->middleware('admin.can:nodes.delete');
    Route::delete('/view/{node:id}/allocation/remove/{allocation:id}', [Admin\NodesController::class, 'allocationRemoveSingle'])->name('admin.nodes.view.allocation.removeSingle')->middleware('admin.can:nodes.allocations');
    Route::delete('/view/{node:id}/allocations', [Admin\NodesController::class, 'allocationRemoveMultiple'])->name('admin.nodes.view.allocation.removeMultiple')->middleware('admin.can:nodes.allocations');
});

/*
|--------------------------------------------------------------------------
| Mount Controller Routes
|--------------------------------------------------------------------------
|
| Endpoint: /admin/mounts
|
*/
Route::group(['prefix' => 'mounts'], function () {
    Route::get('/', [Admin\MountController::class, 'index'])->name('admin.mounts')->middleware('admin.can:mounts.view');
    Route::get('/view/{mount:id}', [Admin\MountController::class, 'view'])->name('admin.mounts.view')->middleware('admin.can:mounts.view');

    Route::post('/', [Admin\MountController::class, 'create'])->middleware('admin.can:mounts.create');
    Route::post('/{mount:id}/eggs', [Admin\MountController::class, 'addEggs'])->name('admin.mounts.eggs')->middleware('admin.can:mounts.update');
    Route::post('/{mount:id}/nodes', [Admin\MountController::class, 'addNodes'])->name('admin.mounts.nodes')->middleware('admin.can:mounts.update');

    Route::patch('/view/{mount:id}', [Admin\MountController::class, 'update'])->middleware('admin.can:mounts.update|mounts.delete');

    Route::delete('/{mount:id}/eggs/{egg_id}', [Admin\MountController::class, 'deleteEgg'])->middleware('admin.can:mounts.update');
    Route::delete('/{mount:id}/nodes/{node_id}', [Admin\MountController::class, 'deleteNode'])->middleware('admin.can:mounts.update');
});

/*
|--------------------------------------------------------------------------
| Nest Controller Routes
|--------------------------------------------------------------------------
|
| Endpoint: /admin/nests
|
*/
Route::group(['prefix' => 'nests'], function () {
    Route::get('/', [Admin\Nests\NestController::class, 'index'])->name('admin.nests')->middleware('admin.can:nests.view');
    Route::get('/new', [Admin\Nests\NestController::class, 'create'])->name('admin.nests.new')->middleware('admin.can:nests.create');
    Route::get('/view/{nest:id}', [Admin\Nests\NestController::class, 'view'])->name('admin.nests.view')->middleware('admin.can:nests.view');
    Route::get('/egg/new', [Admin\Nests\EggController::class, 'create'])->name('admin.nests.egg.new')->middleware('admin.can:eggs.create');
    Route::get('/egg/{egg:id}', [Admin\Nests\EggController::class, 'view'])->name('admin.nests.egg.view')->middleware('admin.can:eggs.view');
    Route::get('/egg/{egg:id}/export', [Admin\Nests\EggShareController::class, 'export'])->name('admin.nests.egg.export')->middleware('admin.can:eggs.view');
    Route::get('/egg/{egg:id}/variables', [Admin\Nests\EggVariableController::class, 'view'])->name('admin.nests.egg.variables')->middleware('admin.can:eggs.view');
    Route::get('/egg/{egg:id}/scripts', [Admin\Nests\EggScriptController::class, 'index'])->name('admin.nests.egg.scripts')->middleware('admin.can:eggs.view');

    Route::post('/new', [Admin\Nests\NestController::class, 'store'])->middleware('admin.can:nests.create');
    Route::post('/import', [Admin\Nests\EggShareController::class, 'import'])->name('admin.nests.egg.import')->middleware('admin.can:eggs.import');
    Route::post('/egg/new', [Admin\Nests\EggController::class, 'store'])->middleware('admin.can:eggs.create');
    Route::post('/egg/{egg:id}/variables', [Admin\Nests\EggVariableController::class, 'store'])->middleware('admin.can:eggs.update');

    Route::put('/egg/{egg:id}', [Admin\Nests\EggShareController::class, 'update'])->middleware('admin.can:eggs.import');

    Route::patch('/view/{nest:id}', [Admin\Nests\NestController::class, 'update'])->middleware('admin.can:nests.update');
    Route::patch('/egg/{egg:id}', [Admin\Nests\EggController::class, 'update'])->middleware('admin.can:eggs.update');
    Route::patch('/egg/{egg:id}/scripts', [Admin\Nests\EggScriptController::class, 'update'])->middleware('admin.can:eggs.update');
    Route::patch('/egg/{egg:id}/variables/{variable:id}', [Admin\Nests\EggVariableController::class, 'update'])->name('admin.nests.egg.variables.edit')->middleware('admin.can:eggs.update');

    Route::delete('/view/{nest:id}', [Admin\Nests\NestController::class, 'destroy'])->middleware('admin.can:nests.delete');
    Route::delete('/egg/{egg:id}', [Admin\Nests\EggController::class, 'destroy'])->middleware('admin.can:eggs.delete');
    Route::delete('/egg/{egg:id}/variables/{variable:id}', [Admin\Nests\EggVariableController::class, 'destroy'])->middleware('admin.can:eggs.update');
});

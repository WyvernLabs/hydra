<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Database;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Pterodactyl\Services\Servers\SuspensionService;
use Pterodactyl\Services\Servers\ServerDeletionService;
use Pterodactyl\Services\Servers\ReinstallServerService;
use Pterodactyl\Exceptions\Model\DataValidationException;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Services\Servers\BuildModificationService;
use Pterodactyl\Services\Databases\DatabasePasswordService;
use Pterodactyl\Services\Servers\DetailsModificationService;
use Pterodactyl\Services\Servers\StartupModificationService;
use Pterodactyl\Contracts\Repository\NestRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\ServerDetailsFormRequest;
use Pterodactyl\Repositories\Eloquent\DatabaseHostRepository;
use Pterodactyl\Services\Databases\DatabaseManagementService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Pterodactyl\Contracts\Repository\ServerRepositoryInterface;
use Pterodactyl\Contracts\Repository\DatabaseRepositoryInterface;
use Pterodactyl\Services\Hydra\Backups\BackupStorage;
use Pterodactyl\Contracts\Repository\AllocationRepositoryInterface;
use Pterodactyl\Services\Servers\ServerConfigurationStructureService;
use Pterodactyl\Http\Requests\Admin\Servers\Databases\StoreServerDatabaseRequest;

class ServersController extends Controller
{
    /**
     * ServersController constructor.
     */
    public function __construct(
        protected AlertsMessageBag $alert,
        protected AllocationRepositoryInterface $allocationRepository,
        protected BuildModificationService $buildModificationService,
        protected ConfigRepository $config,
        protected DaemonServerRepository $daemonServerRepository,
        protected DatabaseManagementService $databaseManagementService,
        protected DatabasePasswordService $databasePasswordService,
        protected DatabaseRepositoryInterface $databaseRepository,
        protected DatabaseHostRepository $databaseHostRepository,
        protected ServerDeletionService $deletionService,
        protected DetailsModificationService $detailsModificationService,
        protected ReinstallServerService $reinstallService,
        protected ServerRepositoryInterface $repository,
        protected NestRepositoryInterface $nestRepository,
        protected ServerConfigurationStructureService $serverConfigurationStructureService,
        protected StartupModificationService $startupModificationService,
        protected SuspensionService $suspensionService,
    ) {
    }

    /**
     * Update the details for a server.
     *
     * @throws DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function setDetails(ServerDetailsFormRequest $request, Server $server): RedirectResponse
    {
        $this->detailsModificationService->handle($server, $request->normalize([
            'owner_id', 'external_id', 'name', 'description',
        ]));

        $this->alert->success(trans('admin/server.alerts.details_updated'))->flash();

        return redirect()->route('admin.servers.view.details', $server->id);
    }

    /**
     * Toggles the installation status for a server.
     *
     * @throws DisplayException
     * @throws DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function toggleInstall(Server $server): RedirectResponse
    {
        if ($server->status === Server::STATUS_INSTALL_FAILED) {
            throw new DisplayException(trans('admin/server.exceptions.marked_as_failed'));
        }

        $this->repository->update($server->id, [
            'status' => $server->isInstalled() ? Server::STATUS_INSTALLING : null,
        ], true, true);

        $this->alert->success(trans('admin/server.alerts.install_toggled'))->flash();

        return redirect()->route('admin.servers.view.manage', $server->id);
    }

    /**
     * Reinstalls the server with the currently assigned service.
     *
     * @throws DisplayException
     * @throws DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function reinstallServer(Server $server): RedirectResponse
    {
        $this->reinstallService->handle($server);
        $this->alert->success(trans('admin/server.alerts.server_reinstalled'))->flash();

        return redirect()->route('admin.servers.view.manage', $server->id);
    }

    /**
     * Manage the suspension status for a server.
     *
     * @throws DisplayException
     * @throws DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function manageSuspension(Request $request, Server $server): RedirectResponse
    {
        $this->suspensionService->toggle($server, $request->input('action'));
        $this->alert->success(trans('admin/server.alerts.suspension_toggled', [
            'status' => $request->input('action') . 'ed',
        ]))->flash();

        return redirect()->route('admin.servers.view.manage', $server->id);
    }

    /**
     * Update the build configuration for a server.
     *
     * @throws DisplayException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     * @throws ValidationException
     */
    public function updateBuild(Request $request, Server $server): RedirectResponse
    {
        try {
            $this->buildModificationService->handle($server, $request->only([
                'allocation_id', 'add_allocations', 'remove_allocations',
                'memory', 'swap', 'io', 'cpu', 'threads', 'disk',
                'database_limit', 'allocation_limit', 'backup_limit', 'oom_disabled',
            ]) + ($request->has('hydra_limits') ? [
                // Hydra: the owner picks the ports; empty limits use the addon defaults.
                'hydra_dedicated' => $request->boolean('hydra_dedicated'),
                'hydra_subdomain_limit' => $request->filled('hydra_subdomain_limit') ? max(0, $request->integer('hydra_subdomain_limit')) : null,
                'hydra_proxy_limit' => $request->filled('hydra_proxy_limit') ? max(0, $request->integer('hydra_proxy_limit')) : null,
            ] : []) + ($request->has('backup_storage_gb') ? [
                // Hydra: the backup space is entered in GB.
                'backup_storage_limit' => BackupStorage::mbFromGb($request->input('backup_storage_gb')),
            ] : []));
        } catch (DataValidationException $exception) {
            throw new ValidationException($exception->getValidator());
        }

        $this->alert->success(trans('admin/server.alerts.build_updated'))->flash();

        return redirect()->route('admin.servers.view.build', $server->id);
    }

    /**
     * Start the server deletion process.
     *
     * @throws DisplayException
     * @throws \Throwable
     */
    public function delete(Request $request, Server $server): RedirectResponse
    {
        $this->deletionService->withForce($request->filled('force_delete'))->handle($server);
        $this->alert->success(trans('admin/server.alerts.server_deleted'))->flash();

        return redirect()->route('admin.servers');
    }

    /**
     * Update the startup command as well as variables.
     *
     * @throws ValidationException
     */
    public function saveStartup(Request $request, Server $server): RedirectResponse
    {
        $data = $request->except('_token');
        if (!empty($data['custom_docker_image'])) {
            $data['docker_image'] = $data['custom_docker_image'];
            unset($data['custom_docker_image']);
        }

        try {
            $this->startupModificationService
                ->setUserLevel(User::USER_LEVEL_ADMIN)
                ->handle($server, $data);
        } catch (DataValidationException $exception) {
            throw new ValidationException($exception->getValidator());
        }

        $this->alert->success(trans('admin/server.alerts.startup_changed'))->flash();

        return redirect()->route('admin.servers.view.startup', $server->id);
    }

    /**
     * Creates a new database assigned to a specific server.
     *
     * @throws \Throwable
     */
    public function newDatabase(StoreServerDatabaseRequest $request, Server $server): RedirectResponse
    {
        $this->databaseManagementService->create($server, [
            'database' => DatabaseManagementService::generateUniqueDatabaseName($request->input('database'), $server->id),
            'remote' => $request->input('remote'),
            'database_host_id' => $request->input('database_host_id'),
            'max_connections' => $request->input('max_connections'),
        ]);

        return redirect()->route('admin.servers.view.database', $server->id)->withInput();
    }

    /**
     * Resets the database password for a specific database on this server.
     *
     * @throws \Throwable
     */
    public function resetDatabasePassword(Request $request, Server $server): Response
    {
        /** @var Database $database */
        $database = $server->databases()->findOrFail($request->input('database'));

        $this->databasePasswordService->handle($database);

        return response('', 204);
    }

    /**
     * Deletes a database from a server.
     *
     * @throws \Exception
     */
    public function deleteDatabase(Server $server, Database $database): Response
    {
        $this->databaseManagementService->delete($database);

        return response('', 204);
    }
}

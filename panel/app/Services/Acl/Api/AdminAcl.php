<?php

namespace Pterodactyl\Services\Acl\Api;

use Pterodactyl\Models\User;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Http\Controllers\Api\Application\Nodes\NodeController;
use Pterodactyl\Http\Controllers\Api\Application\Users\UserController;
use Pterodactyl\Http\Controllers\Api\Application\Users\SanctionController;
use Pterodactyl\Http\Controllers\Api\Application\Servers\ServerController;
use Pterodactyl\Http\Controllers\Api\Application\Servers\StartupController;
use Pterodactyl\Http\Controllers\Api\Application\Locations\LocationController;
use Pterodactyl\Http\Controllers\Api\Application\Servers\ServerDetailsController;
use Pterodactyl\Http\Controllers\Api\Application\Servers\ServerManagementController;

class AdminAcl
{
    /**
     * Resource permission columns in the api_keys table begin
     * with this identifier.
     */
    public const COLUMN_IDENTIFIER = 'r_';

    /**
     * The different types of permissions available for API keys. This
     * implements a read/write/none permissions scheme for all endpoints.
     */
    public const NONE = 0;
    public const READ = 1;
    public const WRITE = 2;

    /**
     * Resources that are available on the API and can contain a permissions
     * set for each key. These are stored in the database as r_{resource}.
     */
    public const RESOURCE_SERVERS = 'servers';
    public const RESOURCE_NODES = 'nodes';
    public const RESOURCE_ALLOCATIONS = 'allocations';
    public const RESOURCE_USERS = 'users';
    public const RESOURCE_LOCATIONS = 'locations';
    public const RESOURCE_NESTS = 'nests';
    public const RESOURCE_EGGS = 'eggs';
    public const RESOURCE_DATABASE_HOSTS = 'database_hosts';
    public const RESOURCE_SERVER_DATABASES = 'server_databases';

    /**
     * Hydra: the admin permissions the owner of a key needs for reading and
     * writing each resource. A key can never do more than its owner.
     */
    public const ADMIN_PERMISSIONS = [
        self::RESOURCE_SERVERS => [
            self::READ => ['servers.view'],
            self::WRITE => ['servers.create', 'servers.details', 'servers.build', 'servers.startup', 'servers.manage', 'servers.delete'],
        ],
        self::RESOURCE_NODES => [
            self::READ => ['nodes.view'],
            self::WRITE => ['nodes.create', 'nodes.update', 'nodes.delete'],
        ],
        self::RESOURCE_ALLOCATIONS => [
            self::READ => ['nodes.view'],
            self::WRITE => ['nodes.allocations'],
        ],
        self::RESOURCE_USERS => [
            self::READ => ['users.view'],
            self::WRITE => ['users.create', 'users.update', 'users.delete', 'users.warn', 'users.ban'],
        ],
        self::RESOURCE_LOCATIONS => [
            self::READ => ['locations.view'],
            self::WRITE => ['locations.create', 'locations.update', 'locations.delete'],
        ],
        self::RESOURCE_NESTS => [
            self::READ => ['nests.view'],
            self::WRITE => ['nests.create', 'nests.update', 'nests.delete'],
        ],
        self::RESOURCE_EGGS => [
            self::READ => ['eggs.view'],
            self::WRITE => ['eggs.create', 'eggs.update', 'eggs.delete'],
        ],
        self::RESOURCE_DATABASE_HOSTS => [
            self::READ => ['databases.view'],
            self::WRITE => ['databases.create', 'databases.update', 'databases.delete'],
        ],
        self::RESOURCE_SERVER_DATABASES => [
            self::READ => ['servers.view'],
            self::WRITE => ['servers.databases'],
        ],
    ];

    /**
     * Hydra: write endpoints that only need the admin permission of their own
     * action instead of every write permission of the resource. So a role with
     * "servers.manage" can suspend servers over the API without being allowed
     * to delete them. Endpoints that are not listed need all of them.
     */
    public const ENDPOINT_PERMISSIONS = [
        ServerController::class . '@store' => ['servers.create'],
        ServerController::class . '@delete' => ['servers.delete'],
        ServerDetailsController::class . '@details' => ['servers.details'],
        ServerDetailsController::class . '@build' => ['servers.build'],
        StartupController::class . '@index' => ['servers.startup'],
        ServerManagementController::class . '@suspend' => ['servers.manage'],
        ServerManagementController::class . '@unsuspend' => ['servers.manage'],
        ServerManagementController::class . '@reinstall' => ['servers.manage'],
        UserController::class . '@store' => ['users.create'],
        UserController::class . '@update' => ['users.update'],
        UserController::class . '@delete' => ['users.delete'],
        SanctionController::class . '@warn' => ['users.warn'],
        SanctionController::class . '@ban' => ['users.ban'],
        SanctionController::class . '@lift' => ['users.ban'],
        NodeController::class . '@store' => ['nodes.create'],
        NodeController::class . '@update' => ['nodes.update'],
        NodeController::class . '@delete' => ['nodes.delete'],
        LocationController::class . '@store' => ['locations.create'],
        LocationController::class . '@update' => ['locations.update'],
        LocationController::class . '@delete' => ['locations.delete'],
    ];

    /**
     * Determine if an API key has permission to perform a specific read/write operation.
     */
    public static function can(int $permission, int $action = self::READ): bool
    {
        if ($permission & $action) {
            return true;
        }

        return false;
    }

    /**
     * Determine if an API Key model has permission to access a given resource
     * at a specific action level.
     */
    public static function check(ApiKey $key, string $resource, int $action = self::READ, ?string $endpoint = null): bool
    {
        if ($key->user && !self::userCan($key->user, $resource, $action, $endpoint)) {
            return false;
        }

        return self::can(data_get($key, self::COLUMN_IDENTIFIER . $resource, self::NONE), $action);
    }

    /**
     * Hydra: whether the user's admin permissions allow the action on the
     * resource; with the endpoint ("Controller@method") only what it needs.
     */
    public static function userCan(User $user, string $resource, int $action = self::READ, ?string $endpoint = null): bool
    {
        if ($user->root_admin) {
            return true;
        }

        $needed = self::neededFor($resource, $action, $endpoint);
        if (is_null($needed)) {
            return false;
        }

        return collect($needed)->every(fn (string $permission) => $user->hasAdminPermission($permission));
    }

    /**
     * Hydra: the admin permissions an endpoint needs, null if it cannot be used.
     *
     * @return string[]|null
     */
    public static function neededFor(string $resource, int $action, ?string $endpoint = null): ?array
    {
        if ($action === self::WRITE && !is_null($endpoint) && isset(self::ENDPOINT_PERMISSIONS[$endpoint])) {
            return self::ENDPOINT_PERMISSIONS[$endpoint];
        }

        return self::ADMIN_PERMISSIONS[$resource][$action] ?? null;
    }

    /**
     * Hydra: the highest access level for the resource the user may put on a
     * key. Write access only needs one of the write permissions, each endpoint
     * still checks its own one.
     */
    public static function maxFor(User $user, string $resource): int
    {
        if (!self::userCan($user, $resource, self::READ)) {
            return self::NONE;
        }

        $write = $user->root_admin || collect(self::ADMIN_PERMISSIONS[$resource][self::WRITE] ?? [])
            ->contains(fn (string $permission) => $user->hasAdminPermission($permission));

        return $write ? self::READ | self::WRITE : self::READ;
    }

    /**
     * Return a list of all resource constants defined in this ACL.
     *
     * @throws \ReflectionException
     */
    public static function getResourceList(): array
    {
        $reflect = new \ReflectionClass(__CLASS__);

        return collect($reflect->getConstants())->filter(function ($value, $key) {
            return substr($key, 0, 9) === 'RESOURCE_';
        })->values()->toArray();
    }
}

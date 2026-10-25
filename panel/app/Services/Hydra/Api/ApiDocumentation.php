<?php

namespace Pterodactyl\Services\Hydra\Api;

use Illuminate\Support\Str;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Pterodactyl\Services\Acl\Api\AdminAcl;
use Illuminate\Foundation\Http\FormRequest;
use Pterodactyl\Http\Requests\Api\Application\ApplicationApiRequest;

/**
 * Hydra: documentation of the application and client API (Admin > API >
 * Documentation), read from the routes, so new endpoints show up by
 * themselves. Method, path, required permission and the fields of the form
 * request come from the code; descriptions and group titles from
 * lang/<locale>/admin/api_docs.php ("endpoints" keyed by "METHOD path").
 */
class ApiDocumentation
{
    public const APIS = ['application', 'client'];

    public function __construct(private Router $router)
    {
    }

    /**
     * Endpoints of an API grouped by area, in the order of the route files.
     *
     * @return array<string, array<int, array{method: string, path: string, key: string, description: ?string, permission: ?string, fields: array}>>
     */
    public function groups(string $api): array
    {
        $groups = [];
        foreach ($this->endpoints($api) as $endpoint) {
            $groups[$this->group($api, Str::after($endpoint['key'], ' '))][] = $endpoint;
        }

        // In the order of the lang file, unknown groups at the end.
        $order = array_flip(array_keys((array) trans("admin/api_docs.groups.$api")));
        uksort($groups, fn (string $a, string $b) => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        return $groups;
    }

    /**
     * @return array<int, array{method: string, path: string, key: string, description: ?string, permission: ?string, fields: array}>
     */
    public function endpoints(string $api): array
    {
        $prefix = "api/$api";
        $descriptions = trans('admin/api_docs.endpoints.' . $api);
        $descriptions = is_array($descriptions) ? $descriptions : [];

        $endpoints = [];
        /** @var Route $route */
        foreach ($this->router->getRoutes() as $route) {
            $uri = $route->uri();
            if ($uri !== $prefix && !str_starts_with($uri, "$prefix/")) {
                continue;
            }
            $path = ltrim(substr($uri, strlen($prefix)), '/');
            [$request, $permission] = $this->request($route, $api);

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $key = "$method $path";
                $endpoints[] = [
                    'method' => $method,
                    'path' => '/' . $uri,
                    'key' => $key,
                    'description' => $descriptions[$key] ?? null,
                    'permission' => $permission,
                    'fields' => $this->fields($request),
                ];
            }
        }

        return $endpoints;
    }

    /**
     * Area of an endpoint, e.g. "servers/files" for /servers/{server}/files/list.
     */
    public function group(string $api, string $path): string
    {
        $parts = explode('/', ltrim($path, '/'));
        if ($api === 'client' && $parts[0] === 'servers') {
            $area = $parts[2] ?? '';

            return match (true) {
                in_array($area, ['command', 'power', 'resources', 'websocket'], true) => 'servers/console',
                in_array($area, ['', 'activity', 'players'], true) => 'servers',
                default => "servers/$area",
            };
        }
        if ($api === 'client' && $parts[0] === 'account' && in_array($parts[1] ?? '', ['pool', 'tickets', 'api-keys', 'ssh-keys'], true)) {
            return 'account/' . $parts[1];
        }

        return in_array($parts[0], ['', 'permissions', 'pages'], true) ? 'general' : $parts[0];
    }

    /**
     * Form request of the controller method and the permission it checks.
     *
     * @return array{0: ?FormRequest, 1: ?string}
     */
    private function request(Route $route, string $api): array
    {
        $action = $route->getActionName();
        [$class, $method] = str_contains($action, '@') ? explode('@', $action, 2) : [$action, '__invoke'];
        if (!class_exists($class) || !method_exists($class, $method)) {
            return [null, null];
        }

        foreach ((new \ReflectionMethod($class, $method))->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof \ReflectionNamedType || !is_subclass_of($type->getName(), FormRequest::class)) {
                continue;
            }

            try {
                /** @var FormRequest $request */
                $request = (new \ReflectionClass($type->getName()))->newInstanceWithoutConstructor();
            } catch (\Throwable) {
                return [null, null];
            }

            return [$request, $api === 'application' ? $this->aclPermission($request, "$class@$method") : $this->clientPermission($request)];
        }

        return [null, null];
    }

    /**
     * E.g. "servers: read" for a request of the application API, with the admin
     * permission of the endpoint for write endpoints ("servers: read & write ·
     * servers.manage").
     */
    private function aclPermission(FormRequest $request, string $endpoint): ?string
    {
        if (!$request instanceof ApplicationApiRequest) {
            return null;
        }

        try {
            $reflection = new \ReflectionObject($request);
            $resource = $reflection->getProperty('resource');
            $level = $reflection->getProperty('permission');
            if (!$resource->isInitialized($request) || is_null($resource->getValue($request))) {
                return null;
            }

            $write = $level->getValue($request) >= AdminAcl::WRITE;
            $text = $resource->getValue($request) . ': ' . trans('admin/api_docs.access.' . ($write ? 'write' : 'read'));
            $admin = $write ? (AdminAcl::ENDPOINT_PERMISSIONS[$endpoint] ?? null) : null;

            return $admin ? $text . ' · ' . implode(', ', $admin) : $text;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The subuser permission a request of the client API checks, e.g. "file.read".
     */
    private function clientPermission(FormRequest $request): ?string
    {
        if (!method_exists($request, 'permission')) {
            return null;
        }

        try {
            $permission = $request->permission();

            return is_string($permission) && $permission !== '' ? $permission : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Fields of the form request with their validation rules, as far as the
     * rules can be read without a real request.
     *
     * @return array<string, string>
     */
    private function fields(?FormRequest $request): array
    {
        if (!$request || !method_exists($request, 'rules')) {
            return [];
        }

        try {
            $rules = $request->rules();
        } catch (\Throwable) {
            return [];
        }
        if (!is_array($rules)) {
            return [];
        }

        $fields = [];
        foreach ($rules as $field => $rule) {
            $parts = is_string($rule) ? explode('|', $rule) : (is_array($rule) ? $rule : [$rule]);
            $fields[$field] = implode(' | ', array_map(fn ($part) => $this->rule($part), $parts));
        }

        return $fields;
    }

    private function rule(mixed $rule): string
    {
        if (is_string($rule)) {
            // Long regular expressions say little in a table.
            return str_starts_with($rule, 'regex:') ? 'regex' : Str::limit($rule, 60);
        }
        if ($rule instanceof \Stringable) {
            return Str::limit((string) $rule, 60);
        }

        return is_object($rule) ? Str::snake(class_basename($rule)) : 'rule';
    }
}

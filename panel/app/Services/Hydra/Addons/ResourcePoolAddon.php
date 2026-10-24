<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Models\HydraResourcePool;

/**
 * Hydra: users get a budget of resources (cores, memory, disk, databases,
 * ports, backup space, subdomains, proxies and servers) on their page in the
 * admin area and create, resize and delete their own servers with it on the
 * dashboard. Which eggs and locations they may pick is set here.
 */
class ResourcePoolAddon extends Addon
{
    public function key(): string
    {
        return 'resource_pool';
    }

    public function icon(): string
    {
        return 'boxes';
    }

    public function rules(): array
    {
        return [
            'eggs' => 'nullable|array',
            'eggs.*' => 'integer|exists:eggs,id',
            'locations' => 'nullable|array',
            'locations.*' => 'integer|exists:locations,id',
        ];
    }

    public function booleans(): array
    {
        return ['user_edit', 'user_delete'];
    }

    public function prepare(array $values): array
    {
        foreach (['eggs', 'locations'] as $list) {
            $values[$list] = array_values(array_unique(array_map('intval', $values[$list] ?? [])));
        }

        return $values;
    }

    public function summary(): array
    {
        $eggs = $this->eggIds();

        return [
            trans('admin/addons.resource_pool.summary_pools', ['count' => HydraResourcePool::query()->count()]),
            empty($eggs)
                ? trans('admin/addons.resource_pool.summary_all_eggs')
                : trans('admin/addons.resource_pool.summary_eggs', ['count' => count($eggs)]),
        ];
    }

    /**
     * Eggs users may pick, empty for every egg.
     *
     * @return int[]
     */
    public function eggIds(): array
    {
        return $this->list('eggs');
    }

    /**
     * Locations users may pick, empty for every location.
     *
     * @return int[]
     */
    public function locationIds(): array
    {
        return $this->list('locations');
    }

    public function userMayEdit(): bool
    {
        return (bool) $this->get('user_edit', true);
    }

    public function userMayDelete(): bool
    {
        return (bool) $this->get('user_delete', true);
    }

    /**
     * @return int[]
     */
    private function list(string $setting): array
    {
        $ids = $this->get($setting, []);
        if (is_string($ids)) {
            $ids = json_decode($ids, true);
        }

        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }
}

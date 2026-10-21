<?php

namespace Pterodactyl\Http\Controllers\Admin\Design;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\PhpMyAdminService;
use Pterodactyl\Services\Hydra\Addons\AddonManager;
use Pterodactyl\Services\Hydra\Addons\PhpMyAdminAddon;

/**
 * Hydra: installs and removes phpMyAdmin on the panel server (Design > Addons
 * > phpMyAdmin).
 */
class PhpMyAdminController extends Controller
{
    public function __construct(private PhpMyAdminService $service, private AddonManager $manager)
    {
    }

    public function status(): JsonResponse
    {
        return new JsonResponse($this->service->toArray());
    }

    public function install(): JsonResponse
    {
        return $this->request('install');
    }

    public function uninstall(): JsonResponse
    {
        // Users are no longer offered a phpMyAdmin that is going away.
        $this->manager->setEnabled(app(PhpMyAdminAddon::class), false);

        return $this->request('uninstall');
    }

    private function request(string $action): JsonResponse
    {
        if (!$this->service->isAvailable()) {
            return new JsonResponse(['error' => __('admin/addons.phpmyadmin.not_available_short')], JsonResponse::HTTP_CONFLICT);
        }
        if ($this->service->isBusy()) {
            return new JsonResponse(['error' => __('admin/addons.phpmyadmin.busy')], JsonResponse::HTTP_CONFLICT);
        }

        $this->service->request($action);

        return new JsonResponse([], JsonResponse::HTTP_ACCEPTED);
    }
}

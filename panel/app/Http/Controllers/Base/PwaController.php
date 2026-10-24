<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Services\Hydra\PwaService;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\Addons\PwaAddon;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: manifest and icons of the installable app (addon "pwa"). Browsers
 * load them without the session, so they are public.
 */
class PwaController extends Controller
{
    public function __construct(private PwaAddon $addon, private PwaService $service)
    {
    }

    public function manifest(): JsonResponse
    {
        if (!$this->addon->enabled()) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse($this->service->manifest(), 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'no-cache',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function icon(string $type, int $size): Response
    {
        if (!$this->addon->enabled() || !in_array($size, PwaService::SIZES, true)) {
            throw new NotFoundHttpException();
        }

        return response()->file($this->service->icon($size, $type === 'maskable'), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}

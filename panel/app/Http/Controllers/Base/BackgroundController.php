<?php

namespace Pterodactyl\Http\Controllers\Base;

use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;
use Pterodactyl\Services\Hydra\BackgroundService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: serves the uploaded backgrounds. They are needed on the login page,
 * so no login is required.
 */
class BackgroundController extends Controller
{
    public function __invoke(string $file): Response
    {
        $path = BackgroundService::path($file);
        if (is_null($path)) {
            throw new NotFoundHttpException();
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return response()->file($path, [
            'Content-Type' => array_search($extension, BackgroundService::MIME_TYPES, true) ?: 'application/octet-stream',
            // Every upload gets a new file name, so a file never changes.
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}

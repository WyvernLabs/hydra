<?php

namespace Pterodactyl\Http\Controllers\Base;

use Pterodactyl\Models\User;
use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;
use Pterodactyl\Services\Users\ProfileMediaService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: serves profile pictures and banners to logged in users.
 */
class ProfileMediaController extends Controller
{
    public function __invoke(string $uuid, string $type): Response
    {
        $user = User::query()->where('uuid', $uuid)->first();
        $path = $user ? ProfileMediaService::path($user, $type) : null;
        if (is_null($path)) {
            throw new NotFoundHttpException();
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return response()->file($path, [
            'Content-Type' => array_search($extension, ProfileMediaService::MIME_TYPES, true) ?: 'application/octet-stream',
            // The URL changes with every upload, so the file never changes.
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}

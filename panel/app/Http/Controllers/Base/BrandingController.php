<?php

namespace Pterodactyl\Http\Controllers\Base;

use Symfony\Component\HttpFoundation\Response;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\BrandingService;

class BrandingController extends Controller
{
    public function __construct(private BrandingService $branding)
    {
    }

    /**
     * Serves the uploaded logo or favicon. Falls back to the bundled default
     * so that the URL keeps working after an image was removed.
     */
    public function __invoke(string $type): Response
    {
        $path = $this->branding->path($type);
        if (is_null($path)) {
            return redirect($type === 'logo' ? BrandingService::DEFAULT_LOGO : BrandingService::DEFAULT_FAVICON);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return response()->file($path, [
            'Content-Type' => BrandingService::MIME_TYPES[$extension] ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            // Uploaded SVGs must never be able to run scripts on the panel origin.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:",
        ]);
    }
}

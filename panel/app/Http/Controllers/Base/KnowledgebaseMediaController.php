<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\Request;
use Pterodactyl\Models\HydraKbMedia;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\Addons\KnowledgebaseAddon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: pictures and videos of the knowledgebase. Visible like the
 * knowledgebase itself (everyone when it is public, otherwise users who are
 * logged in). Videos support range requests for seeking.
 */
class KnowledgebaseMediaController extends Controller
{
    public function __invoke(Request $request, KnowledgebaseAddon $addon, string $uuid, string $extension): BinaryFileResponse
    {
        if (!$request->user() && !$addon->isPublic()) {
            throw new NotFoundHttpException();
        }

        /** @var HydraKbMedia|null $media */
        $media = HydraKbMedia::query()->where('uuid', $uuid)->first();
        $disk = Storage::disk('local');
        if (is_null($media) || !in_array($media->mime, HydraKbMedia::mimes(), true) || !$disk->exists($media->path())) {
            throw new NotFoundHttpException();
        }

        // Only files of the allowed types were stored (checked on upload).
        return response()->file($disk->path($media->path()), [
            'Content-Type' => $media->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            'Cache-Control' => ($addon->isPublic() ? 'public' : 'private') . ', max-age=604800',
        ]);
    }
}

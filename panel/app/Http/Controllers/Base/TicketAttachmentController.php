<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Models\TicketAttachment;
use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Pterodactyl\Services\Hydra\Tickets\TicketService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: images of tickets, only for the uploader, the owner of the ticket
 * and the team.
 */
class TicketAttachmentController extends Controller
{
    public function __invoke(Request $request, TicketService $service, string $uuid): StreamedResponse
    {
        /** @var TicketAttachment|null $file */
        $file = TicketAttachment::query()->where('uuid', $uuid)->with('ticket', 'message')->first();
        $disk = Storage::disk('local');

        if (is_null($file) || !$service->canView($request->user(), $file) || !$disk->exists($file->path())) {
            throw new NotFoundHttpException();
        }

        // Only images that were checked on upload are ever stored.
        return $disk->response($file->path(), $file->name, [
            'Content-Type' => in_array($file->mime, TicketAttachment::MIMES, true) ? $file->mime : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }
}

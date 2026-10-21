<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\UserSanction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: users confirm that they have read a warning, which hides it.
 */
class WarningController extends ClientApiController
{
    public function acknowledge(Request $request, int $warning): JsonResponse
    {
        $sanction = UserSanction::query()
            ->where('id', $warning)
            ->where('user_id', $request->user()->id)
            ->where('type', UserSanction::TYPE_WARNING)
            ->first() ?? throw new NotFoundHttpException();

        if (is_null($sanction->acknowledged_at)) {
            $sanction->forceFill(['acknowledged_at' => now()])->save();
        }

        return new JsonResponse(['warnings' => $request->user()->pendingWarnings()]);
    }
}

<?php

namespace Pterodactyl\Http\Controllers\Api\Remote\Servers;

use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Schedules\ProcessScheduleService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: the node saw the state or console line a schedule waits for.
 */
class ScheduleTriggerController extends Controller
{
    public function __construct(private ProcessScheduleService $service)
    {
    }

    public function __invoke(Request $request, string $uuid, int $schedule): JsonResponse
    {
        /** @var \Pterodactyl\Models\Node $node */
        $node = $request->attributes->get('node');

        /** @var Server|null $server */
        $server = Server::query()->where('uuid', $uuid)->where('node_id', $node->id)->first();
        /** @var Schedule|null $model */
        $model = $server?->schedules()->where('id', $schedule)->first();
        if (!$server || !$model) {
            throw new NotFoundHttpException();
        }

        // Nothing to do for schedules that are off, time based, already
        // running, or while the server is busy (installing, suspended, …).
        if (!$model->is_active || $model->isCron() || $model->is_processing || !is_null($server->status) || $model->tasks->isEmpty()) {
            return new JsonResponse(['started' => false]);
        }

        try {
            $this->service->handle($model);
        } catch (\Throwable $exception) {
            Log::error($exception, ['schedule_id' => $model->id]);

            return new JsonResponse(['started' => false]);
        }

        Activity::event('server:schedule.triggered')
            ->subject($model, $server)
            ->property(['name' => $model->name, 'reason' => substr((string) $request->input('reason'), 0, 32)])
            ->log();

        return new JsonResponse(['started' => true]);
    }
}

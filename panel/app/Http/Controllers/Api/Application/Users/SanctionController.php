<?php

namespace Pterodactyl\Http\Controllers\Api\Application\Users;

use Illuminate\Http\Response;
use Pterodactyl\Models\User;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\BanTemplate;
use Pterodactyl\Models\UserSanction;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Users\SanctionService;
use Pterodactyl\Transformers\Api\Application\UserSanctionTransformer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Http\Controllers\Api\Application\ApplicationApiController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Pterodactyl\Http\Requests\Api\Application\Users\Sanctions\LiftBanRequest;
use Pterodactyl\Http\Requests\Api\Application\Users\Sanctions\StoreBanRequest;
use Pterodactyl\Http\Requests\Api\Application\Users\Sanctions\StoreWarningRequest;
use Pterodactyl\Http\Requests\Api\Application\Users\Sanctions\GetUserSanctionsRequest;

/**
 * Hydra: warnings and bans of user accounts over the application API. They do
 * the same as the buttons on the user page: a ban suspends every server of the
 * user, logs them out and sends them an email; lifting it unsuspends the
 * servers again.
 */
class SanctionController extends ApplicationApiController
{
    public function __construct(private SanctionService $service)
    {
        parent::__construct();
    }

    /**
     * Warnings and bans of the user, newest first.
     */
    public function index(GetUserSanctionsRequest $request, User $user): array
    {
        return $this->fractal->collection($user->sanctions()->latest('id')->get())
            ->transformWith($this->getTransformer(UserSanctionTransformer::class))
            ->toArray();
    }

    /**
     * @throws DisplayException
     */
    public function warn(StoreWarningRequest $request, User $user): JsonResponse
    {
        $this->assertCanSanction($request->user(), $user);
        $warning = $this->service->warn($user, $request->user(), $request->input('reason'));

        return $this->created($warning);
    }

    /**
     * @throws DisplayException
     */
    public function ban(StoreBanRequest $request, User $user): JsonResponse
    {
        $this->assertCanSanction($request->user(), $user);
        $ban = $this->service->banWith(
            $user,
            $request->user(),
            $request->filled('template') ? (int) $request->input('template') : null,
            $request->input('reason'),
            $request->filled('duration_days') ? (int) $request->input('duration_days') : null,
            $request->boolean('permanent'),
        );

        return $this->created($ban);
    }

    public function lift(LiftBanRequest $request, User $user, UserSanction $sanction): Response
    {
        $this->assertCanSanction($request->user(), $user);
        if ($sanction->user_id !== $user->id || !$sanction->isActive()) {
            throw new NotFoundHttpException();
        }

        $this->service->lift($sanction, $request->user());

        return $this->returnNoContent();
    }

    /**
     * Ban templates of the admin area, to ban with "template".
     */
    public function templates(GetUserSanctionsRequest $request): array
    {
        return [
            'object' => 'list',
            'data' => BanTemplate::query()->orderBy('sort')->orderBy('id')->get()->map(fn (BanTemplate $template) => [
                'object' => 'ban_template',
                'attributes' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'reason' => $template->reason,
                    'duration_days' => $template->duration_days,
                ],
            ])->all(),
        ];
    }

    private function created(UserSanction $sanction): JsonResponse
    {
        return new JsonResponse(
            $this->fractal->item($sanction)
                ->transformWith($this->getTransformer(UserSanctionTransformer::class))
                ->toArray(),
            JsonResponse::HTTP_CREATED,
        );
    }

    private function assertCanSanction(User $actor, User $user): void
    {
        try {
            $this->service->assertCanSanction($actor, $user);
        } catch (DisplayException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage());
        }
    }
}

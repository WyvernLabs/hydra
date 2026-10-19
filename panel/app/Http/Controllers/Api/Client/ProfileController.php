<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Pterodactyl\Models\User;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Facades\Auth;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Users\ProfileMediaService;
use Pterodactyl\Services\Users\UserDeletionService;
use Pterodactyl\Http\Requests\Api\Client\Account\DeleteAccountRequest;
use Pterodactyl\Http\Requests\Api\Client\Account\UpdateProfileRequest;

/**
 * Hydra: profile of the logged in user (picture, banner, biography, names)
 * and deleting the own account.
 */
class ProfileController extends ClientApiController
{
    public function __construct(
        private ProfileMediaService $media,
        private UserDeletionService $deletionService,
    ) {
        parent::__construct();
    }

    /**
     * Saves the profile and returns it as the panel shows it.
     *
     * @throws \Throwable
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $oldUsername = $user->username;

        $user->forceFill([
            'username' => $request->validated('username'),
            'name_first' => $request->validated('name_first'),
            'name_last' => $request->validated('name_last'),
            'bio' => $request->validated('bio') ?: null,
            'profile_accent' => $this->color($request->validated('profile_accent')),
            'banner_style' => $request->validated('banner_style'),
            'banner_color_1' => $this->color($request->validated('banner_color_1')),
            'banner_color_2' => $this->color($request->validated('banner_color_2')),
            'show_online' => $request->boolean('show_online'),
        ])->saveOrFail();

        foreach (ProfileMediaService::TYPES as $type) {
            if ($request->hasFile($type)) {
                $this->media->store($user, $type, $request->file($type));
            } elseif ($request->boolean("remove_$type")) {
                $this->media->remove($user, $type);
            }
        }

        if ($oldUsername !== $user->username) {
            Activity::event('user:account.username-changed')
                ->property(['old' => $oldUsername, 'new' => $user->username])
                ->log();
        }

        return new JsonResponse([
            'username' => $user->username,
            'name_first' => $user->name_first,
            'name_last' => $user->name_last,
        ] + $user->profile());
    }

    /**
     * Deletes the account of the logged in user. Accounts that still own
     * servers, and the last root admin, cannot be deleted.
     *
     * @throws DisplayException
     */
    public function delete(DeleteAccountRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->root_admin && User::query()->where('root_admin', true)->count() <= 1) {
            throw new DisplayException(trans('exceptions.profile.last_root_admin'));
        }

        $this->deletionService->handle($user);

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function color(?string $color): ?string
    {
        return $color ? strtolower($color) : null;
    }
}

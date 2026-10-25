<?php

namespace Pterodactyl\Http\Controllers\Admin\Users;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\UserSanction;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hydra\Users\SanctionService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hydra: warn, ban and unban user accounts from their page in the admin area.
 */
class SanctionController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private SanctionService $service)
    {
    }

    public function warn(Request $request, User $user): RedirectResponse
    {
        $this->service->assertCanSanction($request->user(), $user);
        $data = $request->validate(['reason' => 'required|string|max:2000']);

        $this->service->warn($user, $request->user(), $data['reason']);
        $this->alert->success(__('admin/sanctions.warned', ['name' => $user->username]))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    public function ban(Request $request, User $user): RedirectResponse
    {
        $this->service->assertCanSanction($request->user(), $user);
        $data = $request->validate([
            'template' => 'nullable|integer|exists:ban_templates,id',
            'reason' => 'required_without:template|nullable|string|max:2000',
            'duration_days' => 'nullable|integer|between:1,3650',
        ]);

        $this->service->banWith(
            $user,
            $request->user(),
            !empty($data['template']) ? (int) $data['template'] : null,
            $data['reason'] ?? null,
            $request->filled('duration_days') ? (int) $data['duration_days'] : null,
            $request->boolean('permanent'),
        );
        $this->alert->success(__('admin/sanctions.banned', ['name' => $user->username]))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    public function lift(Request $request, User $user, UserSanction $sanction): RedirectResponse
    {
        $this->service->assertCanSanction($request->user(), $user);
        if ($sanction->user_id !== $user->id || !$sanction->isActive()) {
            throw new NotFoundHttpException();
        }

        $this->service->lift($sanction, $request->user());
        $this->alert->success(__('admin/sanctions.lifted', ['name' => $user->username]))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }
}

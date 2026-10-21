<?php

namespace Pterodactyl\Http\Controllers\Admin\Users;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Models\BanTemplate;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\UserSanction;
use Pterodactyl\Exceptions\DisplayException;
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
        $this->assertCanSanction($request, $user);
        $data = $request->validate(['reason' => 'required|string|max:2000']);

        $this->service->warn($user, $request->user(), $data['reason']);
        $this->alert->success(__('admin/sanctions.warned', ['name' => $user->username]))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    public function ban(Request $request, User $user): RedirectResponse
    {
        $this->assertCanSanction($request, $user);
        $data = $request->validate([
            'template' => 'nullable|integer|exists:ban_templates,id',
            'reason' => 'required_without:template|nullable|string|max:2000',
            'duration_days' => 'nullable|integer|between:1,3650',
        ]);

        // A template fills in what was left empty.
        $template = !empty($data['template']) ? BanTemplate::query()->find($data['template']) : null;
        $reason = trim((string) ($data['reason'] ?? '')) ?: (string) $template?->reason;
        $days = $request->filled('duration_days') ? (int) $data['duration_days'] : ($request->boolean('permanent') ? null : $template?->duration_days);

        $this->service->ban($user, $request->user(), $reason, $days);
        $this->alert->success(__('admin/sanctions.banned', ['name' => $user->username]))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    public function lift(Request $request, User $user, UserSanction $sanction): RedirectResponse
    {
        $this->assertCanSanction($request, $user);
        if ($sanction->user_id !== $user->id || !$sanction->isActive()) {
            throw new NotFoundHttpException();
        }

        $this->service->lift($sanction, $request->user());
        $this->alert->success(__('admin/sanctions.lifted', ['name' => $user->username]))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    /**
     * Nobody can sanction themselves, a root admin or an account with more
     * admin rights than they have.
     *
     * @throws DisplayException
     */
    private function assertCanSanction(Request $request, User $user): void
    {
        $actor = $request->user();
        if ($actor->is($user) || $user->root_admin || !$actor->outranks($user)) {
            throw new DisplayException(__('admin/sanctions.not_allowed'));
        }
    }
}

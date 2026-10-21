<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Model;
use Pterodactyl\Models\AdminRole;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\Translation\Translator;
use Pterodactyl\Services\Users\UserUpdateService;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Services\Users\ProfileMediaService;
use Pterodactyl\Services\Users\UserCreationService;
use Pterodactyl\Services\Users\UserDeletionService;
use Pterodactyl\Http\Requests\Admin\UserFormRequest;
use Pterodactyl\Http\Requests\Admin\NewUserFormRequest;
use Pterodactyl\Services\Users\AdminRoleAssignmentService;
use Pterodactyl\Contracts\Repository\UserRepositoryInterface;

class UserController extends Controller
{
    use AvailableLanguages;

    /**
     * UserController constructor.
     */
    public function __construct(
        protected AlertsMessageBag $alert,
        protected UserCreationService $creationService,
        protected UserDeletionService $deletionService,
        protected Translator $translator,
        protected UserUpdateService $updateService,
        protected UserRepositoryInterface $repository,
        protected ViewFactory $view,
        protected AdminRoleAssignmentService $roleService,
    ) {
    }

    /**
     * Display user index page.
     */
    public function index(Request $request): View
    {
        $users = QueryBuilder::for(
            User::query()->select('users.*')
                ->selectRaw('COUNT(DISTINCT(subusers.id)) as subuser_of_count')
                ->selectRaw('COUNT(DISTINCT(servers.id)) as servers_count')
                ->leftJoin('subusers', 'subusers.user_id', '=', 'users.id')
                ->leftJoin('servers', 'servers.owner_id', '=', 'users.id')
                ->groupBy('users.id')
                ->with('adminRoles')
                // Hydra: banned accounts are marked in the list.
                ->withExists(['sanctions as hydra_banned' => fn ($query) => $query->activeBans()])
        )
            ->allowedFilters([
                'username',
                'email',
                'uuid',
                // Hydra: users holding a given admin role.
                AllowedFilter::callback('role', function (Builder $query, $value) {
                    $query->whereHas('adminRoles', fn (Builder $roles) => $roles->where('admin_roles.id', (int) $value));
                }),
            ])
            ->defaultSort('-root_admin')
            ->allowedSorts(['id', 'uuid'])
            ->paginate(50);

        return view('admin.users.index', [
            'users' => $users,
            'roles' => AdminRole::query()->orderBy('sort')->orderBy('name')->get(),
        ]);
    }

    /**
     * Display new user page.
     */
    public function create(Request $request): View
    {
        return view('admin.users.new', [
            'languages' => $this->getAvailableLanguages(true),
        ] + $this->roleViewData($request->user()));
    }

    /**
     * Display user view page.
     */
    public function view(Request $request, User $user): View
    {
        return view('admin.users.view', [
            'user' => $user->load('adminRoles'),
            'languages' => $this->getAvailableLanguages(true),
            'canManage' => $request->user()->outranks($user),
        ] + $this->roleViewData($request->user()));
    }

    /**
     * Delete a user from the system.
     *
     * @throws \Exception
     * @throws DisplayException
     */
    public function delete(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            throw new DisplayException(__('admin/user.exceptions.delete_self'));
        }

        if (!$request->user()->outranks($user)) {
            throw new DisplayException(__('admin/roles.exceptions.user_outranks'));
        }

        $this->deletionService->handle($user);

        return redirect()->route('admin.users');
    }

    /**
     * Create a user.
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function store(NewUserFormRequest $request): RedirectResponse
    {
        $user = $this->creationService->handle($this->withoutRootFlag($request, $request->normalize()));
        $this->syncRoles($request, $user);
        $this->alert->success($this->translator->get('admin/user.notices.account_created'))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    /**
     * Update a user on the system.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     * @throws DisplayException
     */
    public function update(UserFormRequest $request, User $user): RedirectResponse
    {
        if (!$request->user()->outranks($user)) {
            throw new DisplayException(__('admin/roles.exceptions.user_outranks'));
        }

        $this->updateService
            ->setUserLevel(User::USER_LEVEL_ADMIN)
            ->handle($user, $this->withoutRootFlag($request, $request->normalize()));
        $this->syncRoles($request, $user);

        $this->alert->success(trans('admin/user.notices.account_updated'))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    /**
     * Hydra: removes a user's profile picture or banner, e.g. if it is not
     * appropriate.
     *
     * @throws DisplayException
     */
    public function removeMedia(Request $request, User $user, string $type, ProfileMediaService $media): RedirectResponse
    {
        if (!$request->user()->outranks($user)) {
            throw new DisplayException(__('admin/roles.exceptions.user_outranks'));
        }

        $media->remove($user, $type);
        $this->alert->success(__('admin/users.media_removed'))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    /**
     * Get a JSON response of users on the system.
     */
    public function json(Request $request): Model|Collection
    {
        // Hydra: owners are picked by their username.
        $users = QueryBuilder::for(User::query())->allowedFilters(['username', 'email'])->paginate(25);

        // Handle single user requests.
        if ($request->query('user_id')) {
            $user = User::query()->findOrFail($request->input('user_id'));
            // @phpstan-ignore-next-line property.notFound
            $user->md5 = md5(strtolower($user->email));

            return $user;
        }

        return $users->map(function ($item) {
            // @phpstan-ignore-next-line property.notFound
            $item->md5 = md5(strtolower($item->email));

            return $item;
        });
    }

    /**
     * Hydra: all roles and the ones the current user may hand out.
     */
    protected function roleViewData(User $actor): array
    {
        $roles = AdminRole::query()->orderBy('sort')->orderBy('name')->get();

        return [
            'roles' => $roles,
            'assignableRoles' => $roles->filter(fn (AdminRole $role) => $this->roleService->canAssign($actor, $role))
                ->pluck('id')
                ->all(),
        ];
    }

    /**
     * Hydra: only root admins may hand out or take away the root admin flag.
     */
    protected function withoutRootFlag(Request $request, array $data): array
    {
        if (!$request->user()->root_admin) {
            unset($data['root_admin']);
        }

        return $data;
    }

    /**
     * Hydra: saves the admin roles picked in the form, as far as the current
     * user may hand them out.
     */
    protected function syncRoles(Request $request, User $user): void
    {
        if ($request->boolean('roles_submitted') && $request->user()->hasAdminPermission('roles.assign')) {
            $this->roleService->handle($request->user(), $user, (array) $request->input('roles', []));
        }
    }
}

<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Services\Acl\Api\AdminAcl;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Api\KeyCreationService;
use Pterodactyl\Http\Requests\Admin\Api\StoreApplicationApiKeyRequest;

class ApiController extends Controller
{
    /**
     * ApiController constructor.
     */
    public function __construct(
        private AlertsMessageBag $alert,
        private KeyCreationService $keyCreationService,
    ) {
    }

    /**
     * Render view showing all of a user's application API keys.
     */
    public function index(Request $request): View
    {
        return view('admin.api.index', [
            // Hydra: root admins see every key, everybody else only their own.
            'keys' => ApiKey::query()
                ->where('key_type', ApiKey::TYPE_APPLICATION)
                ->when(!$request->user()->root_admin, fn ($query) => $query->where('user_id', $request->user()->id))
                ->get(),
        ]);
    }

    /**
     * Render view allowing an admin to create a new application API key.
     *
     * @throws \ReflectionException
     */
    public function create(Request $request): View
    {
        $resources = AdminAcl::getResourceList();
        sort($resources);

        return view('admin.api.new', [
            'resources' => $resources,
            // Hydra: the most each resource may get, bound by the user's own permissions.
            'limits' => collect($resources)->mapWithKeys(fn ($resource) => [$resource => AdminAcl::maxFor($request->user(), $resource)])->all(),
            'permissions' => [
                'r' => AdminAcl::READ,
                'rw' => AdminAcl::READ | AdminAcl::WRITE,
                'n' => AdminAcl::NONE,
            ],
        ]);
    }

    /**
     * Store the new key and redirect the user back to the application key listing.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function store(StoreApplicationApiKeyRequest $request): RedirectResponse
    {
        $this->keyCreationService->setKeyType(ApiKey::TYPE_APPLICATION)->handle([
            'memo' => $request->input('memo'),
            'user_id' => $request->user()->id,
        ], collect($request->getKeyPermissions())->map(function ($value, $column) use ($request) {
            return (int) $value & AdminAcl::maxFor($request->user(), substr($column, strlen(AdminAcl::COLUMN_IDENTIFIER)));
        })->all());

        $this->alert->success(trans('admin/api.created_notice'))->flash();

        return redirect()->route('admin.api.index');
    }

    /**
     * Delete an application API key from the database.
     */
    public function delete(Request $request, string $identifier): Response
    {
        ApiKey::query()
            ->where('key_type', ApiKey::TYPE_APPLICATION)
            ->where('identifier', $identifier)
            ->when(!$request->user()->root_admin, fn ($query) => $query->where('user_id', $request->user()->id))
            ->delete();

        return response('', 204);
    }
}

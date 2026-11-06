<?php

namespace Pterodactyl\Http\Controllers\Admin\Servers;

use Illuminate\View\View;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Illuminate\Http\Request;
use Pterodactyl\Models\Location;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\HydraServerTemplate;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Pterodactyl\Services\Hydra\Backups\BackupStorage;
use Pterodactyl\Services\Servers\VariableValidatorService;

/**
 * Hydra: manages the templates of the addon "server_templates".
 */
class ServerTemplateController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private VariableValidatorService $variables)
    {
    }

    public function index(): View
    {
        return view('admin.servers.templates.index', [
            'templates' => HydraServerTemplate::query()->with('egg.nest')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(null);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = HydraServerTemplate::query()->create($this->values($request, null));
        $this->alert->success(trans('admin/server_templates.created', ['name' => $template->name]))->flash();

        return redirect()->route('admin.servers.templates');
    }

    public function view(HydraServerTemplate $template): View
    {
        return $this->form($template);
    }

    public function update(Request $request, HydraServerTemplate $template): RedirectResponse
    {
        $template->update($this->values($request, $template));
        $this->alert->success(trans('admin/server_templates.updated', ['name' => $template->name]))->flash();

        return redirect()->route('admin.servers.templates');
    }

    public function delete(HydraServerTemplate $template): RedirectResponse
    {
        $template->delete();
        $this->alert->success(trans('admin/server_templates.deleted', ['name' => $template->name]))->flash();

        return redirect()->route('admin.servers.templates');
    }

    private function form(?HydraServerTemplate $template): View
    {
        \JavaScript::put([
            'templateEggs' => Egg::query()->with('variables')->get()->map(fn (Egg $egg) => [
                'id' => $egg->id,
                'startup' => $egg->startup,
                'images' => array_values($egg->docker_images ?? []),
                'variables' => $egg->variables->map(fn ($variable) => [
                    'name' => $variable->name,
                    'description' => $variable->description,
                    'env' => $variable->env_variable,
                    'default' => $variable->default_value,
                    'rules' => $variable->rules,
                ])->values(),
            ])->values(),
            'templateValues' => [
                'image' => old('custom_image') ?: old('image', $template?->image),
                'environment' => (object) old('environment', $template?->environment ?? []),
            ],
        ]);

        return view('admin.servers.templates.form', [
            'template' => $template,
            'nests' => Nest::query()->with('eggs')->orderBy('name')->get(),
            'locations' => Location::query()->orderBy('short')->get(),
            'nodes' => Node::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function values(Request $request, ?HydraServerTemplate $template): array
    {
        $imageRules = ['nullable', 'string', 'max:191', 'regex:/^~?[\w\.\/\-:@ ]*$/'];
        $data = $request->validate([
            'name' => ['required', 'string', 'between:1,64', Rule::unique('hydra_server_templates', 'name')->ignore($template?->id)],
            'description' => 'nullable|string|max:500',
            'egg_id' => 'required|integer|exists:eggs,id',
            'image' => $imageRules,
            'custom_image' => $imageRules,
            'startup' => 'nullable|string',
            'environment' => 'nullable|array',
            'environment.*' => 'nullable|string',
            'cpu' => 'required|integer|min:0',
            'threads' => 'nullable|regex:/^[0-9-,]+$/',
            'memory' => 'required|integer|min:0',
            'swap' => 'required|integer|min:-1',
            'disk' => 'required|integer|min:0',
            'io' => 'required|integer|between:10,1000',
            'database_limit' => 'required|integer|min:0',
            'allocation_limit' => 'required|integer|min:0',
            'backup_storage_gb' => 'required|numeric|min:0',
            'locations' => 'nullable|array',
            'locations.*' => 'integer|exists:locations,id',
            'nodes' => 'nullable|array',
            'nodes.*' => 'integer|exists:nodes,id',
        ]);

        $image = $data['custom_image'] ?? $data['image'] ?? null;
        if (empty($image)) {
            throw ValidationException::withMessages(['image' => trans('admin/server_templates.image_required')]);
        }

        // Only the variables of the egg are kept, checked by the rules of the egg.
        $environment = $this->variables->setUserLevel(User::USER_LEVEL_ADMIN)
            ->handle((int) $data['egg_id'], $data['environment'] ?? [])
            ->mapWithKeys(fn ($variable) => [$variable->key => $variable->value])
            ->all();

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'egg_id' => (int) $data['egg_id'],
            'image' => $image,
            'startup' => $data['startup'] ?? null,
            'environment' => $environment,
            'cpu' => (int) $data['cpu'],
            'threads' => $data['threads'] ?? null,
            'memory' => (int) $data['memory'],
            'swap' => (int) $data['swap'],
            'disk' => (int) $data['disk'],
            'io' => (int) $data['io'],
            'oom_disabled' => !$request->boolean('oom_enabled'),
            'database_limit' => (int) $data['database_limit'],
            'allocation_limit' => (int) $data['allocation_limit'],
            'backup_storage_limit' => BackupStorage::mbFromGb($data['backup_storage_gb']),
            'locations' => array_map('intval', $data['locations'] ?? []) ?: null,
            'nodes' => array_map('intval', $data['nodes'] ?? []) ?: null,
            'start_on_completion' => $request->boolean('start_on_completion'),
            'skip_scripts' => $request->boolean('skip_scripts'),
        ];
    }
}

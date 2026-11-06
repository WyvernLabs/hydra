<?php

namespace Pterodactyl\Services\Hydra\Templates;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\HydraServerTemplate;
use Pterodactyl\Models\Objects\DeploymentObject;
use Pterodactyl\Services\Servers\ServerCreationService;

/**
 * Hydra: creates servers from the templates of the addon "server_templates".
 */
class ServerTemplateService
{
    public function __construct(private ServerCreationService $creation)
    {
    }

    /**
     * The node and its port are picked from the targets of the template.
     *
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Pterodactyl\Exceptions\Service\Deployment\NoViableNodeException
     * @throws \Pterodactyl\Exceptions\Service\Deployment\NoViableAllocationException
     */
    public function deploy(HydraServerTemplate $template, string $name, int $ownerId): Server
    {
        $egg = $template->egg;

        return $this->creation->handle([
            'name' => $name,
            'description' => '',
            'owner_id' => $ownerId,
            'egg_id' => $egg->id,
            'nest_id' => $egg->nest_id,
            'image' => $template->image,
            'startup' => $template->startup ?: $egg->startup,
            'environment' => $this->environment($template),
            'cpu' => $template->cpu,
            'threads' => $template->threads,
            'memory' => $template->memory,
            'swap' => $template->swap,
            'disk' => $template->disk,
            'io' => $template->io,
            'oom_disabled' => $template->oom_disabled,
            'database_limit' => $template->database_limit,
            'allocation_limit' => $template->allocation_limit,
            'backup_limit' => 0,
            'backup_storage_limit' => $template->backup_storage_limit,
            'skip_scripts' => $template->skip_scripts,
            'start_on_completion' => $template->start_on_completion,
        ], (new DeploymentObject())->setLocations($template->locations ?? [])->setNodes($template->nodes ?? []));
    }

    /**
     * Saved values of the variables. Variables added to the egg afterwards get their default.
     */
    private function environment(HydraServerTemplate $template): array
    {
        $saved = $template->environment ?? [];

        return $template->egg->variables->mapWithKeys(fn ($variable) => [
            $variable->env_variable => array_key_exists($variable->env_variable, $saved) ? $saved[$variable->env_variable] : $variable->default_value,
        ])->all();
    }
}

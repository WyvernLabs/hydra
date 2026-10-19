<?php

namespace Pterodactyl\Http\Requests\Admin\Design;

use Pterodactyl\Services\Hydra\BackgroundService;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BackgroundsFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'reset_background' => 'nullable|string|in:login,dashboard',
        ] + BackgroundService::rules('bg_login') + BackgroundService::rules('bg_dashboard');
    }
}

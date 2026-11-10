<?php

namespace Pterodactyl\Http\Requests\Admin\Design;

use Pterodactyl\Services\Hydra\BackgroundService;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BackgroundsFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        $rules = ['reset_background' => 'nullable|string|in:' . implode(',', BackgroundService::SLOTS)];
        foreach (BackgroundService::SLOTS as $slot) {
            $rules += BackgroundService::rules("bg_$slot");
        }

        return $rules;
    }
}

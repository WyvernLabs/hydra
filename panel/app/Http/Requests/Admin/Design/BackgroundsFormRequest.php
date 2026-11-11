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

        // Hydra: logo and favicon live on the same page (Designer > Images).
        return $rules + [
            'logo' => 'nullable|file|max:2048|mimes:png,jpg,jpeg,gif,webp,svg',
            'favicon' => 'nullable|file|max:1024|mimes:png,ico,svg',
            'reset_logo' => 'nullable|boolean',
            'reset_favicon' => 'nullable|boolean',
            'protect_images' => 'nullable|boolean',
        ];
    }
}

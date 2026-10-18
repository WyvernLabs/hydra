<?php

namespace Pterodactyl\Http\Requests\Admin\Settings;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class AppearanceSettingsFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'accent' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => 'nullable|file|max:2048|mimes:png,jpg,jpeg,gif,webp,svg',
            'favicon' => 'nullable|file|max:1024|mimes:png,ico,svg',
            'reset_logo' => 'nullable|boolean',
            'reset_favicon' => 'nullable|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'accent' => 'Accent Color',
            'logo' => 'Logo',
            'favicon' => 'Favicon',
        ];
    }
}

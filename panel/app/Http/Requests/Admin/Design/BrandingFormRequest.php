<?php

namespace Pterodactyl\Http\Requests\Admin\Design;

use Pterodactyl\Services\Hydra\SurfaceService;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class BrandingFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        $surfaces = ['surface' => 'sometimes|array'];
        foreach (SurfaceService::COLORS as $key) {
            $surfaces["surface.$key"] = ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }
        foreach (SurfaceService::NUMBERS as $key => [$min, $max]) {
            $surfaces["surface.$key"] = "sometimes|integer|between:$min,$max";
        }

        return $surfaces + [
            'accent' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => 'nullable|file|max:2048|mimes:png,jpg,jpeg,gif,webp,svg',
            'favicon' => 'nullable|file|max:1024|mimes:png,ico,svg',
            'reset_logo' => 'nullable|boolean',
            'reset_favicon' => 'nullable|boolean',
            'protect_images' => 'nullable|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'accent' => 'Accent Color',
            'logo' => 'Logo',
            'favicon' => 'Favicon',
            'surface.box' => trans('admin/design.surfaces.box'),
            'surface.inner' => trans('admin/design.surfaces.inner'),
            'surface.border' => trans('admin/design.surfaces.border'),
            'surface.opacity' => trans('admin/design.surfaces.opacity'),
            'surface.border_opacity' => trans('admin/design.surfaces.border_opacity'),
            'surface.blur' => trans('admin/design.surfaces.blur'),
        ];
    }
}

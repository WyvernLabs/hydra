<?php

namespace Pterodactyl\Http\Requests\Admin\Settings;

use Illuminate\Validation\Rule;
use Pterodactyl\Models\PanelLink;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

/**
 * Hydra: a link or page in the sidebar of the client area.
 */
class PanelLinkFormRequest extends AdminFormRequest
{
    use AvailableLanguages;

    public function rules(): array
    {
        $link = $this->route()->parameter('link');
        $languages = array_keys($this->getAvailableLanguages());

        $rules = [
            'type' => 'required|string|in:page,url',
            'title' => 'required|array',
            'title.*' => 'nullable|string|max:64',
            'icon' => ['required', 'string', Rule::in(PanelLink::ICONS)],
            'slug' => [
                'nullable', 'required_if:type,page', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('panel_links', 'slug')->ignore($link instanceof PanelLink ? $link->id : null),
            ],
            'url' => 'nullable|string|max:2048|url:http,https',
            'content' => 'nullable|array',
            'content.*' => 'nullable|string|max:65535',
            'enabled' => 'boolean',
            'public' => 'boolean',
            'sort' => 'integer|min:0|max:9999',
        ];

        // At least one title is needed; the panel language is a good one to ask for.
        $rules['title.' . (in_array(config('app.locale'), $languages, true) ? config('app.locale') : 'en')] = 'required|string|max:64';

        return $rules;
    }

    /**
     * The values to store, cleaned up for the chosen type.
     */
    public function linkData(): array
    {
        $isPage = $this->input('type') === PanelLink::TYPE_PAGE;

        return [
            'type' => $this->input('type'),
            'title' => array_filter($this->input('title', []), fn ($value) => !blank($value)),
            'icon' => $this->input('icon'),
            'slug' => $isPage ? $this->input('slug') : null,
            'url' => $isPage ? null : ($this->input('url') ?: null),
            'content' => $isPage ? array_filter($this->input('content', []), fn ($value) => !blank($value)) : null,
            'enabled' => $this->boolean('enabled'),
            'public' => $isPage && $this->boolean('public'),
            'sort' => (int) $this->input('sort', 0),
        ];
    }
}

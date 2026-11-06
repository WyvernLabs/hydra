<?php

namespace Pterodactyl\Http\Requests\Admin\Design;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Pterodactyl\Models\HydraAnnouncement;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

/**
 * Hydra: an announcement of the client area.
 */
class AnnouncementFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_keys(HydraAnnouncement::TYPES))],
            'title' => 'required|array',
            'title.*' => 'nullable|string|max:191',
            'description' => 'required|array',
            'description.*' => 'nullable|string|max:2000',
            'button_text' => 'nullable|array',
            'button_text.*' => 'nullable|string|max:64',
            // A web address or a path of this panel; nothing like javascript: can get into a link.
            'button_url' => ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/[^\s]+|\/(?!\/)[^\s]*)$/i'],
            'scope' => 'required|string|in:general,nodes',
            'nodes' => 'nullable|array|required_if:scope,nodes',
            'nodes.*' => 'integer|exists:nodes,id',
            'dismissible' => 'boolean',
            'enabled' => 'boolean',
            'expires_at' => 'nullable|date',
            'sort' => 'integer|min:0|max:9999',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Any one language is enough, the others fall back to it.
            if (empty($this->texts($this->input('title')))) {
                $validator->errors()->add('title', trans('admin/announcements.title_required'));
            }
            if (empty($this->texts($this->input('description')))) {
                $validator->errors()->add('description', trans('admin/announcements.description_required'));
            }

            $hasText = !empty($this->texts($this->input('button_text')));
            if ($hasText && blank($this->input('button_url'))) {
                $validator->errors()->add('button_url', trans('admin/announcements.button_url_required'));
            }
            if (!$hasText && !blank($this->input('button_url'))) {
                $validator->errors()->add('button_text', trans('admin/announcements.button_text_required'));
            }
        });
    }

    /**
     * The values to store.
     */
    public function announcementData(): array
    {
        $general = $this->input('scope') === 'general';

        return [
            'type' => $this->input('type'),
            'title' => $this->texts($this->input('title')),
            'description' => $this->texts($this->input('description')),
            'button_text' => $this->texts($this->input('button_text')) ?: null,
            'button_url' => $this->input('button_url') ?: null,
            'nodes' => $general ? null : array_values(array_unique(array_map('intval', $this->input('nodes', [])))),
            'dismissible' => $this->boolean('dismissible'),
            'enabled' => $this->boolean('enabled'),
            'expires_at' => $this->input('expires_at') ?: null,
            'sort' => (int) $this->input('sort', 0),
        ];
    }

    private function texts(mixed $values): array
    {
        return array_filter(is_array($values) ? $values : [], fn ($value) => !blank($value));
    }
}

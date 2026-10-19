<?php

namespace Pterodactyl\Http\Requests\Api\Client\Account;

use Illuminate\Validation\Rule;
use Pterodactyl\Rules\Username;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Services\Users\ProfileMediaService;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

/**
 * Hydra: the profile of the logged in user.
 */
class UpdateProfileRequest extends ClientApiRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => mb_strtolower(trim($this->input('username')))]);
        }
    }

    public function rules(): array
    {
        $color = ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $image = ['nullable', 'file', 'mimes:png,jpg,jpeg,gif,webp', 'max:' . (ProfileMediaService::uploadLimit() * 1024), function (string $attribute, mixed $value, \Closure $fail) {
            // Check the contents as well, the name and type sent by the browser can be anything.
            $info = $value instanceof UploadedFile ? @getimagesize($value->getRealPath()) : false;
            if (!$info || !array_key_exists($info['mime'], ProfileMediaService::MIME_TYPES)) {
                $fail(trans('exceptions.profile.invalid_image'));
            }
        }];

        return [
            'username' => ['required', 'string', 'between:1,191', new Username(), Rule::unique('users', 'username')->ignore($this->user()->id)],
            'name_first' => 'required|string|between:1,191',
            'name_last' => 'required|string|between:1,191',
            'bio' => 'nullable|string|max:240',
            'profile_accent' => $color,
            'banner_style' => 'required|string|in:gradient,image',
            'banner_color_1' => $color,
            'banner_color_2' => $color,
            'show_online' => 'required|boolean',
            'avatar' => $image,
            'banner' => $image,
            'remove_avatar' => 'sometimes|boolean',
            'remove_banner' => 'sometimes|boolean',
        ];
    }
}

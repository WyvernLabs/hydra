<?php

namespace Pterodactyl\Http\Requests\Api\Client\Account;

use Illuminate\Container\Container;
use Illuminate\Contracts\Hashing\Hasher;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;
use Pterodactyl\Exceptions\Http\Base\InvalidPasswordProvidedException;

/**
 * Hydra: deleting the own account needs the password and the word
 * "DELETE" (or "LÖSCHEN") typed in.
 */
class DeleteAccountRequest extends ClientApiRequest
{
    /**
     * @throws InvalidPasswordProvidedException
     */
    public function authorize(): bool
    {
        if (!parent::authorize()) {
            return false;
        }

        $hasher = Container::getInstance()->make(Hasher::class);

        if (!$hasher->check($this->input('password', ''), $this->user()->password)) {
            throw new InvalidPasswordProvidedException(trans('validation.internal.invalid_password'));
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'password' => 'required|string',
            'confirm' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (!in_array(mb_strtoupper(trim($value)), ['DELETE', 'LÖSCHEN'], true)) {
                    $fail(trans('exceptions.profile.confirm_delete'));
                }
            }],
        ];
    }
}

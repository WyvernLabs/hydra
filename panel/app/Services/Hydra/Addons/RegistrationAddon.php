<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Illuminate\Support\Str;

/**
 * Hydra: visitors can create an account themselves. They confirm their email
 * address with a six digit code first.
 */
class RegistrationAddon extends Addon
{
    public function key(): string
    {
        return 'registration';
    }

    public function icon(): string
    {
        return 'user-plus';
    }

    public function rules(): array
    {
        return [
            'code_lifetime' => 'required|integer|between:5,60',
            'allowed_domains' => 'nullable|string|max:2000',
        ];
    }

    public function summary(): array
    {
        $domains = $this->allowedDomains();

        return [
            trans('admin/addons.registration.summary_lifetime', ['minutes' => $this->codeLifetime()]),
            empty($domains)
                ? trans('admin/addons.registration.summary_all_domains')
                : trans('admin/addons.registration.summary_domains', ['domains' => implode(', ', $domains)]),
        ];
    }

    /**
     * Minutes a code sent by email stays valid.
     */
    public function codeLifetime(): int
    {
        return max(5, min(60, (int) $this->get('code_lifetime', 15)));
    }

    /**
     * Email domains that may register. Empty means every domain.
     *
     * @return string[]
     */
    public function allowedDomains(): array
    {
        return collect(preg_split('/[\s,;]+/', (string) $this->get('allowed_domains', '')))
            ->map(fn (string $domain) => ltrim(Str::lower(trim($domain)), '@'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function allowsEmail(string $email): bool
    {
        $domains = $this->allowedDomains();
        if (empty($domains)) {
            return true;
        }

        return in_array(Str::lower(Str::afterLast($email, '@')), $domains, true);
    }
}

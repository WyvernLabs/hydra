<?php

namespace Pterodactyl\Http\Requests\Base;

use Illuminate\Foundation\Http\FormRequest;

class LocaleRequest extends FormRequest
{
    /**
     * i18next-multiload-backend-adapter requests several languages and namespaces at
     * once, joined by "+" (which arrives as a space), e.g. locale=de+en&namespace=a+b.
     * Hydra: namespaces may contain sub folders (e.g. "client/server"), but no dots or
     * leading slashes, so they always stay inside resources/lang/<locale>.
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', 'max:64', 'regex:/^[a-z]{2}([ +][a-z]{2})*$/'],
            'namespace' => ['required', 'string', 'max:512', 'regex:/^[a-z][a-z0-9_-]*(\/[a-z][a-z0-9_-]*)*([ +][a-z][a-z0-9_-]*(\/[a-z][a-z0-9_-]*)*)*$/'],
        ];
    }

    /**
     * @return string[]
     */
    public function locales(): array
    {
        return preg_split('/[ +]/', $this->input('locale'));
    }

    /**
     * @return string[]
     */
    public function namespaces(): array
    {
        return preg_split('/[ +]/', $this->input('namespace'));
    }
}

<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Pterodactyl\Traits\Helpers\AvailableLanguages;

class LanguageMiddleware
{
    use AvailableLanguages;

    /**
     * LanguageMiddleware constructor.
     */
    public function __construct(private Application $app)
    {
    }

    /**
     * Handle an incoming request and set the preferred language: the language
     * chosen by the user, otherwise the browser language (e.g. on the login page),
     * otherwise the default language configured in the admin area.
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        $this->app->setLocale($this->resolve($request));

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $available = array_keys($this->getAvailableLanguages());
        $default = config('app.locale', 'en');

        $language = $request->user()?->language;
        if (!empty($language) && in_array($language, $available, true)) {
            return $language;
        }

        // Accept-Language in order of preference, e.g. ["de_DE", "de", "en_US"]. Symfony's
        // getPreferredLanguage() would fall back to the first available language instead
        // of the configured default, so the list is walked manually.
        foreach ($request->getLanguages() as $language) {
            $code = strtolower(substr($language, 0, 2));
            if (in_array($code, $available, true)) {
                return $code;
            }
        }

        return $default;
    }
}

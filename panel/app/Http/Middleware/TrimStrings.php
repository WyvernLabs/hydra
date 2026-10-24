<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as BaseTrimmer;

class TrimStrings extends BaseTrimmer
{
    /**
     * The names of the attributes that should not be trimmed.
     */
    protected $except = [
        'password',
        'password_confirmation',
    ];

    /**
     * Hydra: the console message while offline may start with spaces, e.g.
     * ASCII art (Design > Console).
     */
    protected function transform($key, $value)
    {
        if (str_starts_with((string) $key, 'offline_message.')) {
            return $value;
        }

        return parent::transform($key, $value);
    }
}

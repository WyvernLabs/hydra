<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Upstream
    |--------------------------------------------------------------------------
    |
    | The Pterodactyl releases this Hydra build is based on. Shown in the admin
    | area and used as reference when porting upstream security fixes.
    |
    */
    'upstream' => [
        'panel' => '1.15.1',
        'wings' => '1.13.4',
    ],

    /*
    |--------------------------------------------------------------------------
    | Hydra Branding
    |--------------------------------------------------------------------------
    |
    | Default values for the panel appearance. All of these values can be
    | overridden in the admin area under Settings > Appearance, which stores
    | them in the settings table (keys "settings::hydra:branding:*").
    |
    | "logo" and "favicon" contain the file name of an uploaded image inside
    | storage/app/hydra/branding. When empty, the bundled defaults are used.
    |
    */
    'branding' => [
        'accent' => env('HYDRA_ACCENT', '#74c236'),
        'logo' => null,
        'favicon' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | User Profiles
    |--------------------------------------------------------------------------
    |
    | Upload limit for profile pictures and banners in megabytes. Can be
    | changed in the admin area under Settings ("settings::hydra:profiles:*").
    |
    */
    'profiles' => [
        'upload_limit' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Backgrounds
    |--------------------------------------------------------------------------
    |
    | Backgrounds of the login page and the dashboard, set in the admin area
    | under Settings > Appearance ("settings::hydra:backgrounds:*"). "image"
    | is a file in storage/app/hydra/backgrounds, blur is in pixels and dim
    | in percent. Without an image the hexagon pattern is used.
    |
    */
    'backgrounds' => [
        'login' => ['image' => null, 'blur' => 0, 'dim' => 0],
        'dashboard' => ['image' => null, 'blur' => 0, 'dim' => 0],
    ],

    /*
    |--------------------------------------------------------------------------
    | Addons
    |--------------------------------------------------------------------------
    |
    | Default settings of the addons in the admin area under Design > Addons
    | ("settings::hydra:addons:<addon>:<setting>").
    |
    | turnstile:    Cloudflare Turnstile for the login ("login"), the password
    |               reset ("password") and the registration ("register").
    | registration: visitors create an account themselves after confirming
    |               their email with a code that is valid "code_lifetime"
    |               minutes. "allowed_domains" limits the email domains
    |               (comma separated, empty = every domain).
    | phpmyadmin:   users open their databases in phpMyAdmin, which is
    |               installed on the panel server from the addon page.
    | dashboard:    overview page of every server; with "landing" servers
    |               open on it instead of the console.
    | autodelete:   deletes servers suspended for "server_days" and accounts
    |               banned permanently for "user_days" (both can be switched
    |               off with "servers" and "users").
    |
    */
    'addons' => [
        'turnstile' => [
            'enabled' => false,
            'site_key' => env('TURNSTILE_SITE_KEY'),
            'secret_key' => env('TURNSTILE_SECRET_KEY'),
            'login' => true,
            'password' => true,
            'register' => true,
        ],
        'registration' => [
            'enabled' => false,
            'code_lifetime' => 15,
            'allowed_domains' => null,
        ],
        'phpmyadmin' => [
            'enabled' => false,
        ],
        'dashboard' => [
            'enabled' => false,
            'landing' => true,
        ],
        'autodelete' => [
            'enabled' => false,
            'servers' => true,
            'server_days' => 30,
            'users' => true,
            'user_days' => 14,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | Navigation of the server pages as JSON, edited under Design > Navigation
    | ("settings::hydra:navigation:server"). Null uses the default navigation,
    | see Pterodactyl\Services\Hydra\ServerNavigation.
    |
    */
    'navigation' => [
        'server' => null,
    ],
];

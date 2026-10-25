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
    | lockdown:     owners can lock everyone else out of their server.
    | cpu:          CPU usage out of 100 % instead of 100 % per core.
    | mclogs:       console button that uploads the log file at "path" to mclo.gs.
    | startup_command: users change the startup command of their servers.
    | properties:   server page with the .properties file at "path" as a form.
    | env:          server page with the .env file at "path" as a form.
    | versions:     Minecraft version changer with the software of mcjars.app.
    | motd:         MOTD with a picture for Minecraft servers of the "eggs" that
    |               are offline, starting, stopping or suspended ("states", JSON).
    | importer:     users copy the files of another server over SFTP onto
    |               theirs (settings page of the server).
    | ports:        servers get the additional ports of their egg automatically
    |               (eggs.hydra_ports, set on the egg page).
    | player_stats: players online on Minecraft Java servers of the "eggs"
    |               (JSON), kept for "keep_days".
    | pwa:          the panel can be installed as an app ("name", "short_name",
    |               "theme_color", "background_color", icon in storage); "tabs"
    |               opens pages in tabs inside the app window.
    | resource_pool: users share out a pool of resources (set on their page in
    |               the admin area) to own servers; they pick from the "eggs" and
    |               "locations" (JSON, empty = all), "user_edit" / "user_delete"
    |               let them resize and delete those servers.
    | trash:        deleted files are kept in a trash for "days" (on by default).
    | tickets:      support tickets in the "languages" (ISO codes, JSON) with
    |               "categories" named in each of them (JSON), images up to
    |               "upload_limit" MB, "max_open" unclosed tickets per user
    |               (0 = any number).
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
        'lockdown' => [
            'enabled' => false,
        ],
        'cpu' => [
            'enabled' => false,
        ],
        'mclogs' => [
            'enabled' => false,
            'path' => 'logs/latest.log',
        ],
        'sftp_backups' => [
            'enabled' => false,
            'users' => true,
        ],
        'subdomains' => [
            'enabled' => false,
            'default_limit' => 1,
            'blocked' => '',
        ],
        'proxy' => [
            'enabled' => false,
            'default_limit' => 1,
        ],
        'games' => [
            'enabled' => false,
            'eggs' => [],
            'max_profiles' => 3,
        ],
        'startup_command' => [
            'enabled' => false,
        ],
        'properties' => [
            'enabled' => false,
            'path' => 'server.properties',
        ],
        'env' => [
            'enabled' => false,
            'path' => '.env',
        ],
        'versions' => [
            'enabled' => false,
        ],
        'motd' => [
            'enabled' => false,
            'eggs' => [],
            'states' => null,
        ],
        'importer' => [
            'enabled' => true,
        ],
        'ports' => [
            'enabled' => true,
        ],
        'player_stats' => [
            'enabled' => false,
            'eggs' => [],
            'keep_days' => 30,
        ],
        'pwa' => [
            'enabled' => true,
            'name' => null,
            'short_name' => null,
            'theme_color' => '#141414',
            'background_color' => '#121212',
            'tabs' => true,
        ],
        'resource_pool' => [
            'enabled' => false,
            'eggs' => [],
            'locations' => [],
            'user_edit' => true,
            'user_delete' => true,
        ],
        'trash' => [
            'enabled' => true,
            'days' => 7,
        ],
        'tickets' => [
            'enabled' => false,
            'languages' => null,
            'categories' => null,
            'upload_limit' => 5,
            'max_open' => 5,
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

    /*
    |--------------------------------------------------------------------------
    | Console
    |--------------------------------------------------------------------------
    |
    | Texts of the server console, set under Design > Console
    | ("settings::hydra:console:*"): the message while a server is offline (JSON
    | by language), the prefix of panel messages and the one that replaces
    | "[… Daemon]:" of Wings (null keeps it).
    |
    */
    'console' => [
        'offline_message' => null,
        'prefix' => null,
        'daemon_prefix' => null,
    ],
];

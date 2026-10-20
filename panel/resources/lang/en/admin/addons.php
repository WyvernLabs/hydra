<?php

/*
 * Hydra: the addons of the panel (Design > Addons).
 */
return [
    'title' => 'Addons',
    'subtitle' => 'Extra features of the panel that can be switched on one by one.',
    'help' => 'Click on a box to change the settings of the addon.',
    'active' => 'Active',
    'inactive' => 'Inactive',
    'enable' => 'Enable',
    'disable' => 'Disable',
    'settings' => 'Settings',
    'back' => 'Back to the addons',
    'enabled_label' => 'Addon enabled',
    'saved' => 'The settings of ":name" have been saved.',
    'saved_not_enabled' => 'The settings have been saved, but the addon could not be enabled: :reason',
    'cannot_enable' => 'The addon cannot be enabled yet: :reason',
    'enabled' => '":name" has been enabled.',
    'disabled' => '":name" has been disabled.',

    'turnstile' => [
        'name' => 'Cloudflare Turnstile',
        'description' => 'Protects the login, the password reset and the registration from bots with Cloudflare Turnstile, a captcha that usually needs no click.',
        'keys' => 'Keys',
        'keys_help' => 'Create a widget for the domain of the panel in the <a href=":url" target="_blank" rel="noopener">Cloudflare dashboard under Turnstile</a> and enter its keys here.',
        'site_key' => 'Site Key',
        'secret_key' => 'Secret Key',
        'secret_key_help' => 'Only used by the panel to ask Cloudflare whether a check was passed. Stored encrypted.',
        'secret_key_set' => 'Saved – leave empty to keep it',
        'forms_title' => 'Protected Forms',
        'forms' => [
            'login' => 'Login',
            'password' => 'Password reset',
            'register' => 'Registration',
        ],
        'no_forms' => 'No form protected',
        'missing_keys' => 'Enter the site key and the secret key first.',
    ],

    'registration' => [
        'name' => 'Registration',
        'description' => 'Visitors can create an account themselves. The email address is confirmed with a six digit code, afterwards they are logged in right away.',
        'code_lifetime' => 'Code Validity',
        'code_lifetime_help' => 'Minutes the code sent by email stays valid (5–60).',
        'minutes' => 'minutes',
        'allowed_domains' => 'Allowed Email Domains',
        'allowed_domains_help' => 'Only addresses of these domains can register, e.g. <code>example.com, example.org</code>. Leave empty to allow every domain.',
        'mail_hint' => 'The code is sent by email. Make sure the <a href=":url">mail settings</a> work.',
        'turnstile_hint' => 'Combine it with the <a href=":url">Cloudflare Turnstile</a> addon to keep bots from creating accounts.',
        'summary_lifetime' => 'Code valid for :minutes minutes',
        'summary_all_domains' => 'All email domains',
        'summary_domains' => 'Only :domains',
    ],
];

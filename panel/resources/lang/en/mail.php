<?php

/*
 * Emails sent by the panel. They use the language of the recipient
 * (User::preferredLocale()).
 */
return [
    'hello' => 'Hello!',
    'hello_name' => 'Hello :name!',
    'whoops' => 'Whoops!',
    'regards' => 'Regards,',
    'trouble' => 'If you’re having trouble clicking the ":action" button, copy and paste the URL below into your web browser:',
    'rights' => 'All rights reserved.',

    'account_created' => [
        'subject' => 'Your account on :app',
        'intro' => 'You are receiving this email because an account has been created for you on :app.',
        'username' => 'Username: :username',
        'email' => 'Email: :email',
        'action' => 'Setup Your Account',
    ],
    'added_to_server' => [
        'subject' => 'You have been added to a server',
        'intro' => 'You have been added as a subuser for the following server, allowing you certain control over the server.',
        'server' => 'Server Name: :name',
        'action' => 'Visit Server',
    ],
    'removed_from_server' => [
        'subject' => 'You have been removed from a server',
        'intro' => 'You have been removed as a subuser for the following server.',
        'server' => 'Server Name: :name',
        'action' => 'Visit Panel',
    ],
    'server_installed' => [
        'subject' => 'Your server is ready',
        'intro' => 'Your server has finished installing and is now ready for you to use.',
        'server' => 'Server Name: :name',
        'action' => 'Login and Begin Using',
    ],
    'password_reset' => [
        'subject' => 'Reset Password',
        'intro' => 'You are receiving this email because we received a password reset request for your account.',
        'action' => 'Reset Password',
        'outro' => 'If you did not request a password reset, no further action is required.',
    ],
    'test' => [
        'subject' => ':app Test Message',
        'line' => "This is a test of the :app mail system. You're good to go!",
    ],
    'registration_code' => [
        'subject' => ':code is your code for :app',
        'intro' => 'Use this code to confirm your email address and finish creating your account on :app:',
        'lifetime' => 'The code is valid for :minutes minutes.',
        'outro' => 'If you did not try to create an account, you can ignore this email.',
    ],
];

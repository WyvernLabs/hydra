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
    // Hydra: suspension of a server and deletion of the account.
    'server_suspended' => [
        'subject' => 'Your server :name has been suspended',
        'intro' => 'An administrator has suspended your server ":name". It can no longer be started.',
        'access' => 'You can still access its files, databases and backups.',
        'deletion' => 'If the server stays suspended, it will be deleted automatically after :days days.',
        'outro' => 'If you think this is a mistake, please contact support.',
    ],
    'account_deleted' => [
        'subject' => 'Your :app account has been deleted',
        'intro' => 'Your account on :app has been deleted.',
        'data' => 'Your data has been removed and you can no longer log in.',
        'outro' => 'If you did not ask for this, please contact support.',
    ],
    'account_banned' => [
        'subject' => 'Your account has been banned',
        'intro' => 'An administrator has banned your account. You can no longer log in.',
        'reason' => 'Reason: :reason',
        'until' => 'The ban ends on :date.',
        'permanent' => 'The ban is permanent.',
        'servers' => 'All of your servers have been suspended.',
        'deletion' => 'Your account and your servers will be deleted automatically after :days days.',
        'outro' => 'If you think this is a mistake, please contact the support.',
    ],
];

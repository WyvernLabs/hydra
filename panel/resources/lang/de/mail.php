<?php

/*
 * E-Mails des Panels. Sie werden in der Sprache des Empfängers verschickt
 * (User::preferredLocale()).
 */
return [
    'hello' => 'Hallo!',
    'hello_name' => 'Hallo :name!',
    'whoops' => 'Hoppla!',
    'regards' => 'Viele Grüße,',
    'trouble' => 'Falls die Schaltfläche „:action“ nicht funktioniert, kopiere diese Adresse in deinen Browser:',
    'rights' => 'Alle Rechte vorbehalten.',

    'password_reset' => [
        'subject' => 'Passwort zurücksetzen',
        'intro' => 'Du erhältst diese E-Mail, weil für dein Konto das Zurücksetzen des Passworts angefordert wurde.',
        'action' => 'Passwort zurücksetzen',
        'outro' => 'Wenn du das nicht angefordert hast, musst du nichts weiter tun.',
    ],
    'test' => [
        'subject' => 'Testnachricht von :app',
        'line' => 'Das ist ein Test des E-Mail-Versands von :app. Alles funktioniert!',
    ],
    'registration_code' => [
        'subject' => ':code ist dein Code für :app',
        'intro' => 'Mit diesem Code bestätigst du deine E-Mail-Adresse und schließt die Registrierung bei :app ab:',
        'lifetime' => 'Der Code ist :minutes Minuten gültig.',
        'outro' => 'Wenn du kein Konto anlegen wolltest, kannst du diese E-Mail ignorieren.',
    ],
    // Hydra: Sperre eines Servers und Löschung des Kontos.
    'server_suspended' => [
        'subject' => 'Dein Server :name wurde gesperrt',
        'intro' => 'Ein Administrator hat deinen Server „:name“ gesperrt. Er kann nicht mehr gestartet werden.',
        'access' => 'Auf Dateien, Datenbanken und Backups kannst du weiterhin zugreifen.',
        'deletion' => 'Bleibt der Server gesperrt, wird er nach :days Tagen automatisch gelöscht.',
        'outro' => 'Wenn du denkst, dass es sich um einen Fehler handelt, wende dich bitte an den Support.',
    ],
    'account_deleted' => [
        'subject' => 'Dein Konto bei :app wurde gelöscht',
        'intro' => 'Dein Konto bei :app wurde gelöscht.',
        'data' => 'Deine Daten wurden entfernt, eine Anmeldung ist nicht mehr möglich.',
        'outro' => 'Wenn du das nicht veranlasst hast, wende dich bitte an den Support.',
    ],
    'account_banned' => [
        'subject' => 'Dein Konto wurde gesperrt',
        'intro' => 'Ein Administrator hat dein Konto gesperrt. Du kannst dich nicht mehr anmelden.',
        'reason' => 'Grund: :reason',
        'until' => 'Die Sperre endet am :date.',
        'permanent' => 'Die Sperre ist dauerhaft.',
        'servers' => 'Alle deine Server wurden gesperrt.',
        'deletion' => 'Dein Konto und deine Server werden nach :days Tagen automatisch gelöscht.',
        'outro' => 'Wenn du denkst, dass es sich um einen Fehler handelt, wende dich bitte an den Support.',
    ],
];

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

    'account_created' => [
        'subject' => 'Dein Konto bei :app',
        'intro' => 'Für dich wurde ein Konto bei :app angelegt.',
        'username' => 'Benutzername: :username',
        'email' => 'E-Mail: :email',
        'action' => 'Konto einrichten',
    ],
    'added_to_server' => [
        'subject' => 'Du wurdest zu einem Server hinzugefügt',
        'intro' => 'Du wurdest als Unterbenutzer zu diesem Server hinzugefügt und kannst ihn jetzt teilweise verwalten.',
        'server' => 'Server: :name',
        'action' => 'Server öffnen',
    ],
    'removed_from_server' => [
        'subject' => 'Du wurdest von einem Server entfernt',
        'intro' => 'Du wurdest als Unterbenutzer von diesem Server entfernt.',
        'server' => 'Server: :name',
        'action' => 'Panel öffnen',
    ],
    'server_installed' => [
        'subject' => 'Dein Server ist bereit',
        'intro' => 'Dein Server ist fertig installiert und kann jetzt genutzt werden.',
        'server' => 'Server: :name',
        'action' => 'Anmelden und loslegen',
    ],
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
    // Hydra: Verwarnungen und Sperren.
    'ticket_answered' => [
        'subject' => 'Neue Antwort auf dein Ticket #:id',
        'intro' => 'Unser Team hat auf dein Ticket „:subject“ geantwortet.',
        'action' => 'Ticket öffnen',
    ],
    'account_warned' => [
        'subject' => 'Du hast eine Verwarnung erhalten',
        'intro' => 'Ein Administrator hat dein Konto verwarnt.',
        'reason' => 'Grund: :reason',
        'outro' => 'Bitte halte dich an die Regeln, weitere Verstöße können zu einer Sperre führen. Die Verwarnung wird dir auch beim Anmelden angezeigt.',
        'action' => 'Panel öffnen',
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

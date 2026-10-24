<?php

/*
 * Hydra: Support-Tickets im Adminbereich (Community > Tickets, Addon Tickets).
 */
return [
    'title' => 'Tickets',
    'subtitle' => 'Support-Anfragen deiner Benutzer.',
    'list' => 'Tickets',
    'view_title' => 'Ticket #:id',
    'search' => 'Betreff, Benutzer oder #ID suchen',
    'empty' => 'Hier gibt es keine Tickets.',

    'tabs' => [
        'active' => 'Nicht geschlossen',
        'open' => 'Wartet auf Team',
        'answered' => 'Beantwortet',
        'closed' => 'Geschlossen',
        'all' => 'Alle',
    ],

    'statuses' => [
        'open' => 'Wartet auf Team',
        'answered' => 'Beantwortet',
        'closed' => 'Geschlossen',
    ],

    'priorities' => [
        'low' => 'Niedrig',
        'normal' => 'Normal',
        'high' => 'Hoch',
        'urgent' => 'Dringend',
    ],

    'assigned_any' => 'Alle Teammitglieder',
    'assigned_me' => 'Mir zugewiesen',
    'assigned_none' => 'Nicht zugewiesen',

    'subject' => 'Betreff',
    'user' => 'Benutzer',
    'server' => 'Server',
    'language' => 'Sprache',
    'language_any' => 'Alle Sprachen',
    'category' => 'Kategorie',
    'priority' => 'Priorität',
    'status' => 'Status',
    'assignee' => 'Zugewiesen an',
    'nobody' => 'Niemand',
    'last_reply' => 'Letzte Antwort',
    'created' => 'Erstellt',
    'details' => 'Details',
    'manage' => 'Verwalten',
    'other_tickets' => 'Weitere Tickets dieses Benutzers',

    'team' => 'Team',
    'internal_note' => 'Interne Notiz',
    'deleted_user' => 'Gelöschter Benutzer',

    'reply' => 'Antworten',
    'send' => 'Senden',
    'internal' => 'Interne Notiz',
    'internal_help' => 'Interne Notizen sieht nur das Team. Sie ändern den Status nicht und verschicken keine E-Mail.',
    'close_after' => 'Ticket nach dem Senden schließen',

    'delete' => 'Ticket löschen',
    'delete_text' => 'Löscht das Ticket mit allen Nachrichten und Bildern. Das lässt sich nicht rückgängig machen.',
    'delete_confirm' => 'Dieses Ticket wirklich löschen?',

    'editor' => [
        'bold' => 'Fett',
        'italic' => 'Kursiv',
        'code' => 'Code',
        'quote' => 'Zitat',
        'list' => 'Liste',
        'link' => 'Link',
        'image' => 'Bild hochladen',
        'write' => 'Schreiben',
        'preview' => 'Vorschau',
        'placeholder' => 'Schreibe deine Antwort … Markdown wird unterstützt.',
        'hint' => 'Markdown wird unterstützt. Bilder (PNG, JPG, GIF, WebP, bis :size MB) fügst du per Knopf, Einfügen oder Hineinziehen hinzu.',
        'uploading' => 'Wird hochgeladen',
        'too_large' => 'Das Bild ist größer als :size MB.',
        'failed' => 'Das Bild konnte nicht hochgeladen werden.',
        'empty' => 'Nichts für die Vorschau.',
    ],

    'alerts' => [
        'replied' => 'Deine Antwort wurde gesendet.',
        'note_added' => 'Die interne Notiz wurde gespeichert.',
        'updated' => 'Das Ticket wurde aktualisiert.',
        'deleted' => 'Ticket #:id wurde gelöscht.',
    ],
];

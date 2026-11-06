<?php

return [
    'title' => 'Statusseite: Anwendungen',
    'subtitle' => 'Eigene Anwendungen neben den Nodes auf der Statusseite.',
    'list' => 'Anwendungen',
    'none' => 'Es gibt noch keine Anwendungen. Die Nodes stehen automatisch auf der Statusseite.',
    'open_page' => 'Statusseite öffnen',
    'add' => 'Anwendung hinzufügen',
    'edit' => 'Anwendung bearbeiten',
    'name' => 'Name',
    'type' => 'Prüfung',
    'types' => [
        'http' => 'Webseite (HTTP/HTTPS)',
        'tcp' => 'Port (TCP)',
    ],
    'type_help' => 'Webseite: online, solange sie antwortet, auch mit einer Login-Abfrage; Serverfehler (ab 500) zählen als offline. Port: online, wenn sich der Port verbinden lässt, z. B. für Datenbanken oder Spieleserver.',
    'target' => 'Ziel',
    'target_help' => 'Eine Adresse wie https://example.com oder host:port wie db.example.com:3306. Das Ziel ist nicht öffentlich sichtbar, nur der Name.',
    'target_invalid_http' => 'Gib eine Adresse an, die mit http:// oder https:// beginnt.',
    'target_invalid_tcp' => 'Gib das Ziel als host:port an, z. B. db.example.com:3306.',
    'state' => 'Zustand',
    'sort' => 'Reihenfolge',
    'enabled' => 'Prüfen und auf der Statusseite anzeigen',
    'saved' => '„:name“ wurde gespeichert.',
    'deleted' => '„:name“ wurde gelöscht.',
];

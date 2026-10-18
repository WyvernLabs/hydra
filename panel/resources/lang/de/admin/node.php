<?php

return [
    'validation' => [
        'fqdn_not_resolvable' => 'Die angegebene Domain oder IP-Adresse löst nicht auf eine gültige IP-Adresse auf.',
        'fqdn_required_for_ssl' => 'Für SSL braucht dieser Node eine Domain, die auf eine öffentliche IP-Adresse auflöst.',
    ],
    'notices' => [
        'allocations_added' => 'Die Adressen wurden zu diesem Node hinzugefügt.',
        'node_deleted' => 'Der Node wurde aus dem Panel entfernt.',
        'location_required' => 'Lege zuerst mindestens einen Standort an, bevor du einen Node hinzufügst.',
        'node_created' => 'Der Node wurde erstellt. Im Reiter „Konfiguration“ kannst du den Daemon auf dem Server automatisch einrichten. Bevor du Server anlegen kannst, musst du mindestens eine IP-Adresse mit Port zuweisen.',
        'node_updated' => 'Die Node-Daten wurden aktualisiert. Wurden Daemon-Einstellungen geändert, musst du den Daemon neu starten.',
        'unallocated_deleted' => 'Alle nicht zugewiesenen Ports für <code>:ip</code> wurden gelöscht.',
    ],
];

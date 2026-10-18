<?php

return [
    'daemon_connection_failed' => 'Bei der Kommunikation mit dem Daemon ist ein Fehler aufgetreten (HTTP-Statuscode :code). Der Fehler wurde protokolliert.',
    'node' => [
        'servers_attached' => 'Ein Node kann nur gelöscht werden, wenn ihm keine Server mehr zugeordnet sind.',
        'daemon_off_config_updated' => 'Die Daemon-Konfiguration wurde aktualisiert, die Konfigurationsdatei auf dem Daemon konnte aber nicht automatisch angepasst werden. Aktualisiere die config.yml des Daemons manuell, damit die Änderungen wirksam werden.',
    ],
    'allocations' => [
        'server_using' => 'Dieser Adresse ist ein Server zugeordnet. Eine Adresse kann nur gelöscht werden, wenn ihr kein Server zugeordnet ist.',
        'too_many_ports' => 'Es können nicht mehr als 1000 Ports auf einmal in einem Bereich hinzugefügt werden.',
        'invalid_mapping' => 'Die Angabe für Port :port ist ungültig und konnte nicht verarbeitet werden.',
        'cidr_out_of_range' => 'In CIDR-Schreibweise sind nur Masken zwischen /25 und /32 erlaubt.',
        'port_out_of_range' => 'Ports müssen größer als 1024 und höchstens 65535 sein.',
    ],
    'nest' => [
        'delete_has_servers' => 'Ein Nest, dem noch aktive Server zugeordnet sind, kann nicht gelöscht werden.',
        'egg' => [
            'delete_has_servers' => 'Ein Egg, das noch von aktiven Servern genutzt wird, kann nicht gelöscht werden.',
            'invalid_copy_id' => 'Das Egg, von dem das Skript kopiert werden soll, existiert nicht oder kopiert selbst ein Skript.',
            'must_be_child' => 'Die Einstellung „Einstellungen kopieren von“ muss auf ein Egg des ausgewählten Nests verweisen.',
            'has_children' => 'Andere Eggs übernehmen Einstellungen von diesem Egg. Lösche zuerst diese Eggs.',
        ],
        'variables' => [
            'env_not_unique' => 'Die Umgebungsvariable :name muss innerhalb dieses Eggs eindeutig sein.',
            'reserved_name' => 'Die Umgebungsvariable :name ist geschützt und kann keiner Variable zugewiesen werden.',
            'bad_validation_rule' => 'Die Validierungsregel ":rule" ist keine gültige Regel.',
        ],
        'importer' => [
            'json_error' => 'Beim Einlesen der JSON-Datei ist ein Fehler aufgetreten: :error.',
            'file_error' => 'Die angegebene JSON-Datei ist ungültig.',
            'invalid_json_provided' => 'Das Format der angegebenen JSON-Datei wurde nicht erkannt.',
        ],
    ],
    'subusers' => [
        'editing_self' => 'Du kannst dein eigenes Unterbenutzer-Konto nicht bearbeiten.',
        'user_is_owner' => 'Der Besitzer des Servers kann nicht als Unterbenutzer hinzugefügt werden.',
        'subuser_exists' => 'Ein Benutzer mit dieser E-Mail-Adresse ist bereits Unterbenutzer dieses Servers.',
    ],
    'databases' => [
        'delete_has_databases' => 'Ein Datenbank-Host mit aktiven Datenbanken kann nicht gelöscht werden.',
    ],
    'tasks' => [
        'chain_interval_too_long' => 'Der Abstand zwischen verketteten Aufgaben darf höchstens 15 Minuten betragen.',
    ],
    'locations' => [
        'has_nodes' => 'Ein Standort mit aktiven Nodes kann nicht gelöscht werden.',
    ],
    'users' => [
        'node_revocation_failed' => 'Die Schlüssel auf <a href=":link">Node #:node</a> konnten nicht widerrufen werden. :error',
    ],
    'deployment' => [
        'no_viable_nodes' => 'Es wurde kein Node gefunden, der die Anforderungen für die automatische Bereitstellung erfüllt.',
        'no_viable_allocations' => 'Es wurde keine Adresse gefunden, die die Anforderungen für die automatische Bereitstellung erfüllt.',
    ],
    'api' => [
        'resource_not_found' => 'Die angeforderte Ressource existiert auf diesem Server nicht.',
    ],
];

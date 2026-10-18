<?php

return [
    'notices' => [
        'created' => 'Das neue Nest :name wurde erstellt.',
        'deleted' => 'Das Nest wurde gelöscht.',
        'updated' => 'Die Einstellungen des Nests wurden aktualisiert.',
    ],
    'eggs' => [
        'notices' => [
            'imported' => 'Das Egg und seine Variablen wurden importiert.',
            'updated_via_import' => 'Das Egg wurde mit der angegebenen Datei aktualisiert.',
            'deleted' => 'Das Egg wurde gelöscht.',
            'updated' => 'Die Einstellungen des Eggs wurden aktualisiert.',
            'script_updated' => 'Das Installationsskript des Eggs wurde aktualisiert und wird bei jeder Serverinstallation ausgeführt.',
            'egg_created' => 'Das neue Egg wurde erstellt. Starte laufende Daemons neu, damit sie das Egg verwenden können.',
        ],
    ],
    'variables' => [
        'notices' => [
            'variable_deleted' => 'Die Variable ":variable" wurde gelöscht und steht Servern nach einem Neuaufbau nicht mehr zur Verfügung.',
            'variable_updated' => 'Die Variable ":variable" wurde aktualisiert. Server, die sie verwenden, müssen neu aufgebaut werden, damit die Änderung wirkt.',
            'variable_created' => 'Die neue Variable wurde erstellt und diesem Egg zugewiesen.',
        ],
    ],
];

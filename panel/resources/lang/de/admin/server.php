<?php

return [
    'exceptions' => [
        'no_new_default_allocation' => 'Du versuchst, die Standardadresse dieses Servers zu löschen, es gibt aber keine Ersatzadresse.',
        'marked_as_failed' => 'Bei diesem Server ist eine frühere Installation fehlgeschlagen. Der Status kann in diesem Zustand nicht umgeschaltet werden.',
        'skipping_install_script' => 'Dieser Server überspringt das Installationsskript seines Eggs. Eine Neuinstallation ist erst möglich, wenn diese Einstellung deaktiviert ist.',
        'bad_variable' => 'Die Variable :name ist ungültig.',
        'daemon_exception' => 'Bei der Kommunikation mit dem Daemon ist ein Fehler aufgetreten (HTTP-Statuscode :code). Der Fehler wurde protokolliert. (Anfrage-ID: :request_id)',
        'default_allocation_not_found' => 'Die gewünschte Standardadresse gehört nicht zu diesem Server.',
    ],
    'alerts' => [
        'startup_changed' => 'Die Startkonfiguration dieses Servers wurde aktualisiert. Wurde Nest oder Egg geändert, wird der Server jetzt neu installiert.',
        'server_deleted' => 'Der Server wurde gelöscht.',
        'server_created' => 'Der Server wurde im Panel erstellt. Der Daemon braucht einige Minuten, um ihn vollständig zu installieren.',
        'build_updated' => 'Die Ressourcen dieses Servers wurden aktualisiert. Manche Änderungen werden erst nach einem Neustart wirksam.',
        'suspension_toggled' => 'Der Sperrstatus des Servers wurde auf :status geändert.',
        'rebuild_on_boot' => 'Der Docker-Container dieses Servers wird beim nächsten Start neu aufgebaut.',
        'install_toggled' => 'Der Installationsstatus dieses Servers wurde umgeschaltet.',
        'server_reinstalled' => 'Die Neuinstallation dieses Servers wurde gestartet.',
        'details_updated' => 'Die Serverdetails wurden aktualisiert.',
        'docker_image_updated' => 'Das Standard-Docker-Image dieses Servers wurde geändert. Ein Neustart ist nötig, damit die Änderung wirkt.',
        'node_required' => 'Lege zuerst mindestens einen Node an, bevor du einen Server hinzufügst.',
        'transfer_nodes_required' => 'Für Servertransfers brauchst du mindestens zwei Nodes.',
        'transfer_started' => 'Der Servertransfer wurde gestartet.',
        'transfer_not_viable' => 'Der gewählte Node hat nicht genug Speicherplatz oder Arbeitsspeicher für diesen Server.',
    ],
];

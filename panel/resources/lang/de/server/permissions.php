<?php

/*
 * Bezeichnungen und Beschreibungen der Unterbenutzer-Rechte (Pterodactyl\Models\Permission).
 * Fehlende Einträge fallen auf die englischen Texte im Modell zurück.
 */
return [
    'websocket' => [
        'title' => 'Websocket',
        'description' => 'Erlaubt die Verbindung zum Websocket des Servers und damit das Ansehen der Konsolenausgabe und der Live-Statistiken.',
        'keys' => [
            'connect' => 'Erlaubt die Verbindung zum Websocket, um die Konsole zu verfolgen.',
        ],
        'labels' => [
            'connect' => 'Verbinden',
        ],
    ],

    'control' => [
        'title' => 'Steuerung',
        'description' => 'Rechte zum Starten und Stoppen des Servers und zum Senden von Befehlen.',
        'keys' => [
            'console' => 'Erlaubt das Senden von Befehlen über die Konsole.',
            'start' => 'Erlaubt das Starten des Servers, wenn er gestoppt ist.',
            'stop' => 'Erlaubt das Stoppen des Servers, wenn er läuft.',
            'restart' => 'Erlaubt den Neustart des Servers. Damit kann er auch gestartet, aber nicht dauerhaft gestoppt werden.',
        ],
        'labels' => [
            'console' => 'Konsole',
            'start' => 'Starten',
            'stop' => 'Stoppen',
            'restart' => 'Neustart',
        ],
    ],

    'user' => [
        'title' => 'Unterbenutzer',
        'description' => 'Rechte zur Verwaltung anderer Unterbenutzer. Das eigene Konto und Rechte, die man selbst nicht hat, können nie bearbeitet bzw. vergeben werden.',
        'keys' => [
            'create' => 'Erlaubt das Anlegen neuer Unterbenutzer.',
            'read' => 'Erlaubt das Ansehen der Unterbenutzer und ihrer Rechte.',
            'update' => 'Erlaubt das Bearbeiten anderer Unterbenutzer.',
            'delete' => 'Erlaubt das Entfernen von Unterbenutzern.',
        ],
        'labels' => [
            'create' => 'Anlegen',
            'read' => 'Ansehen',
            'update' => 'Bearbeiten',
            'delete' => 'Löschen',
        ],
    ],

    'file' => [
        'title' => 'Dateien',
        'description' => 'Rechte zum Bearbeiten der Dateien dieses Servers.',
        'keys' => [
            'create' => 'Erlaubt das Anlegen von Dateien und Ordnern über das Panel oder per Upload.',
            'read' => 'Erlaubt das Ansehen von Ordnerinhalten, aber nicht das Öffnen oder Herunterladen von Dateien.',
            'read-content' => 'Erlaubt das Öffnen von Dateien. Damit können Dateien auch heruntergeladen werden.',
            'update' => 'Erlaubt das Ändern bestehender Dateien und Ordner.',
            'delete' => 'Erlaubt das Löschen von Dateien und Ordnern.',
            'archive' => 'Erlaubt das Packen von Ordnern und das Entpacken vorhandener Archive.',
            'sftp' => 'Erlaubt die Verbindung per SFTP und die Dateiverwaltung im Rahmen der übrigen Dateirechte.',
        ],
        'labels' => [
            'create' => 'Anlegen',
            'read' => 'Ansehen',
            'read-content' => 'Inhalt lesen',
            'update' => 'Bearbeiten',
            'delete' => 'Löschen',
            'archive' => 'Archive',
            'sftp' => 'SFTP',
        ],
    ],

    'backup' => [
        'title' => 'Backups',
        'description' => 'Rechte zum Erstellen und Verwalten von Backups.',
        'keys' => [
            'create' => 'Erlaubt das Erstellen neuer Backups.',
            'read' => 'Erlaubt das Ansehen aller Backups dieses Servers.',
            'delete' => 'Erlaubt das Löschen von Backups.',
            'download' => 'Erlaubt das Herunterladen von Backups. Achtung: Damit sind alle Dateien des Servers im Backup zugänglich.',
            'restore' => 'Erlaubt das Wiederherstellen von Backups. Achtung: Dabei können alle Serverdateien gelöscht werden.',
            'sftp' => 'Erlaubt das Einrichten des eigenen SFTP-Servers für Backups. Achtung: Backups enthalten alle Dateien des Servers.',
        ],
        'labels' => [
            'create' => 'Erstellen',
            'read' => 'Ansehen',
            'delete' => 'Löschen',
            'download' => 'Herunterladen',
            'restore' => 'Wiederherstellen',
            'sftp' => 'Eigener SFTP-Server',
        ],
    ],

    'allocation' => [
        'title' => 'Netzwerk',
        'description' => 'Rechte zum Ändern der Netzwerkadressen (Ports) dieses Servers.',
        'keys' => [
            'read' => 'Erlaubt das Ansehen aller zugewiesenen Adressen. Die primäre Adresse sieht jeder Benutzer mit Zugriff auf den Server.',
            'create' => 'Erlaubt das Hinzufügen weiterer Adressen.',
            'update' => 'Erlaubt das Ändern der primären Adresse und das Bearbeiten von Notizen.',
            'delete' => 'Erlaubt das Entfernen von Adressen.',
        ],
        'labels' => [
            'read' => 'Ansehen',
            'create' => 'Hinzufügen',
            'update' => 'Bearbeiten',
            'delete' => 'Löschen',
        ],
    ],

    'startup' => [
        'title' => 'Start',
        'description' => 'Rechte zum Ansehen der Startparameter dieses Servers.',
        'keys' => [
            'read' => 'Erlaubt das Ansehen der Startvariablen.',
            'update' => 'Erlaubt das Ändern der Startvariablen.',
            'docker-image' => 'Erlaubt das Ändern des Docker-Images, mit dem der Server läuft.',
        ],
        'labels' => [
            'read' => 'Ansehen',
            'update' => 'Bearbeiten',
            'docker-image' => 'Docker-Image',
        ],
    ],

    'database' => [
        'title' => 'Datenbanken',
        'description' => 'Rechte zur Verwaltung der Datenbanken dieses Servers.',
        'keys' => [
            'create' => 'Erlaubt das Anlegen neuer Datenbanken.',
            'read' => 'Erlaubt das Ansehen der Datenbanken dieses Servers.',
            'update' => 'Erlaubt das Erneuern von Datenbankpasswörtern. Ohne „Passwort ansehen“ bleibt das neue Passwort verborgen.',
            'delete' => 'Erlaubt das Löschen von Datenbanken.',
            'view_password' => 'Erlaubt das Ansehen der Datenbankpasswörter.',
            'browse' => 'Erlaubt das Ansehen der Tabellen und Daten im Panel.',
            'edit' => 'Erlaubt das Bearbeiten von Daten und SQL-Befehle im Panel.',
            'import' => 'Erlaubt das Einspielen von SQL-Dateien in eine Datenbank.',
            'export' => 'Erlaubt das Herunterladen einer Datenbank als SQL-Datei.',
            'phpmyadmin' => 'Erlaubt das Öffnen der Datenbanken in phpMyAdmin, mit vollen Rechten auf die Datenbank.',
        ],
        'labels' => [
            'create' => 'Anlegen',
            'read' => 'Ansehen',
            'update' => 'Bearbeiten',
            'delete' => 'Löschen',
            'view_password' => 'Passwort ansehen',
            'browse' => 'Daten ansehen',
            'edit' => 'Daten bearbeiten',
            'import' => 'Importieren',
            'export' => 'Exportieren',
            'phpmyadmin' => 'phpMyAdmin',
        ],
    ],

    'schedule' => [
        'title' => 'Zeitpläne',
        'description' => 'Rechte zur Verwaltung der Zeitpläne dieses Servers.',
        'keys' => [
            'create' => 'Erlaubt das Anlegen neuer Zeitpläne.',
            'read' => 'Erlaubt das Ansehen von Zeitplänen und ihren Aufgaben.',
            'update' => 'Erlaubt das Bearbeiten von Zeitplänen und Aufgaben.',
            'delete' => 'Erlaubt das Löschen von Zeitplänen.',
        ],
        'labels' => [
            'create' => 'Anlegen',
            'read' => 'Ansehen',
            'update' => 'Bearbeiten',
            'delete' => 'Löschen',
        ],
    ],

    'settings' => [
        'title' => 'Einstellungen',
        'description' => 'Rechte für die Einstellungen dieses Servers.',
        'keys' => [
            'rename' => 'Erlaubt das Umbenennen des Servers und das Ändern seiner Beschreibung.',
            'reinstall' => 'Erlaubt das Neuinstallieren des Servers.',
            'game' => 'Erlaubt das Wechseln des Spiels sowie das Laden und Löschen von Spielprofilen. Achtung: Dabei werden die Dateien des Servers ersetzt.',
            'version' => 'Erlaubt das Installieren einer anderen Minecraft-Software oder -Version. Achtung: Dabei können Dateien des Servers ersetzt oder gelöscht werden.',
        ],
        'labels' => [
            'rename' => 'Umbenennen',
            'reinstall' => 'Neu installieren',
            'game' => 'Spiel wechseln',
            'version' => 'Minecraft-Version wechseln',
        ],
    ],

    'activity' => [
        'title' => 'Aktivität',
        'description' => 'Rechte für das Aktivitätsprotokoll des Servers.',
        'keys' => [
            'read' => 'Erlaubt das Ansehen des Aktivitätsprotokolls.',
        ],
        'labels' => [
            'read' => 'Ansehen',
        ],
    ],
];

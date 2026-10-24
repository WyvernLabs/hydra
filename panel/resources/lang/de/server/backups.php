<?php

/*
 * Hydra: Backup-Speicher und Backups auf SFTP-Servern.
 */
return [
    'disabled' => 'Dieser Server hat keinen Speicher für Backups.',
    'full' => 'Der Backup-Speicher dieses Servers ist voll. Lösche zuerst ältere Backups.',
    'no_sftp' => 'Es ist kein eigener SFTP-Server für Backups eingerichtet.',
    'credentials_required' => 'Gib ein Passwort oder einen privaten Schlüssel ein.',
    'sftp_error' => 'SFTP-Server: :message',
    'sftp_in_use' => 'Auf diesem SFTP-Server liegen noch Backups. Lösche sie zuerst.',
    'sftp_file_missing' => 'Diese Datei liegt nicht mehr auf dem SFTP-Server.',
    'sftp_file_imported' => 'Diese Datei ist bereits in der Backup-Liste.',
];

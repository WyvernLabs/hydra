<?php

// Hydra: messages of the database manager, import and export in the client area.
return [
    'errors' => [
        'connection' => 'Could not connect to the database: :message',
        'table_missing' => 'The table ":table" does not exist.',
        'unknown_column' => 'The column ":column" does not exist or cannot be changed here.',
        'no_primary_key' => 'This table has no primary key, so rows cannot be changed here. Use the SQL console instead.',
        'row_missing' => 'The row no longer exists. Reload the table.',
        'tool_missing' => 'The MariaDB client tools are missing on the panel server. Run "hydra-panel update" on it.',
        'import_read' => 'The uploaded file could not be read.',
        'import_failed' => 'The import failed: :message',
        'phpmyadmin' => 'phpMyAdmin is not available.',
    ],
];

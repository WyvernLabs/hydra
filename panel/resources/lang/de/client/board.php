<?php

/*
 * Hydra: Kanban-Board eines Servers (Addon "board").
 */
return [
    'title' => 'Board',
    'summary' => ':columns Spalten · :cards Blöcke',
    'loading' => 'Board wird geladen …',
    'empty' => 'Dieses Board hat noch keine Spalten.',
    'empty_manage' => 'Dieses Board hat noch keine Spalten. Leg eine Spalte an, zum Beispiel "Offen", "In Arbeit" und "Erledigt".',

    'actions' => [
        'refresh' => 'Aktualisieren',
        'cancel' => 'Abbrechen',
        'save' => 'Speichern',
        'close' => 'Schließen',
    ],

    'color' => [
        'none' => 'Keine Farbe',
        'custom' => 'Andere Farbe',
    ],

    'column' => [
        'create' => 'Spalte hinzufügen',
        'create_first' => 'Erste Spalte anlegen',
        'edit' => 'Spalte bearbeiten',
        'delete' => 'Spalte löschen',
        'delete_title' => 'Spalte ":name" löschen?',
        'delete_with_cards_one' => 'Die Spalte und ihr Block werden endgültig gelöscht.',
        'delete_with_cards_other' => 'Die Spalte und ihre :count Blöcke werden endgültig gelöscht.',
        'delete_empty' => 'Die leere Spalte wird gelöscht.',
        'name' => 'Name',
        'name_placeholder' => 'z. B. In Arbeit',
        'color' => 'Farbe',
        'empty' => 'Keine Blöcke',
    ],

    'card' => [
        'title' => 'Block',
        'name' => 'Name',
        'description' => 'Beschreibung',
        'description_placeholder' => 'Beschreibung hinzufügen …',
        'no_description' => 'Keine Beschreibung.',
        'has_description' => 'Hat eine Beschreibung',
        'claim' => 'Geclaimt von',
        'claim_action' => 'Claimen',
        'release' => 'Freigeben',
        'unclaimed' => 'Niemand arbeitet an diesem Block.',
        'claimed_since' => 'geclaimt :time',
        'claimed_by' => 'Geclaimt von :user',
        'you' => 'Du',
        'column' => 'Spalte',
        'tags' => 'Tags',
        'created' => 'Erstellt :time',
        'delete' => 'Block löschen',
        'delete_confirm' => 'Diesen Block endgültig löschen?',
        'add' => 'Block hinzufügen',
        'new_placeholder' => 'Name des Blocks',
        'new_help' => 'Enter legt den Block an, Esc schließt.',
        'full' => 'Maximum von :max Blöcken erreicht',
    ],

    'checklist' => [
        'title' => 'Checkliste',
        'progress' => ':done von :total erledigt',
        'empty' => 'Keine Einträge in der Checkliste.',
        'full' => 'Maximum von :max Einträgen erreicht.',
        'add_placeholder' => 'Eintrag hinzufügen …',
        'delete' => 'Eintrag entfernen',
    ],

    'tags' => [
        'title' => 'Tags',
        'help' => 'Tags markieren Blöcke, zum Beispiel mit einer Priorität. Name und Farbe bestimmst du selbst.',
        'manage' => 'Tags verwalten',
        'name' => 'Name des Tags',
        'delete' => 'Tag löschen',
        'empty' => 'Noch keine Tags.',
        'full' => 'Maximum von :max Tags erreicht.',
        'new_placeholder' => 'Neuer Tag, z. B. Hohe Priorität',
        'add' => 'Hinzufügen',
    ],
];

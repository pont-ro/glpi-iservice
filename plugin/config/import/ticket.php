<?php
return [
    'itemTypeClass' => Ticket::class,
    'oldTable'      => 'glpi_tickets',
    'checkValues'   => [
        'entities_id' => 0,
    ],
    'foreignKeys'   => [
        'users_id_lastupdater' => 'User',
        'users_id_recipient'   => 'User',
        'itilcategories_id'    => 'ITILCategory',
        'locations_id'         => 'Location',
    ],
    'handleMissingForeignKeys' => [
        'itilcategories_id' => ['force' => 0],
        'locations_id' => ['force' => 0],
    ],
    'clearRelatedTable' => 'glpi_plugin_formcreator_issues',
    'forceValues'   => [
        '_disablenotif'   => 1,
    ],
];

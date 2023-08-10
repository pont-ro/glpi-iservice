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
        'itilcategories_id' => ['add' => 100000000], // Confirm with hupu: setting invalid value will lead to error, we should se to 0 instead.
    ],
];

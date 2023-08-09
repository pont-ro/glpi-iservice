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
    ]
];

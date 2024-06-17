<?php
return [
    'itemTypeClass' => Group_Ticket::class,
    'oldTable'      => 'glpi_groups_tickets',
    'foreignKeys'   => [
        'tickets_id' => 'Ticket',
        'groups_id'  => 'Group',
    ]
];

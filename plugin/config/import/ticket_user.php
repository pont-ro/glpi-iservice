<?php
return [
    'itemTypeClass' => Ticket_User::class,
    'oldTable'      => 'glpi_tickets_users',
    'foreignKeys'   => [
        'tickets_id' => 'Ticket',
        'users_id'   => 'User',
    ]
];

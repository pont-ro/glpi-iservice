<?php
return [
    'itemTypeClass' => Item_Ticket::class,
    'oldTable'      => 'glpi_items_tickets WHERE itemtype NOT IN ("Computer", "Monitor")',
    'foreignKeys'     => [
        'items_id' => [
            'dependsFrom' => 'itemtype',
        ],
        'tickets_id' => 'Ticket',
    ],
];

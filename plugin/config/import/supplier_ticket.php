<?php
return [
    'itemTypeClass' => Supplier_Ticket::class,
    'oldTable'      => 'glpi_suppliers_tickets',
    'foreignKeys'   => [
        'tickets_id'   => 'Ticket',
        'suppliers_id' => 'Supplier',
    ]
];

<?php
return [
    'itemTypeClass'   => TicketTemplate::class,
    'oldTable'        => 'glpi_tickettemplates',
    'identifierField' => 'name',
    'checkValues'     => [
        'entities_id' => 0,
    ],
];

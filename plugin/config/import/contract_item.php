<?php
return [
    'itemTypeClass' => Contract_Item::class,
    'oldTable'      => 'glpi_contracts_items',
    'foreignKeys'   => [
        'items_id'         => [
            'dependsFrom' => 'itemtype',
        ],
        'contracts_id'  => 'Contract',
    ],
];

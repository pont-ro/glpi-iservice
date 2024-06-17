<?php
return [
    'itemTypeClass'            => Infocom::class,
    'oldTable'                 => 'glpi_infocoms WHERE itemtype NOT IN ("Peripheral", "Computer", "ConsumableItem", "Consumable", "Monitor")',
    'foreignKeys'              => [
        'items_id'     => [
            'dependsFrom' => 'itemtype',
        ],
        'suppliers_id' => 'Supplier',
    ],
    'identifierField'          => ['itemtype', 'items_id'],
    'handleMissingForeignKeys' => [
        'items_id' => ['add' => 100000000], // Temporary fix for missing items_id.
    ],
    'ignoreNotAdded'           => true, // Temporary fix for missing items_id.
];

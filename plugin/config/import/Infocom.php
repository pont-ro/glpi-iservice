<?php
return [
    'itemTypeClass' => Infocom::class,
    'oldTable'      => 'glpi_infocoms WHERE itemtype NOT IN ("Peripheral", "Computer", "ConsumableItem", "Consumable", "Monitor")',
    'foreignKeys'   => [
        'items_id'     => [
            'dependsFrom' => 'itemtype',
        ],
        'suppliers_id' => 'Supplier',
    ],
];

<?php
return [
    'itemTypeClass'   => Cartridge::class,
    'oldTable'        => 'glpi_cartridges',
    'foreignKeys'     => [
        'cartridgeitems_id' => 'CartridgeItem',
        'printers_id'       => 'Printer',
    ],
    'forceValues'   => [
        'pages' => null,
    ],
    'updateAfterCreate' => true, // This is needed because Cartridge::prepareInputForAdd method will ignore all input data except cartridgeitems_id.
];

<?php
return [
    'itemTypeClass'   => Cartridge::class,
    'oldTable'        => 'glpi_cartridges',
    'foreignKeys'     => [
        'cartridgeitems_id' => 'CartridgeItem',
        'printers_id'       => 'Printer',
    ],
];

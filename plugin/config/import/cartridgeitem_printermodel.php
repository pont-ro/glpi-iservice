<?php
return [
    'itemTypeClass' => CartridgeItem_PrinterModel::class,
    'oldTable'      => 'glpi_cartridgeitems_printermodels',
    'foreignKeys'   => [
        'cartridgeitems_id' => 'CartridgeItem',
        'printermodels_id'  => 'PrinterModel',
    ],
];

<?php
return [
    'itemTypeClass' => Printer::class,
    'oldTable'      => 'glpi_printers',
    'forceValues'   => [
        'networks_id' => 0,
    ],
    'foreignKeys'   => [
        'users_id_tech'    => 'User',
        'groups_id_tech'   => 'Group',
        'locations_id'     => 'Location',
        'printertypes_id'  => 'PrinterType',
        'printermodels_id' => 'PrinterModel',
        'manufacturers_id' => 'Manufacturer',
        'users_id'         => 'User',
        'groups_id'        => 'Group',
        'states_id'        => 'State',
    ],
];

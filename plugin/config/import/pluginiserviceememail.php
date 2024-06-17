<?php

return [
    'itemTypeClass' => PluginIserviceEMEmail::class,
    'oldTable'      => 'glpi_plugin_iservice_ememails',
    'foreignKeys'   => [
        'printers_id'   => 'Printer',
        'suppliers_id'  => 'Supplier',
        'users_id_tech' => 'User',
    ],
];

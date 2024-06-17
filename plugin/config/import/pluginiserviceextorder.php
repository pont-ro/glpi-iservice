<?php
return [
    'itemTypeClass' => PluginIserviceExtOrder::class,
    'oldTable'      => 'glpi_plugin_iservice_extorders',
    'foreignKeys'   => [
        'users_id'                         => 'User',
        'suppliers_id'                     => 'Supplier',
        'plugin_iservice_orderstatuses_id' => 'PluginIserviceOrderstatus',
    ]
];

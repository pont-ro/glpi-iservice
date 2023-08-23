<?php
return [
    'itemTypeClass' => PluginIserviceIntOrder::class,
    'oldTable'      => 'glpi_plugin_iservice_intorders',
    'foreignKeys'   => [
        'tickets_id'                       => 'Ticket',
        'plugin_iservice_orderstatuses_id' => 'PluginIserviceOrderstatus',
    ]
];

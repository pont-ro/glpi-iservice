<?php
return [
    'itemTypeClass' => PluginIserviceIntOrder_ExtOrder::class,
    'oldTable'      => 'glpi_plugin_iservice_intorders_extorders',
    'foreignKeys'   => [
        'plugin_iservice_extorders_id' => 'PluginIserviceExtOrder',
        'plugin_iservice_intorders_id' => 'PluginIserviceIntOrder',
    ]
];

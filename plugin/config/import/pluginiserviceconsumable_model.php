<?php
return [
    'itemTypeClass' => PluginIserviceConsumable_Model::class,
    'oldTable'      => 'glpi_plugin_iservice_consumables_models',
    'foreignKeys'   => [
        'printermodels_id' => 'PrinterModel',
    ],
    'handleMissingForeignKeys' => [
        'printermodels_id' => ['add' => 100000000],
    ],
];

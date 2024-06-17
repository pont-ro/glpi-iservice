<?php

$additionalFields = [
    [
        'name' => 'items_id',
    ],
    [
        'name' => 'itemtype',
    ],
    [
        'name' => 'plugin_fields_containers_id',
    ]
];

$fieldMap = json_decode(file_get_contents(PLUGIN_ISERVICE_DIR . '/install/customfields/printermodel_customfields.json'), true);


return [
    'itemTypeClass' => PluginFieldsPrintermodelprintermodelcustomfield::class,
    'oldTable'      => 'glpi_plugin_fields_printermodelprintermodelcustomfields',
    'fieldMap'      => array_merge($additionalFields, $fieldMap),
    'forceValues'   => [
        'itemtype' => 'PrinterModel',
    ],
    'foreignKeys'   => [
        'items_id'                    => 'PrinterModel',
        'plugin_fields_containers_id' => 'PluginFieldsContainer',
    ],
];

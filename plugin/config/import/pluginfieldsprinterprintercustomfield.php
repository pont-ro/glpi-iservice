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

$fieldMap = json_decode(file_get_contents(PLUGIN_ISERVICE_DIR . '/install/customfields/printer_customfields.json'), true);


return [
    'itemTypeClass' => PluginFieldsPrinterprintercustomfield::class,
    'oldTable'      => 'glpi_plugin_fields_printercustomfields',
    'fieldMap'      => array_merge($additionalFields, $fieldMap),
    'forceValues'   => [
        'itemtype' => 'Printer',
    ],
    'foreignKeys'   => [
        'items_id'                    => 'Printer',
        'plugin_fields_containers_id' => 'PluginFieldsContainer',
    ],
];

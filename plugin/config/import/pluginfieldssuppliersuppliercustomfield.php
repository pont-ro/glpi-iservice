<?php
$additionalFields = [
    [
        'name'     => 'items_id',
    ],
    [
        'name'     => 'itemtype',
    ],
    [
        'name'     => 'plugin_fields_containers_id',
    ]
];

$fieldMap = json_decode(file_get_contents(PLUGIN_ISERVICE_DIR . '/install/customfields/supplier_customfields.json'), true);



return [
    'itemTypeClass'    => PluginFieldsSuppliersuppliercustomfield::class,
    'oldTable'         => 'glpi_plugin_fields_suppliercustomfields',
    'fieldMap'         => array_merge($additionalFields, $fieldMap),
    'foreignKeys'      => [
        'items_id'                    => 'Supplier',
        'plugin_fields_containers_id' => 'PluginFieldsContainer',
    ],
];

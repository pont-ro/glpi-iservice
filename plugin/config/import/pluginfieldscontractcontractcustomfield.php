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

$fieldMap = json_decode(file_get_contents(PLUGIN_ISERVICE_DIR . '/install/customfields/contract_customfields.json'), true);


return [
    'itemTypeClass' => PluginFieldsContractcontractcustomfield::class,
    'oldTable'      => 'glpi_plugin_fields_contractcustomfields',
    'fieldMap'      => array_merge($additionalFields, $fieldMap),
    'forceValues'   => [
        'itemtype' => 'Contract',
    ],
    'foreignKeys'   => [
        'items_id'                    => 'Contract',
        'plugin_fields_containers_id' => 'PluginFieldsContainer',
    ],
];

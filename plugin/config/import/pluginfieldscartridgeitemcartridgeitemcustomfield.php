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
    ],
    [
        'name'     => 'plugin_fields_cartridgeitemtypedropdowns_id',
        'old_name' => 'plugin_fields_typefielddropdowns_id',
    ]
];

$fieldMap = json_decode(file_get_contents(PLUGIN_ISERVICE_DIR . '/install/customfields/cartridgeitem_customfields.json'), true);


return [
    'itemTypeClass' => PluginFieldsCartridgeitemcartridgeitemcustomfield::class,
    'oldTable'      => 'glpi_plugin_fields_cartridgeitemcartridgecustomfields',
    'fieldMap'      => array_merge($additionalFields, $fieldMap),
    'foreignKeys'   => [
        'items_id'                                    => 'CartridgeItem',
        'plugin_fields_containers_id'                 => 'PluginFieldsContainer',
        'plugin_fields_cartridgeitemtypedropdowns_id' => 'PluginFieldsCartridgeitemtypeDropdown'
    ],
];

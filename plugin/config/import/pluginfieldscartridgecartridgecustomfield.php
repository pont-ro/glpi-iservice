<?php
$additionalFields = [
    [
        'name'     => 'items_id',
        'old_name' => 'id',
    ],
    [
        'name' => 'itemtype',
    ],
    [
        'name' => 'plugin_fields_containers_id',
    ]
];

$fieldMap = json_decode(file_get_contents(PLUGIN_ISERVICE_DIR . '/install/customfields/cartridge_customfields.json'), true);

$fieldsContainer = new PluginFieldsContainer();
$fieldsContainer->getFromDBByCrit(['name' => 'cartridgecustomfield']);
$fieldsContainerId = $fieldsContainer->getField('id');

if (empty($fieldsContainerId) || $fieldsContainerId == NOT_AVAILABLE) {
    return null;
}

return [
    'itemTypeClass' => PluginFieldsCartridgecartridgecustomfield::class,
    'oldTable'      => 'glpi_cartridges',
    'fieldMap'      => array_merge($additionalFields, $fieldMap),
    'forceValues'   => [
        'itemtype'                    => 'Cartridge',
        'plugin_fields_containers_id' => $fieldsContainerId,
    ],
    'foreignKeys'   => [
        'items_id'             => 'Cartridge',
        'tickets_id_use_field' => 'Ticket',
        'tickets_id_out_field' => 'Ticket',
        'suppliers_id_field'   => 'Supplier',
        'locations_id_field'   => 'Location',
    ],
    'handleMissingForeignKeys' => [
        'locations_id_field'   => ['add' => 100000000],
    ],
];

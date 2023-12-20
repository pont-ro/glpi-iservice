<?php

return [
    'itemTypeClass' => PluginIserviceCartridge_Ticket::class,
    'oldTable'      => 'glpi_plugin_iservice_cartridges_tickets',
    'fieldMap'      => [
        [
            'name' => 'tickets_id',
        ],
        [
            'name' => 'cartridges_id',
        ],
        [
            'name' => 'locations_id',
        ],
        [
            'name'     => 'plugin_fields_cartridgeitemtypedropdowns_id',
            'old_name' => 'plugin_fields_typefielddropdowns_id',
        ],
        [
            'name' => 'cartridges_id_emptied',
        ],
    ],
    'foreignKeys'   => [
        'locations_id'                                => 'Location',
        'tickets_id'                                  => 'Ticket',
        'cartridges_id'                               => 'Cartridge',
        'plugin_fields_cartridgeitemtypedropdowns_id' => 'PluginFieldsCartridgeitemtypeDropdown'
    ],
    'handleMissingForeignKeys' => [
        'plugin_fields_cartridgeitemtypedropdowns_id' => ['add' => 100000000],
        'locations_id'                                => ['add' => 100000000],
    ],
];

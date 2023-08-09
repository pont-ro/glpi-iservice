<?php

return [
    'itemTypeClass' => PluginIserviceConsumable_Ticket::class,
    'oldTable'      => 'glpi_plugin_iservice_consumables_tickets',
    'fieldMap'      => [
        [
            'name' => 'locations_id',
        ],
        [
            'name' => 'create_cartridge',
        ],
        [
            'name' => 'tickets_id',
        ],
        [
            'name' => 'plugin_iservice_consumables_id',
        ],
        [
            'name'     => 'plugin_fields_cartridgeitemtypedropdowns_id',
            'old_name' => 'plugin_fields_typefielddropdowns_id',
        ],
        [
            'name' => 'amount',
        ],
        [
            'name' => 'price',
        ],
        [
            'name' => 'euro_price',
        ],
        [
            'name' => 'new_cartridge_ids',
        ],
    ],
    'foreignKeys'   => [
        'locations_id'                                => 'Location',
        'tickets_id'                                  => 'Ticket',
        'plugin_fields_cartridgeitemtypedropdowns_id' => 'PluginFieldsCartridgeitemtypeDropdown'
    ]
];

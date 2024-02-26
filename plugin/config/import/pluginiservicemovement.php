<?php
return [
    'itemTypeClass'            => PluginIserviceMovement::class,
    'oldTable'                 => 'glpi_plugin_iservice_movements',
    'foreignKeys'              => [
        'items_id'         => [
            'dependsFrom' => 'itemtype',
        ],
        'suppliers_id_old' => 'Supplier',
        'suppliers_id'     => 'Supplier',
        'users_id'         => 'User',
        'states_id'        => 'State',
        'locations_id'     => 'Location',
        'users_id_tech'    => 'User',
        'groups_id'        => 'Group',
        'contracts_id'     => 'Contract',
    ],
    'handleMissingForeignKeys' => [
        'suppliers_id_old' => ['add' => 100000000],
        'suppliers_id'     => ['add' => 100000000],
        'contracts_id'     => ['add' => 100000000],
        'states_id'        => ['add' => 100000000],
    ],
    'fieldMap'                 => [
        [
            'name' => 'itemtype',
        ],
        [
            'name' => 'items_id',
        ],
        [
            'name' => 'suppliers_id_old',
        ],
        [
            'name' => 'suppliers_id',
        ],
        [
            'name' => 'init_date',
        ],
        [
            'name' => 'invoice',
        ],
        [
            'name' => 'users_id',
        ],
        [
            'name' => 'states_id',
        ],
        [
            'name' => 'locations_id',
        ],
        [
            'name' => 'usage_address',
        ],
        [
            'name' => 'week_number',
        ],
        [
            'name' => 'users_id_tech',
        ],
        [
            'name' => 'groups_id',
        ],
        [
            'name' => 'contact',
        ],
        [
            'name' => 'contact_num',
        ],
        [
            'name' => 'contracts_id',
        ],
        [
            'name' => 'dba',
        ],
        [
            'name' => 'dca',
        ],
        [
            'name'     => 'invoiced_total_black_field',
            'old_name' => 'total2_black_fact',
        ],
        [
            'name'     => 'invoiced_total_color_field',
            'old_name' => 'total2_color_fact',
        ],
        [
            'name'     => 'invoice_date',
            'old_name' => 'data_fact',
        ],
        [
            'name'     => 'invoice_expiry_date_field',
            'old_name' => 'data_exp_fact',
        ],
        [
            'name' => 'disableem',
        ],
        [
            'name' => 'snoozereadcheck',
        ],
        [
            'name' => 'moved',
        ],
        [
            'name' => 'type',
        ],
    ],
];

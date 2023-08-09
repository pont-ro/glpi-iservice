<?php
return [
    'itemTypeClass'            => PluginIserviceMovement::class,
    'oldTable'                 => 'glpi_plugin_iservice_movements',
    'foreignKeys'              => [
        'items_id'         => [
            'dependsFrom' => 'itemtype',
        ],
        'tickets_id'       => 'Ticket',
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
    ],
];

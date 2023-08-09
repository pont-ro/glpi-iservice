<?php

$ticketexporttypedropdowns = PluginIserviceDB::getQueryResult(
    "SELECT * FROM glpi_plugin_fields_ticketexporttypedropdowns"
);

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
        'name'     => 'plugin_fields_ticketexporttypedropdowns_id',
        'old_name' => 'export_type',
        'valueMap' => array_column($ticketexporttypedropdowns, 'id', 'name'),
        'default'  => '0',
    ]
];

$fieldMap = json_decode(file_get_contents(PLUGIN_ISERVICE_DIR . '/install/customfields/ticket_customfields.json'), true);


return [
    'itemTypeClass' => PluginFieldsTicketticketcustomfield::class,
    'select'        => 'cft.*, t.data_luc, t.total2_black, t.total2_color',
    'oldTable'      => "glpi_plugin_fields_ticketcustomfields cft LEFT JOIN glpi_tickets t ON t.id = cft.items_id AND cft.itemtype = 'Ticket'",
    'fieldMap'      => array_merge($additionalFields, $fieldMap),
    'forceValues'   => [
        'itemtype' => 'Ticket',
    ],
    'foreignKeys'   => [
        'items_id'                    => 'Ticket',
        'plugin_fields_containers_id' => 'PluginFieldsContainer',
        'movement_id_field'           => 'PluginIserviceMovement',
        'movement2_id_field'          => 'PluginIserviceMovement',
        'em_mail_id_field'            => 'PluginIserviceEMEmail',
    ],
];

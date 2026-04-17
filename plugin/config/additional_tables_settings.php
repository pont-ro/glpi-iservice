<?php

return [
    'glpi_plugin_fields_ticketticketcustomfields' => [
        'indexes' => [
            [
                'name' => 'em_mail_id_field',
                'type' => 'index',
                'columns' => "(`em_mail_id_field`)",
            ],
            [
                'name' => 'movement_id_field',
                'type' => 'index',
                'columns' => "(`movement_id_field`)",
            ],
            [
                'name' => 'movement2_id_field',
                'type' => 'index',
                'columns' => "(`movement2_id_field`)",
            ],
        ],
    ],
    'glpi_plugin_fields_cartridgecartridgecustomfields' => [
        'columns' => [
            'printed_pages_field'       => "DECIMAL(11,0) GENERATED ALWAYS AS (IF(`pages_out_field` > 0, `pages_out_field` - `pages_use_field`, NULL)) VIRTUAL",
            'printed_pages_color_field' => "DECIMAL(11,0) GENERATED ALWAYS AS (IF(`pages_color_out_field` > 0, `pages_color_out_field` - `pages_color_use_field`, NULL)) VIRTUAL",
        ],
        'indexes' => [
            [
                'name' => 'tickets_id_use_field',
                'type' => 'index',
                'columns' => "(`tickets_id_use_field`)",
            ],
            [
                'name' => 'tickets_id_out_field',
                'type' => 'index',
                'columns' => "(`tickets_id_out_field`)",
            ],
            [
                'name' => 'suppliers_id_field',
                'type' => 'index',
                'columns' => "(`suppliers_id_field`)",
            ],
            [
                'name' => 'locations_id_field',
                'type' => 'index',
                'columns' => "(`locations_id_field`)",
            ],
            [
                'name' => 'plugin_fields_cartridgeitemtypedropdowns_id',
                'type' => 'index',
                'columns' => "(`plugin_fields_cartridgeitemtypedropdowns_id`)",
            ],
        ],
    ],
    'glpi_plugin_fields_suppliersuppliercustomfields' => [
        'indexes' => [
            [
                'name' => 'hmarfa_code_field',
                'type' => 'index',
                'columns' => "(`hmarfa_code_field`)",
            ],
        ],
    ],
    'glpi_plugin_iservice_consumables_models' => [
        'indexes' => [
            [
                'name' => 'plugin_iservice_consumables_id',
                'type' => 'index',
                'columns' => "(`plugin_iservice_consumables_id`)",
            ],
            [
                'name' => 'printermodels_id',
                'type' => 'index',
                'columns' => "(`printermodels_id`)",
            ],
        ],
    ],
    'glpi_plugin_iservice_minimum_stocks' => [
        'indexes' => [
            [
                'name' => 'plugin_iservice_consumables_id',
                'type' => 'index',
                'columns' => "(`plugin_iservice_consumables_id`)",
            ],
        ],
    ],
];

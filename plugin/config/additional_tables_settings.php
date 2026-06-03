<?php

// Fields plugin stores "number" type as VARCHAR(255) DEFAULT NULL in new installations,
// but older versions used DECIMAL. These column entries ensure existing DECIMAL columns
// are migrated to VARCHAR so MySQL strict mode does not reject empty string saves.
$numberFieldType = 'VARCHAR(255) DEFAULT NULL';

return [
    'glpi_plugin_fields_ticketticketcustomfields' => [
        'columns' => [
            'movement_id_field'  => $numberFieldType, // number
            'movement2_id_field' => $numberFieldType, // number
            'em_mail_id_field'   => $numberFieldType, // number
            'total2_black_field' => $numberFieldType, // number
            'total2_color_field' => $numberFieldType, // number
        ],
        'indexes' => [
            [
                'name'    => 'em_mail_id_field',
                'type'    => 'index',
                'columns' => "(`em_mail_id_field`)",
            ],
            [
                'name'    => 'movement_id_field',
                'type'    => 'index',
                'columns' => "(`movement_id_field`)",
            ],
            [
                'name'    => 'movement2_id_field',
                'type'    => 'index',
                'columns' => "(`movement2_id_field`)",
            ],
        ],
    ],
    'glpi_plugin_fields_cartridgecartridgecustomfields' => [
        'columns' => [
            // Virtual generated columns — must stay DECIMAL, not touched by $numberFieldType
            'printed_pages_field'       => "DECIMAL(11,0) GENERATED ALWAYS AS (IF(`pages_out_field` > 0, `pages_out_field` - `pages_use_field`, NULL)) VIRTUAL",
            'printed_pages_color_field' => "DECIMAL(11,0) GENERATED ALWAYS AS (IF(`pages_color_out_field` > 0, `pages_color_out_field` - `pages_color_use_field`, NULL)) VIRTUAL",
            // number fields
            'tickets_id_use_field'                     => $numberFieldType,
            'tickets_id_out_field'                     => $numberFieldType,
            'pages_out_field'                          => $numberFieldType,
            'pages_color_out_field'                    => $numberFieldType,
            'pages_use_field'                          => $numberFieldType,
            'pages_color_use_field'                    => $numberFieldType,
            'suppliers_id_field'                       => $numberFieldType,
            'locations_id_field'                       => $numberFieldType,
            'plugin_fields_cartridgeitemtypedropdowns_id' => $numberFieldType,
        ],
        'indexes' => [
            [
                'name'    => 'tickets_id_use_field',
                'type'    => 'index',
                'columns' => "(`tickets_id_use_field`)",
            ],
            [
                'name'    => 'tickets_id_out_field',
                'type'    => 'index',
                'columns' => "(`tickets_id_out_field`)",
            ],
            [
                'name'    => 'suppliers_id_field',
                'type'    => 'index',
                'columns' => "(`suppliers_id_field`)",
            ],
            [
                'name'    => 'locations_id_field',
                'type'    => 'index',
                'columns' => "(`locations_id_field`)",
            ],
            [
                'name'    => 'plugin_fields_cartridgeitemtypedropdowns_id',
                'type'    => 'index',
                'columns' => "(`plugin_fields_cartridgeitemtypedropdowns_id`)",
            ],
        ],
    ],
    'glpi_plugin_fields_suppliersuppliercustomfields' => [
        'columns' => [
            'payment_deadline_field' => $numberFieldType, // number
        ],
        'indexes' => [
            [
                'name'    => 'hmarfa_code_field',
                'type'    => 'index',
                'columns' => "(`hmarfa_code_field`)",
            ],
        ],
    ],
    'glpi_plugin_fields_printerprintercustomfields' => [
        'columns' => [
            'invoiced_total_black_field'  => $numberFieldType, // number
            'invoiced_total_color_field'  => $numberFieldType, // number
            'invoiced_value_field'        => $numberFieldType, // number
            'week_nr_field'               => $numberFieldType, // number
            'daily_bk_average_field'      => $numberFieldType, // number
            'daily_color_average_field'   => $numberFieldType, // number
            'uc_bk_field'                 => $numberFieldType, // number
            'uc_cyan_field'               => $numberFieldType, // number
            'uc_magenta_field'            => $numberFieldType, // number
            'uc_yellow_field'             => $numberFieldType, // number
        ],
    ],
    'glpi_plugin_fields_contractcontractcustomfields' => [
        'columns' => [
            'copy_price_bk_field'       => $numberFieldType, // number
            'copy_price_col_field'      => $numberFieldType, // number
            'included_copies_bk_field'  => $numberFieldType, // number
            'included_copies_col_field' => $numberFieldType, // number
            'included_copy_value_field' => $numberFieldType, // number
            'monthly_fee_field'         => $numberFieldType, // number
            'currency_field'            => $numberFieldType, // number
            'copy_price_divider_field'  => $numberFieldType, // number
        ],
    ],
    'glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields' => [
        'columns' => [
            'atc_field'              => $numberFieldType, // number
            'life_coefficient_field' => $numberFieldType, // number
        ],
    ],
    'glpi_plugin_iservice_consumables_models' => [
        'indexes' => [
            [
                'name'    => 'plugin_iservice_consumables_id',
                'type'    => 'index',
                'columns' => "(`plugin_iservice_consumables_id`)",
            ],
            [
                'name'    => 'printermodels_id',
                'type'    => 'index',
                'columns' => "(`printermodels_id`)",
            ],
        ],
    ],
    'glpi_plugin_iservice_minimum_stocks' => [
        'indexes' => [
            [
                'name'    => 'plugin_iservice_consumables_id',
                'type'    => 'index',
                'columns' => "(`plugin_iservice_consumables_id`)",
            ],
        ],
    ],
];

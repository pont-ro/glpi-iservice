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
];

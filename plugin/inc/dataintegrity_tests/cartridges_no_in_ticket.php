<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select c.id
        from glpi_cartridges c
        left join glpi_plugin_iservice_consumables_tickets ct on ct.amount > 0 and ct.new_cartridge_ids like concat('%|', c.id, '|%')
        where ct.id is null
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges without in ticket'
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridges without in ticket',
            'iteration_text' => "Cartridge <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/view.php?view=cartridges&cartridges0%5Bid%5D=[id]' target='_blank'>[id]</a> has no in ticket",
        ],
    ],
    'schedule' => [
        'display_last_result' => true,
        'hours'               => [3],
        'ignore_text'         => [
            'hours' => "Checked only at 3",
        ]
    ]
];

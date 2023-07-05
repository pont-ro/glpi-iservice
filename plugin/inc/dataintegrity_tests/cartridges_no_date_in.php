<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select c.id
        from glpi_cartridges c
        where c.date_in is null
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges without in date'
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridges without in date',
            'iteration_text' => "Cartridge <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/view.php?view=cartridges&cartridges0%5Bid%5D=[id]' target='_blank'>[id]</a> has no in date",
        ],
    ],
];

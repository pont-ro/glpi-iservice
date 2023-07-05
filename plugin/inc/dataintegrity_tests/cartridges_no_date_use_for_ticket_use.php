<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select c.id, c.tickets_id_use
        from glpi_cartridges c
        left join glpi_tickets t on t.id = c.tickets_id_use and t.`status` = 6
        where c.date_use is null and c.tickets_id_use is not null;
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges with no instalation date but an installer ticket'
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridges with no instalation date but an installer ticket',
            'iteration_text' => "Cartridge <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/view.php?view=cartridges&cartridges0%5Bid%5D=[id]' target='_blank'>[id]</a> has installer tikcet <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tickets_id_use]&mode=9999' target='_blank'>[tickets_id_use]</a> but no installation date",
        ],
    ],
];

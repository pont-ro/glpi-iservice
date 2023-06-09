<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select c.id, c.tickets_id_out
        from glpi_cartridges c
        left join glpi_plugin_iservice_cartridges_tickets ct on ct.tickets_id = c.tickets_id_out
        where ct.id is null and c.tickets_id_use is not null and c.tickets_id_out is not null
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges with invalid uninstaller ticket'
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridges with with invalid uninstaller ticket',
            'iteration_text' => "Tikcet <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tickets_id_out]&mode=9999' target='_blank'>[tickets_id_out]</a> installs no cartridges but uninstalls cartridge <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/view.php?view=cartridges&cartridges0%5Bid%5D=[id]' target='_blank'>[id]</a>",
        ],
    ],
];

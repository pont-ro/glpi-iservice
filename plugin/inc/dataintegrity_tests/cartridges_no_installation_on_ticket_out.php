<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select c.id, c.tickets_id_out_field
        from glpi_plugin_iservice_cartridges c
        left join glpi_plugin_iservice_cartridges_tickets ct on ct.tickets_id = c.tickets_id_out_field
        where ct.id is null and c.tickets_id_use_field is not null and c.tickets_id_out_field is not null
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges with invalid uninstaller ticket'
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridges with with invalid uninstaller ticket',
            'iteration_text' => "Tikcet <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tickets_id_out_field]&mode=9999' target='_blank'>[tickets_id_out_field]</a> installs no cartridges but uninstalls cartridge <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=GlpiPlugin\Iservice\Specialviews\Cartridges&cartridges0%5Bid%5D=[id]' target='_blank'>[id]</a>",
        ],
    ],
];

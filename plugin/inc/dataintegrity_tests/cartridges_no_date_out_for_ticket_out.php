<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select c.id, c.tickets_id_out_field
        from glpi_plugin_iservice_cartridges c
        join glpi_tickets t on t.id = c.tickets_id_out_field and t.`status` = 6
        where c.date_out is null
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges with no uninstallation date but an uninstaller ticket'
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridges with no uninstallation date but an uninstaller ticket',
            'iteration_text' => "Cartridge <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/view.php?view=cartridges&cartridges0%5Bid%5D=[id]' target='_blank'>[id]</a> has uninstaller tikcet <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tickets_id_out_field]&mode=9999' target='_blank'>[tickets_id_out_field]</a> but no uninstallation date",
        ],
    ],
];

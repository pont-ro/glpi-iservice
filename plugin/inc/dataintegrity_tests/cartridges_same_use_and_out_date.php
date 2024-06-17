<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select c.id, c.tickets_id_use_field ticket_id
        from glpi_plugin_iservice_cartridges c
        where c.tickets_id_use_field = c.tickets_id_out_field
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges with the same installer and uninstaller ticket'
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridges with the same installer and uninstaller ticket',
            'iteration_text' => "Cartridge <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Cartridges&cartridges0%5Bid%5D=[id]' target='_blank'>[id]</a> has the same use and out ticket: <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[ticket_id]' target='_blank'>[ticket_id]</a>",
        ],
    ],
];

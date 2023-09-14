<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select c.id, c.printers_id, c.printed_pages_field bk, c.printed_pages_color_field color, c.tickets_id_use_field install_ticket_id, c.tickets_id_out_field out_ticket_id
        from glpi_plugin_iservice_cartridges c
        where c.printed_pages_field < 0 or c.printed_pages_color_field < 0
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges with negative printed pages',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridges with negative printed pages',
            'iteration_text' => "Cartridge <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=GlpiPlugin\Iservice\Specialviews\Cartridges&cartridges0%5Bid%5D=[id]' target='_blank'>[id]</a> on printer <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=GlpiPlugin\Iservice\Specialviews\Operations&operations0%5Bprinter_id%5D=[printers_id]' target='_blank'>[printers_id]</a> has negative printed pages: [bk] black and [color] color - installer tikcet <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[install_ticket_id]&mode=9999' target='_blank'>[install_ticket_id]</a>, remover ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[out_ticket_id]&mode=9999' target='_blank'>[out_ticket_id]</a>",
        ],
    ],
];

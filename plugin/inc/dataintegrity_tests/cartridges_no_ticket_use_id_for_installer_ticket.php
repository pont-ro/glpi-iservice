<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select c.id cid, t.id tid, t.cartridge_install_date_field date_install, t.total2_black_field, t.total2_color_field
        from glpi_plugin_iservice_cartridges c
        join glpi_plugin_iservice_cartridges_tickets ct on ct.cartridges_id = c.id
        join glpi_plugin_iservice_tickets t on t.id = ct.tickets_id and t.status = " . Ticket::CLOSED . "
        where c.tickets_id_use_field is null
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges with unmarked installer ticket'
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridges with unmarked installer ticket',
            'iteration_text' => "Cartridge <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Cartridges&cartridges0%5Bid%5D=[cid]' target='_blank'>[cid]</a> has installer tikcet <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]&mode=9999' target='_blank'>[tid]</a>, but it is not saved on the cartridge.",
        ],
    ],
];

<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select *
        from (
              select c.id cid, c.printers_id pid, count(ct.tickets_id) ticket_count, group_concat('<a href=\"$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?mode=9999&id=', ct.tickets_id, '\"  target=\"_blank\">', ct.tickets_id, '</a>' separator ', ') ticket_ids
              from glpi_cartridges c
              join glpi_plugin_iservice_cartridges_tickets ct on ct.cartridges_id = c.id
              join glpi_tickets t on t.id = ct.tickets_id and t.is_deleted = 0
              group by c.id
             ) t
        where t.ticket_count > 1
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges installed by more then 1 ticket',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridges installed by more then 1 ticket',
            'iteration_text' => "Cartridge <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Cartridges&cartridges0%5Bid%5D=[cid]' target='_blank'>[cid]</a> was installed by [ticket_count] tickets: [ticket_ids]. See operations list for printer <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Operations&operations0%5Bprinter_id%5D=[pid]' target='_blank'>[pid]</a>",
        ],
    ],
];

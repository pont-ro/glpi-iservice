<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select t.id tid, it.items_id pid, tm.min_id
        from glpi_plugin_iservice_tickets t
        join glpi_items_tickets it on it.tickets_id = t.id and it.itemtype = 'Printer'
        join glpi_plugin_iservice_cartridges_tickets crt on crt.tickets_id = t.id
        join (select min(s_t.id) min_id, s_it.items_id 
              from glpi_items_tickets s_it
              join glpi_tickets s_t on s_t.id = s_it.tickets_id and s_t.is_deleted = 0 and s_t.`status` = 6
              join glpi_plugin_iservice_cartridges_tickets s_crt on s_crt.tickets_id = s_t.id
              where itemtype = 'Printer'
              group by items_id) tm on tm.items_id = it.items_id
        where t.is_deleted = 0 and t.`status` = 6 and coalesce(t.total2_black_field, 0) + coalesce(t.total2_color_field, 0) = 0 and t.id != tm.min_id
        group by t.id
        order by t.id
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no tickets with no counter that change a cartridge',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} tickets with no counter that change a cartridge',
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]' target='_blank'>[tid]</a> with printer <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Operations&operations0%5Bprinter_id%5D=[pid]' target='_blank'>[pid]</a> has no counter reading, but it changes a cartridge (the first installation was with ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[min_id]' target='_blank'>[min_id]</a>).",
        ],
    ],
];

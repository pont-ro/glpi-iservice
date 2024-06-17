<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select ct.tickets_id tid, count(ct.id) c_count
        from glpi_plugin_iservice_cartridges_tickets ct
        left join glpi_items_tickets it on it.tickets_id = ct.tickets_id
        join glpi_tickets t on t.id = it.tickets_id and t.is_deleted = 0
        where it.id is null
        group by ct.tickets_id
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no tickets without printers but installed cartridges',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} tickets without printers but installed cartridges',
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]' target='_blank'>[tid]</a> has [c_count] installed cartridges but no printer associated",
        ],
    ],
];

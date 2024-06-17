<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select t.id tid, it.items_id pid
        from glpi_tickets t
        join glpi_items_tickets it on it.tickets_id = t.id and it.itemtype = 'Printer' and it.items_id > 0
        left join glpi_suppliers_tickets st on st.tickets_id = t.id
        where t.id > 55000 and st.suppliers_id is null        
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no tickets with printer but without partner.',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} tickets with printer but without partner',
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]' target='_blank'>[tid]</a> has printer <b>[pid]</b> but no partner.",
        ],
    ],
];

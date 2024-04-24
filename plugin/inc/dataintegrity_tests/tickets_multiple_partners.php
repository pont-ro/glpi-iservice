<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select * from (
        select t.id tid, count(st.id) pcount, GROUP_CONCAT(CONCAT(s.name, ' (id ', s.id, ')') SEPARATOR ', ') pnames
        from glpi_tickets t
        join glpi_suppliers_tickets st on st.tickets_id = t.id
        join glpi_suppliers s on s.id = st.suppliers_id
        where t.is_deleted = 0
        group by t.id
        ) ts
        where ts.pcount > 1
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no tickets with multiple partners',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} tickets with multiple partners',
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]' target='_blank'>[tid]</a> has [pcount] partners: [pnames]",
        ],
    ],
];

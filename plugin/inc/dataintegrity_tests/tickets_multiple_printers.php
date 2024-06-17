<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select * from (
        select t.id tid, count(it.id) pcount, GROUP_CONCAT(CONCAT(p.serial, ' (id ', p.id, ')') SEPARATOR ', ') pnames
        from glpi_tickets t
        join glpi_items_tickets it on it.tickets_id = t.id and it.itemtype = 'Printer'
        join glpi_printers p on p.id = it.items_id
        where t.is_deleted = 0
        group by t.id
        ) ts
        where ts.pcount > 1
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no tickets with multiple printers',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} tickets with multiple printers',
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]' target='_blank'>[tid]</a> has [pcount] printers: [pnames]",
        ],
    ],
];

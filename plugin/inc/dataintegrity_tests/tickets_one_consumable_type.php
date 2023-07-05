<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select cst1.tickets_id tid, count(cst1.id) `count`, cst1.plugin_iservice_consumables_id consumable
        from glpi_plugin_iservice_consumables_tickets cst1
        join glpi_plugin_iservice_consumables_tickets cst2 on cst1.tickets_id = cst2.tickets_id and cst1.id != cst2.id and cst1.plugin_iservice_consumables_id = cst2.plugin_iservice_consumables_id
        group by cst1.tickets_id, cst1.plugin_iservice_consumables_id
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no tickets with the same consumable multiple times',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} tickets with the same consumable multiple times',
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]&mode=9999' target='_blank'>[tid]</a> has [count] times the consumable [consumable]",
        ],
    ],
];

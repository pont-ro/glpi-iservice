<?php

global $CFG_PLUGIN_ISERVICE;

return [
    'query' => "
        select
            items_id tid
          , effective_date_field 
        from glpi_plugin_fields_ticketticketcustomfields
        left join glpi_tickets t on t.id = items_id
        where (effective_date_field = '' or effective_date_field is null) and t.is_deleted = 0 and t.date > '$CFG_PLUGIN_ISERVICE[data_integrity_tests_date_from]'
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => "There are no tickets with empty effective date",
        ],
        'positive_result' => [
            'summary_text' => "There are {count} tickets with empty effective date.",
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]' target='_blank'>[tid]</a> has empty effective date.",
        ],
    ],
];

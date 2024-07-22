<?php

return [
    'query' => "
        select
            items_id tid
          , effective_date_field 
        from glpi_plugin_fields_ticketticketcustomfields
        where effective_date_field = '' or effective_date_field is null
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => "There are no tickets with empty effective date.",
        ],
        'positive_result' => [
            'summary_text' => "There are {count} tickets with empty effective date.",
            'iteration_text' => "Ticket <a href='/front/ticket.form.php?id=[tid]' target='_blank'>[tid]</a> has empty effective date.",
        ],
    ],
];

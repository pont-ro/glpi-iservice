<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
            select 
                t.id tid
              , t.effective_date_field
              , ted.name export_type
            from glpi_plugin_iservice_tickets t
            join glpi_plugin_fields_ticketexporttypedropdowns ted on ted.id = t.plugin_fields_ticketexporttypedropdowns_id
            left join glpi_plugin_iservice_consumables_tickets ct on ct.tickets_id = t.id
            where t.is_deleted = 0
              and t.status = 6 
              and t.plugin_fields_ticketexporttypedropdowns_id > 0
              and t.effective_date_field > '$CFG_PLUGIN_ISERVICE[data_integrity_tests_date_from]'
              and ct.id is null
            group by t.id
        ",
    'test' => [
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no tickets with export type set, but no consumables',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} tickets with export type set, but no consumables',
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]' target='_blank'>[tid]</a> with effective date <b>[effective_date_field]</b> has no consumables but <b>[export_type]</b> export type",
        ],
    ],
];

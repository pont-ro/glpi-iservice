<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
            select 
                t.id tid
              , GROUP_CONCAT(ct.plugin_iservice_consumables_id SEPARATOR ', ') consumables
              , t.effective_date_field
            from glpi_plugin_iservice_tickets t
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
            'summary_text' => 'There are no tickets with consumables without export type',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} tickets with consumables without export type',
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[id]' target='_blank'>[id]</a> has [consumable_count] consumables but no export type",
        ],
    ],
];

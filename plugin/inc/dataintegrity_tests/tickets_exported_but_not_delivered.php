<?php
global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select 
            t.id tid
          , t.date_creation
          , tcf.effective_date_field
        from glpi_plugin_fields_ticketticketcustomfields tcf
        join glpi_tickets t on t.id = tcf.items_id and tcf.itemtype = 'Ticket' and t.is_deleted = 0
        where tcf.exported_field = 1
          and coalesce(tcf.delivered_field, 0) = 0
          and tcf.plugin_fields_ticketexporttypedropdowns_id > 0
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => "There are no tickets marked as exported but not marked as delivered",
        ],
        'positive_result' => [
            'summary_text' => "There are {count} tickets marked as exported but not marked as delivered",
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]' target='_blank'>[tid]</a> created at <b>[date_creation]</b> and effective date <b>[effective_date_field]</b> is marked as exported, but is not marked as delivered.",
        ],
    ],
];

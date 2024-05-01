<?php
global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
$effective_date_max_difference = 3;
return [
    'query' => "
        select
            t.id tid
          , t.effective_date_field 
          , mf.max_date max_followup_date
        from glpi_plugin_iservice_tickets t
        join (
          select items_id tid, max(date) max_date
          from glpi_itilfollowups f
          where itemtype = 'Ticket'
          group by f.items_id 
          ) mf on mf.tid = t.id 
        where t.is_deleted = 0
          and t.id > 55000
          and t.status = 6
        and datediff(t.effective_date_field, mf.max_date) > $effective_date_max_difference 
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => "There are no tickets with difference of effective date and last followup effective date greater than $effective_date_max_difference",
        ],
        'positive_result' => [
            'summary_text' => "There are {count} tickets with difference of effective date and last followup effective date greater than $effective_date_max_difference",
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]' target='_blank'>[tid]</a> has effective date <b>[effective_date_field]</b> but the last followup date is <b>[max_followup_date]</b>",
        ],
    ],
];

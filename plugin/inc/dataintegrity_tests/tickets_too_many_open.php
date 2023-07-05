<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select `count`
        from (
            select count(*) `count` 
            from glpi_tickets t 
            where t.is_deleted = 0 
              and t.status <> 6
        ) t
        where t.`count` > $CFG_PLUGIN_ISERVICE[open_ticket_limit] 
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => "There are less than $CFG_PLUGIN_ISERVICE[open_ticket_limit] open tickets.",
        ],
        'positive_result' => [
            'result_type' => 'error',
            'summary_text' => "There are more than $CFG_PLUGIN_ISERVICE[open_ticket_limit] open tickets.",
        ],
    ],
];

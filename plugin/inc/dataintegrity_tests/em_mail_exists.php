<?php
global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
return [
    'query'         => sprintf("select eme.id from glpi_plugin_iservice_ememails eme where eme.date > '%s'", date('Y-m-d H:i:s', strtotime('-2 hours'))),
    'test'          => [
        'type'            => 'compare_query_count',
        'zero_result'     => [
            'summary_text' => 'There are no E-maintenance emails imported in the last 2 hours!',
            'result_type'  => 'warning',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} E-maintenance emails imported in the last 2 hours.',
            'result_type'  => 'info',
        ],
        'snoozed_result'  => [
            'summary_text' => 'E-maintenance mail existence check is snoozed until [snooze_time]',
        ]
    ],
    'enable_snooze' => '2 hours',
    'schedule'      => [
        'hours'       => [10, 11, 12, 13, 14, 15, 16, 17, 18],
        'weekdays'    => [1, 2, 3, 4, 5],
        'ignore_text' => [
            'hours' => "E-maintenance mail existence checked only from 10 to 19",
            'weekdays' => "E-maintenance mail existence checked only on workdays",
        ]
    ]
];

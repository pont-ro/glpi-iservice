<?php
return;
global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        ",
    'test' => [
        'alert' => true,
        'type' => '', // 'compare_query_count', 'string_begins', 'read_file'
        'zero_result' => [
            'summary_text' => 'There are no ...',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} ...',
            'iteration_text' => "...",
        ],
    ],
    'enable_snooze' => false,
    'schedule' => [
        'h:m' => ['1:00', '1-3:*', '*:15-25', '1-3:15-25', '*:15', '1:*'],
        'weekdays' => [],
        'ignore_text' => 'Task ignored due to shcedule',
    ]
];

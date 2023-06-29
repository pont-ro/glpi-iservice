<?php
return [
    'query' => "
        select distinct l1.completename
        from glpi_locations l1
        join glpi_locations l2 on l2.completename = l1.completename and l2.id != l1.id
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no duplicate location names',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} duplicate location names',
            'iteration_text' => "Location name [completename] is defined multiple times",
        ],
    ],
];

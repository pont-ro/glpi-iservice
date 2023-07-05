<?php
global $CFG_GLPI;
return [
    'query' => "
        SELECT id, IF(state = 1, 'cron job seems to not run', 'cron job seems to be stuck') message
        FROM `glpi_crontasks` 
        WHERE TIMESTAMPDIFF(SECOND, lastrun, now()) - frequency >= frequency * 2
          AND mode = 2 AND state = 1
        ORDER BY id
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'Every cron job seems to work fine'
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cron jobs that have errors',
            'iteration_text' => "Cron job <a href='$CFG_GLPI[root_doc]/front/crontask.form.php?id=[id]' target='_blank'>[id]</a> has the following error: [message]"
        ],
    ],
];

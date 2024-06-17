<?php
return [
    'query' => "
        SELECT id, IF(content = '\n', 'Unknown error' , content) message 
        FROM glpi_crontasklogs 
        WHERE crontasks_id = 9 
          AND state = 1 
          AND (content = '\n' OR content = 'Could not connect to mailgate server' OR content LIKE 'Could not find mailgate%')
          AND crontasklogs_id = (SELECT MAX(crontasklogs_id) FROM glpi_crontasklogs WHERE crontasks_id = 9) 
        ORDER BY ID DESC LIMIT 1
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'The mailgate mail collector runs without errors'
        ],
        'positive_result' => [
            'summary_text' => 'The mailgate mail collector has the following error:',
            'iteration_text' => '[message]'
        ],
    ],
];

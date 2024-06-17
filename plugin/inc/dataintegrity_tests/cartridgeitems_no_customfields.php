<?php
return [
    'query' => "
        select ci.id, ci.ref, ci.name
        from glpi_plugin_iservice_cartridge_items ci
        where ci.is_deleted = 0 and ci.id is null
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridge types without customfields',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridge types without customfields',
            'iteration_text' => '[name] with id [id] and hMarfa code [ref] has no custom fields',
        ],
    ],
];

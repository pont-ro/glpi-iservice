<?php
global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
return [
    'string' => $CFG_PLUGIN_ISERVICE['root_doc'],
    'test' => [
        'type' => 'string_begins',
        'parameters' => '/plugins',
        'negative_result' => [
            'summary_text' => "The CFG_PLUGIN_ISERVICE[root_doc] variable ([string]) does not begin with '[parameters]'. Press the <b>Refresh</b> button to resolve this.",
            'result_type' => 'error',
        ],
        'positive_result' => [
            'summary_text' => "The CFG_PLUGIN_ISERVICE[root_doc] variable ([string]) begins with '[parameters]'.",
            'result_type' => 'info',
        ],
    ],
];

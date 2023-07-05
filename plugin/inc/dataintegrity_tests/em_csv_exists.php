<?php
global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
return [
    'file_name'     => PluginIserviceEmaintenance::getImportFilePath(),
    'test'          => [
        'em_alert'        => true,
        'type'            => 'file_modified',
        'compare'         => date('Y-m-d'),
        'zero_result'     => [
            'summary_text' => 'EM CSV file exists',
            'result_type'  => 'em_info',
        ],
        'positive_result'     => [
            'summary_text' => 'EM CSV file exists',
            'result_type'  => 'em_info',
        ],
        'negative_result' => [
            'summary_text' => 'Nu există fișier EM CSV pentru azi',
            'result_type'  => 'em_warning',
        ],
        'no_file_result' => [
            'summary_text' => 'Nu există fișier EM CSV',
            'result_type'  => 'em_error',
        ],
    ],
];

<?php

return [
    'file_name' => PluginIserviceConfig::getConfigValue('hmarfa.import.errors'),
    'test' => [
        'alert' => true,
        'type' => 'read_file',
        'zero_result' => [
            'summary_text' => "There is no need to run the hMarfa reorganization",
        ],
        'positive_result' => [
            'summary_text' => 'A hMarfa reorganization is needed since [file_creation_date]. <a href="javascript:void(0);" onclick="$(\'#hMarfa_reorganization_needed_reason\').toggle();">see errors</a><pre id="hMarfa_reorganization_needed_reason" style="display:none;">[content]</pre>',
        ],
    ],
];

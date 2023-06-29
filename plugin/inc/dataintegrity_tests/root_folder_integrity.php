<?php
global $CFG_PLUGIN_ISERVICE;

if (!function_exists('iservice_custom_command_check_root_folder_integrity')) {
    if (!file_exists($CFG_PLUGIN_ISERVICE['folder_integrity']['report_file'])) {
        file_put_contents($CFG_PLUGIN_ISERVICE['folder_integrity']['report_file'], '');
    }

    function iservice_custom_command_check_root_folder_integrity() {
        global $CFG_PLUGIN_ISERVICE;

        $result = [];
        exec("git -C \"$_SERVER[DOCUMENT_ROOT]\" clean -n", $result);
        file_put_contents(
            $CFG_PLUGIN_ISERVICE['folder_integrity']['report_file'],
            implode("\n", array_map(function($value) {return str_replace("Would remove ", "", $value);}, $result)));
    }
}

return [
    'command_before' => 'check_root_folder_integrity',
    'file_name' => $CFG_PLUGIN_ISERVICE['folder_integrity']['report_file'],
    'test' => [
        'alert' => true,
        'no_cache' => true,
        'type' => 'read_file',
        'zero_result' => [
            'summary_text' => "No server breach detected",
        ],
        'positive_result' => [
            'summary_text' => 'Server breach detected (unknown entries appeared in the file structure) at [file_creation_date]. <a href="javascript:void(0);" onclick="$(\'#root_folder_unknown_entries\').toggle();">see entries</a><pre id="root_folder_unknown_entries" style="display:none;">[content]</pre>',
        ],
    ],
];

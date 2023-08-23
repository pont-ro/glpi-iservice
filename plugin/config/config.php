<?php

/* Array keys must use dot notation, e.g. 'config_override.core.palette' => 'auror'. */

return [
    'version' => '0.0.1',
    'enabled_crons.h_marfa_import' => true,
    'enabled_crons.em_mailgate' => true,
    'enabled_crons.data_integrity_test' => true,
    'enable_header_tests' => true,
    'hmarfa.import.script_file' => '/hMarfaImport.sql',
    'hmarfa.import.errors' => PLUGIN_ISERVICE_CACHE_DIR . "/hMarfaImportErrors",
    'hmarfa.export.default_path' => '/var/sambadir/2x/CSV_HAMOR', // Without trailing slash!
    'emaintenance.import_default_path' => '/var/sambadir/2x/CSV_EM', // Without trailing slash!
    'emaintenance.csv_last_check_date_file' => PLUGIN_ISERVICE_CACHE_DIR . "/csvLastCheckDate",
    'dataintegritytests.folder' => PLUGIN_ISERVICE_DIR . "/inc/dataintegrity_tests",
    'dataintegritytests.cache_timeout' => 3600, // 60 minutes
    'folder_integrity.report_file' => PLUGIN_ISERVICE_CACHE_DIR . "/unknown_directory_entries",
    'open_ticket_limit' => 50,
];

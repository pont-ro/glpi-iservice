<?php

/* Array keys must use dot notation, e.g. 'config_override.core.palette' => 'auror'. */

return [
    'url_base' => ($_SERVER['HTTP_ORIGIN'] ?? '') ?: 'http' . ($_SERVER['HTTPS'] ? 's' : '') . '://' . $_SERVER['HTTP_HOST'],
    'enabled_crons.h_marfa_import' => true,
    'enabled_crons.em_mailgate' => true,
    'enabled_crons.data_integrity_test' => false,
    'enabled_crons.printerDailyAverageCalculator' => true,
    'enabled_crons.mailStockVerify' => true,
    'enabled_crons.mailCartridgeVerify' => true,
    'enable_header_tests' => false,
    'hmarfa.import.script_file' => PLUGIN_ISERVICE_CACHE_DIR . '/hMarfaImport.sql',
    'hmarfa.import.errors' => PLUGIN_ISERVICE_CACHE_DIR . "/hMarfaImportErrors",
    'hmarfa.export.default_path' => '/var/sambadir/2x/CSV_HAMOR_3', // Without trailing slash!
    'emaintenance.import_default_path' => '/var/sambadir/2x/CSV_EM', // Without trailing slash!
    'emaintenance.csv_last_check_date_file' => PLUGIN_ISERVICE_CACHE_DIR . "/csvLastCheckDate",
    'emaintenance.default_email' => "emaintenance@expertline.ro",
    'emaintenance.accepted_senders' => "exlemservice@gmail.com,sendonly@rcm.ec1.srv.ygles.com",
    'dataintegritytests.folder' => PLUGIN_ISERVICE_DIR . "/inc/dataintegrity_tests",
    'dataintegritytests.cache_timeout' => 3600, // 60 minutes
    'folder_integrity.report_file' => PLUGIN_ISERVICE_CACHE_DIR . "/unknown_directory_entries",
    'open_ticket_limit' => 50,
    'views.show_limit' => 'false',
    'plugin.cleanup_on_uninstall' => false,
    'old_db' => [
        'host' => 'localhost',
        'name' => 'iservice2',
        'user' => 'c1iservice',
        'pass' => 'c1iservice.Password'
    ],
    'backup_restore.backup_key'    => 'iservice_v3', // Backup file is displayed only if it contains this string.
    'backup_restore.backup_path'   => '/var/backup', // Without trailing slash!
    'backup_restore.backup_method' => 'bz2', // Supported: sql, bz2.
    'ajax_limit_count' => 1,
];

<?php
namespace GlpiPlugin\Iservice\InstallSteps;

use PluginIserviceDB;

class CreateTablesInstallStep
{

    const CLEANUP_ON_UNINSTALL = true;

    public static function do(): bool
    {
        $tables = include PLUGIN_ISERVICE_DIR . '/config/database_tables.php';

        foreach ($tables as $tableName => $tableConfig) {
            PluginIserviceDB::createTable($tableName, $tableConfig);
        }

        return true;
    }

    public static function undo(): void
    {
        if (!self::CLEANUP_ON_UNINSTALL) {
            return;
        }

        global $DB;

        $DB->runFile(PLUGIN_ISERVICE_DIR . '/install/sql/delete_tables.sql');
    }

}

<?php
namespace GlpiPlugin\Iservice\InstallSteps;

use PluginIserviceDB;

class CreateTablesInstallStep
{

    const CLEANUP_ON_UNINSTALL = false;

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

        $tables = array_reverse(array_keys(include PLUGIN_ISERVICE_DIR . '/config/database_tables.php'));

        foreach ($tables as $tableName) {
            PluginIserviceDB::deleteTable($tableName);
        }
    }

}

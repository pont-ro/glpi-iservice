<?php
namespace GlpiPlugin\Iservice\InstallSteps;

use PluginIserviceDB;

class OptimizeTablesInstallStep
{
    const CLEANUP_ON_UNINSTALL = false;

    public static function do(): bool
    {
        $tables = include PLUGIN_ISERVICE_DIR . '/config/additional_tables_settings.php';

        foreach ($tables as $tableName => $tableConfig) {
            PluginIserviceDB::alterTable($tableName, $tableConfig);
        }

        return true;
    }

    public static function undo(): void
    {
        if (!self::CLEANUP_ON_UNINSTALL) {
            return;
        }
    }

}

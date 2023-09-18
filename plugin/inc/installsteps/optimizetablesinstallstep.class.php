<?php
namespace GlpiPlugin\Iservice\InstallSteps;

use PluginIserviceConfig;
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
        if (!PluginIserviceConfig::getConfigValue('plugin.cleanup_on_uninstall', self::CLEANUP_ON_UNINSTALL)) {
            return;
        }
    }

}

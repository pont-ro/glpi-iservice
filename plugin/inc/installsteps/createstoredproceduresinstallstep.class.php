<?php
namespace GlpiPlugin\Iservice\InstallSteps;

use PluginIserviceConfig;
use PluginIserviceDB;

class CreateStoredProceduresInstallStep
{

    const CLEANUP_ON_UNINSTALL = false;

    public static function do(): bool
    {
        PluginIserviceDB::runScriptFile(PLUGIN_ISERVICE_DIR . '/install/sql/create_stored_procedures.sql');

        return true;
    }

    public static function undo(): void
    {
        if (!PluginIserviceConfig::getConfigValue('plugin.cleanup_on_uninstall', self::CLEANUP_ON_UNINSTALL)) {
            return;
        }

        global $DB;

        PluginIserviceDB::runScriptFile(PLUGIN_ISERVICE_DIR . '/install/sql/delete_stored_procedures.sql');
    }

}

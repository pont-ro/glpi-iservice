<?php
namespace GlpiPlugin\Iservice\InstallSteps;

use PluginIserviceDB;

class PrepareDatabaseInstallStep
{

    const CLEANUP_ON_UNINSTALL = true;

    public static function do(): bool
    {
        PluginIserviceDB::runScriptFile(PLUGIN_ISERVICE_DIR . '/install/sql/create_tables.sql');
        PluginIserviceDB::runScriptFile(PLUGIN_ISERVICE_DIR . '/install/sql/create_views.sql');
        PluginIserviceDB::runScriptFile(PLUGIN_ISERVICE_DIR . '/install/sql/create_stored_procedures.sql');

        return true;
    }

    public static function undo(): void
    {
        if (!self::CLEANUP_ON_UNINSTALL) {
            return;
        }

        global $DB;

        $DB->runFile(PLUGIN_ISERVICE_DIR . '/install/sql/delete_tables.sql');
        $DB->runFile(PLUGIN_ISERVICE_DIR . '/install/sql/delete_views.sql');
        PluginIserviceDB::runScriptFile(PLUGIN_ISERVICE_DIR . '/install/sql/delete_stored_procedures.sql');
    }

}

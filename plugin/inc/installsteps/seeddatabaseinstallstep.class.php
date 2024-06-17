<?php
namespace GlpiPlugin\Iservice\InstallSteps;

use PluginIserviceDB;

class SeedDatabaseInstallStep
{

    const CLEANUP_ON_UNINSTALL = false;

    public static function do(): bool
    {
        PluginIserviceDB::runScriptFile(PLUGIN_ISERVICE_DIR . '/install/sql/seed_database.sql');

        return true;
    }

    public static function undo(): void
    {

    }

}

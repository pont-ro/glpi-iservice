<?php
namespace GlpiPlugin\Iservice\InstallSteps;

use PluginIserviceDB;

class CreateViewsInstallStep
{

    const CLEANUP_ON_UNINSTALL = false;

    public static function do(): bool
    {
        PluginIserviceDB::runScriptFile(PLUGIN_ISERVICE_DIR . '/install/sql/create_views.sql');

        return true;
    }

    public static function undo(): void
    {
        if (!self::CLEANUP_ON_UNINSTALL) {
            return;
        }

        global $DB;

        $DB->runFile(PLUGIN_ISERVICE_DIR . '/install/sql/delete_views.sql');
    }

}

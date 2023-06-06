<?php
namespace GlpiPlugin\Iservice\InstallSteps;

class CreateTablesInstallStep
{

    const CLEANUP_ON_UNINSTALL = true;

    public static function do(): bool
    {
        global $DB;

        $DB->runFile(PLUGIN_ISERVICE_DIR . '/install/sql/create_tables.sql');
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

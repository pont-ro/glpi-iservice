<?php
namespace GlpiPlugin\Iservice\InstallStep;

class CreateTablesInstallStep
{

    const CLEANUP_ON_UNINSTALL = false;

    public static function do(): bool
    {
        global $DB;

        $DB->runFile(GLPI_ROOT . '/plugins/iservice/install/sql/create_tables.sql');
        return true;
    }

    public static function undo(): void
    {
        if (!self::CLEANUP_ON_UNINSTALL) {
            return;
        }

        global $DB;

        $DB->runFile(GLPI_ROOT . '/plugins/iservice/install/sql/delete_tables.sql');
    }

}

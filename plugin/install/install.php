<?php

namespace GlpiPlugin\iService;

use GlpiPlugin\iService\InstallSteps\AddCustomFieldsInstallStep;
use GlpiPlugin\iService\InstallSteps\CreateTablesInstallStep;
use GlpiPlugin\iService\InstallSteps\CreateViewsInstallStep;
use GlpiPlugin\iService\InstallSteps\CreateStoredProceduresInstallStep;
use GlpiPlugin\iService\InstallSteps\SeedDatabaseInstallStep;
use GlpiPlugin\iService\InstallSteps\OverwriteAssetsInstallStep;
use GlpiPlugin\iService\InstallSteps\HandleProfileRightsInstallStep;
use GlpiPlugin\iService\InstallSteps\CronTasksInstallStep;
use GlpiPlugin\iService\InstallSteps\OptimizeTablesInstallStep;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIserviceInstall
{

    public function install(): bool
    {
        $result = OverwriteAssetsInstallStep::do();
        $result = $result && CreateTablesInstallStep::do();
        $result = $result && SeedDatabaseInstallStep::do();
        $result = $result && AddCustomFieldsInstallStep::do();
        $result = $result && CreateViewsInstallStep::do();
        $result = $result && CreateStoredProceduresInstallStep::do();
        $result = $result && OptimizeTablesInstallStep::do();
        $result = $result && HandleProfileRightsInstallStep::do();
        return $result && CronTasksInstallStep::do();
    }

    public function uninstall(): void
    {
        AddCustomFieldsInstallStep::undo();
        CreateTablesInstallStep::undo();
        CreateStoredProceduresInstallStep::undo();
        CreateViewsInstallStep::undo();
        CreateTablesInstallStep::undo();
        OverwriteAssetsInstallStep::undo();
        HandleProfileRightsInstallStep::undo();
    }

    public function isPluginInstalled(): bool
    {
        return true;
    }

}

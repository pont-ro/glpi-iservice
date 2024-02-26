<?php

namespace GlpiPlugin\Iservice\InstallSteps;

use PluginIserviceConfig;
use PluginIserviceDB;

class HandleProfileRightsInstallStep
{
    const CLEANUP_ON_UNINSTALL = false;

    public static function do(): bool
    {
        global $DB;

        $table                = 'glpi_profilerights';
        $superAdminProfileIds = \Profile::getSuperAdminProfilesId();
        $rightsValues         = include PLUGIN_ISERVICE_DIR . '/inc/rights.config.php';

        if (!self::createTableRestoreScript($table)) {
            return false;
        }

        foreach ($rightsValues['onInstallStep'] ?? [] as $rightName => $rightValue) {
            if (!$DB->update(
                $table,
                [
                    'rights' => $rightValue,
                ],
                [
                    'WHERE'  => [
                        'name' => $rightName,
                        'NOT' => ['profiles_id' => $superAdminProfileIds],
                    ],
                ]
            )
            ) {
                return false;
            }
        }

        return true;
    }

    public static function undo(): void
    {
        if (!PluginIserviceConfig::getConfigValue('plugin.cleanup_on_uninstall', self::CLEANUP_ON_UNINSTALL)) {
            return;
        }

        self::runTableRestoreScript('glpi_profilerights');

    }

    public static function createTableRestoreScript($tableName): bool
    {
        global $DB;

        $outputFile = PLUGIN_ISERVICE_DIR . "/install/sql/" . $tableName . "_original.sql";

        $command = "mysqldump -h $DB->dbhost -u $DB->dbuser -p$DB->dbpassword $DB->dbdefault $tableName> $outputFile";

        exec($command, $output, $returnVar);

        return $returnVar === 0;
    }

    public static function runTableRestoreScript($tableName): bool
    {
        $scriptPath = PLUGIN_ISERVICE_DIR . "/install/sql/" . $tableName . "_original.sql";

        PluginIserviceDB::runScriptFile($scriptPath);

        return true;
    }

}

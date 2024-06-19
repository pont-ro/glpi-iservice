<?php

namespace GlpiPlugin\Iservice\InstallSteps;

use PluginIserviceConfig;
use PluginIserviceDB;
use Profile;

class HandleProfileRightsInstallStep
{
    const CLEANUP_ON_UNINSTALL = false;

    public static function do(): bool
    {
        $result = self::removeRightsToRemoveForNotSuperAdmins();
        $result = $result && self::setDefaultProfileRights();
        $result = $result && self::setDefaultProfileRightsForCustomFields();

        return $result;
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

    public static function removeRightsToRemoveForNotSuperAdmins(): bool
    {
        global $DB;

        $table                = 'glpi_profilerights';
        $superAdminProfileIds = \Profile::getSuperAdminProfilesId();
        $rightsValues         = include PLUGIN_ISERVICE_DIR . '/inc/rights.config.php';

        if (!self::createTableRestoreScript($table)) {
            return false;
        }

        foreach ($rightsValues['rightsToRemoveForNotSuperAdmins'] ?? [] as $rightName => $rightValue) {
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

    public static function setDefaultProfileRights(): bool
    {
        global $DB;

        $rightsConfig = self::getRightsConfig();

        foreach ($rightsConfig['defaultValues'] ?? [] as $profileName => $rightValues) {
            foreach ($rightValues as $rightName => $rightValue) {
                $profile = self::getProfileByName($profileName);
                if (!$profile) {
                    return false;
                }

                if (!$DB->update(
                    'glpi_profilerights',
                    [
                        'rights' => $rightValue,
                    ],
                    [
                        'WHERE'  => [
                            'name' => $rightName,
                            'profiles_id' => $profile->fields['id'],
                        ],
                    ]
                )
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function setDefaultProfileRightsForCustomFields(): bool
    {
        global $DB;

        $profilesWithFullAccess = self::getRightsConfig()['customFieldsRightsSettings']['profilesWithFullAccess'] ?? [];

        foreach ($profilesWithFullAccess as $profileName) {
            $profile = self::getProfileByName($profileName);
            if (!$profile) {
                return false;
            }

            if (!$DB->update(
                'glpi_plugin_fields_profiles',
                [
                    'right' => 4,
                ],
                [
                    'WHERE'  => [
                        'profiles_id' => $profile->fields['id'],
                    ],
                ]
            )
            ) {
                return false;
            }
        }

        return true;
    }

    public static function getRightsConfig(): array
    {
        $rightsConfig = include PLUGIN_ISERVICE_DIR . '/inc/rights.config.php';

        return $rightsConfig ?? [];
    }

    public static function getProfileByName(String $profileName): Profile|bool
    {
        $profile = new Profile();
        $result  = $profile->getFromDBByRequest(
            [
                'WHERE' => [
                    'name' => $profileName,
                ],
            ]
        );

        return $result ? $profile : false;
    }

}

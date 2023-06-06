<?php
namespace GlpiPlugin\Iservice\InstallSteps;

class OverwriteAssetsInstallStep
{

    const ASSETS_TO_CHANGE = [
        'pics/favicon.ico',
        'pics/logos/logo-G-100-black.png',
        'pics/logos/logo-G-100-grey.png',
        'pics/logos/logo-G-100-white.png',
        'pics/logos/logo-GLPI-100-black.png',
        'pics/logos/logo-GLPI-100-grey.png',
        'pics/logos/logo-GLPI-100-white.png',
        'pics/logos/logo-GLPI-250-black.png',
        'pics/logos/logo-GLPI-250-grey.png',
        'pics/logos/logo-GLPI-250-white.png',
    ];

    public static function do(): bool
    {
        $result = true;
        foreach (self::ASSETS_TO_CHANGE as $fileName) {
            if (!file_exists(GLPI_ROOT . "/$fileName.iSb")) {
                $result &= rename(GLPI_ROOT . "/$fileName", GLPI_ROOT . "/$fileName.iSb");
                $result &= copy(PLUGIN_ISERVICE_DIR . "/assets/$fileName", GLPI_ROOT . "/$fileName");
            }
        }

        return $result;
    }

    public static function undo(): void
    {
        foreach (self::ASSETS_TO_CHANGE as $fileName) {
            if (file_exists(GLPI_ROOT . "/$fileName.iSb")) {
                unlink(GLPI_ROOT . "/$fileName");
                rename(GLPI_ROOT . "/$fileName.iSb", GLPI_ROOT . "/$fileName");
            }
        }
    }

}

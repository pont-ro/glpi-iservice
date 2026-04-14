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
        'templates/layout/parts/profile_selector.html.twig',
        'templates/layout/parts/user_header.html.twig',
    ];

    private static function getGlpiPath(string $fileName): string
    {
        if (str_starts_with($fileName, 'pics/')) {
            return GLPI_ROOT . "/public/$fileName";
        }
        return GLPI_ROOT . "/$fileName";
    }

    public static function do(): bool
    {
        $result = true;
        foreach (self::ASSETS_TO_CHANGE as $fileName) {
            $glpiPath = self::getGlpiPath($fileName);
            if (!file_exists("$glpiPath.iSb")) {
                $result &= rename($glpiPath, "$glpiPath.iSb");
                $result &= copy(PLUGIN_ISERVICE_DIR . "/assets/$fileName", $glpiPath);
            }
        }

        return $result;
    }

    public static function undo(): void
    {
        foreach (self::ASSETS_TO_CHANGE as $fileName) {
            $glpiPath = self::getGlpiPath($fileName);
            if (file_exists("$glpiPath.iSb")) {
                unlink($glpiPath);
                rename("$glpiPath.iSb", $glpiPath);
            }
        }
    }

}

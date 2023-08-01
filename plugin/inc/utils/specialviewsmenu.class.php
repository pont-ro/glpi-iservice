<?php

namespace GlpiPlugin\Iservice\Utils;

use \Session;

class SpecialViewsMenu
{

    public static function setDropdownNameAndIcon(&$menus): void
    {
        $menuConfig = self::getMenuConfig();

        $menus['specialViews']['title'] = $menuConfig['specialViews']['title'] ?? _n('Special View', 'Special Views', Session::getPluralNumber());
        $menus['specialViews']['icon']  = $menuConfig['specialViews']['icon'] ?? 'ti ti-columns';
    }

    public static function getClasses(): array
    {
        $menuConfig = self::getMenuConfig();

        return isset($menuConfig['specialViews']['classes']) && is_array($menuConfig['specialViews']['classes']) ? $menuConfig['specialViews']['classes'] : [];
    }

    public static function getMenuConfig(): array
    {
        $menuConfig = $_SESSION['plugin']['iservice']['menuConfig'] ?? null;

        if (empty($menuConfig)) {
            $menuConfig = $_SESSION['plugin']['iservice']['menuConfig'] = self::getMenuConfigFromConfigFile();
        }

        return $menuConfig;
    }

    public static function getMenuConfigFromConfigFile(): ?array
    {
        $configFile = GLPI_ROOT . "/plugins/iservice/config/menu.php";

        if (!file_exists($configFile)) {
            return null;
        }

        return include_once GLPI_ROOT . "/plugins/iservice/config/menu.php" ?: [];

    }

}

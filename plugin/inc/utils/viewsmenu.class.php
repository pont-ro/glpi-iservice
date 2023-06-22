<?php

namespace GlpiPlugin\Iservice\Utils;

use \Session;

class ViewsMenu
{

    public static function setDropdownNameAndIcon(&$menus): void
    {
        $menuConfig = self::getMenuConfig();

        $menus['views']['title'] = $menuConfig['views']['title'] ?? _n('View', 'Views', Session::getPluralNumber());
        $menus['views']['icon']  = $menuConfig['views']['icon'] ?? 'ti ti-columns';
    }

    public static function getClasses(): array
    {
        $menuConfig = self::getMenuConfig();

        return isset($menuConfig['views']['classes']) && is_array($menuConfig['views']['classes']) ? $menuConfig['views']['classes'] : [];
    }

    public static function getMenuConfig(): array
    {
        $menuConfig = $_SESSION['plugin']['iservice']['viewMenuClasses'] ?? null;

        if (empty($menuConfig)) {
            $menuConfig = $_SESSION['plugin']['iservice']['viewMenuClasses'] = self::getMenuConfigFromConfigFile();
        }

        return $menuConfig;
    }

    public static function getMenuConfigFromConfigFile(): ?array
    {
        $configFile = GLPI_ROOT . "/plugins/iservice/config/menu.php";

        if (!file_exists($configFile)) {
            return null;
        }

        return include_once GLPI_ROOT . "/plugins/iservice/config/menu.php";

    }

}

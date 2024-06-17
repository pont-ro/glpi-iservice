<?php

namespace GlpiPlugin\Iservice\Utils;

use \Session;
use GlpiPlugin\Iservice\Utils\ToolBox as Toolbox;

class ViewsMenu
{

    public static function setDropdownNameAndIcon(&$menus): void
    {
        $menuConfig = Toolbox::getMenuConfig();

        $menus['views']['title'] = $menuConfig['views']['title'] ?? _n('View', 'Views', Session::getPluralNumber());
        $menus['views']['icon']  = $menuConfig['views']['icon'] ?? 'ti ti-columns';
    }

    public static function getClasses(): array
    {
        $menuConfig = Toolbox::getMenuConfig();

        return isset($menuConfig['views']['classes']) && is_array($menuConfig['views']['classes']) ? $menuConfig['views']['classes'] : [];
    }

}

<?php

namespace GlpiPlugin\Iservice\Utils;

use \Session;
use GlpiPlugin\Iservice\Utils\ToolBox as Toolbox;

class SpecialViewsMenu
{

    public static function setDropdownNameAndIcon(&$menus): void
    {
        $menuConfig = Toolbox::getMenuConfig();

        $menus['specialViews']['title'] = $menuConfig['specialViews']['title'] ?? _tn('Special View', 'Special Views', Session::getPluralNumber());
        $menus['specialViews']['icon']  = $menuConfig['specialViews']['icon'] ?? 'ti ti-columns';
    }

    public static function getClasses(): array
    {
        $menuConfig = Toolbox::getMenuConfig();

        return isset($menuConfig['specialViews']['classes']) && is_array($menuConfig['specialViews']['classes']) ? $menuConfig['specialViews']['classes'] : [];
    }

}

<?php

namespace GlpiPlugin\Iservice\Utils;


use GlpiPlugin\Iservice\Utils\ToolBox as Toolbox;

class IserviceMenu
{

    public static function setDropdownNameAndIcon(&$menus): void
    {
        $menuConfig = Toolbox::getMenuConfig();

        $menus['iService']['title'] = $menuConfig['iService']['title'] ?? __('iService', 'iservice');
        $menus['iService']['icon']  = $menuConfig['iService']['icon'] ?? 'ti ti-columns';
    }

    public static function getMenuUrls($profile): array
    {
        $menuConfig = Toolbox::getMenuConfig();

        if (empty($menuConfig['iService']['content'])) {
            return [];
        }

        return self::filterMenuByProfile($menuConfig['iService']['content'], $profile);
    }

    public static function filterMenuByProfile(array $menuConfig, string $profile): array
    {
        return array_filter(
            $menuConfig, function ($item) use ($profile) {
                if (isset($item['roles']) && !in_array($profile, $item['roles'])) {
                    return false;
                }

                return true;
            }
        );
    }

    public static function getClasses(): array
    {
        $menuConfig = Toolbox::getMenuConfig();

        return is_array($menuConfig['iService']['classes'] ?? '') ? $menuConfig['iService']['classes'] : [];
    }

}

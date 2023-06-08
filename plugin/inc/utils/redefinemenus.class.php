<?php

namespace GlpiPlugin\Iservice\Utils;

class RedefineMenus
{
    public const MENU_ITEMS_TO_REMOVE = [
        'config',
        'assets.content.allassets',
    ];
    public static function redefine($menus): array
    {
        $activeProfile = $_SESSION['glpiactiveprofile']['id'] ?? null;
        $superAdminProfileId = \Profile::getSuperAdminProfilesId();

        if (in_array($activeProfile, $superAdminProfileId)) {
            return $menus;
        }

        foreach (self::MENU_ITEMS_TO_REMOVE as $item) {
            self::removeMenuItem($menus, $item);
        }

        return $menus;
    }

    public static function removeMenuItem(&$menus, $item): void
    {
        $keys = explode('.', $item);
        $current = &$menus;
        foreach ($keys as $subKey) {
            if (isset($current[$subKey])) {
                if (end($keys) === $subKey) {
                    unset($current[$subKey]);
                } else {
                    $current = &$current[$subKey];
                }
            } else {
                break;
            }
        }
    }
}

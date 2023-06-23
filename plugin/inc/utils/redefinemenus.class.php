<?php

namespace GlpiPlugin\Iservice\Utils;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use \Session;
use \PluginIserviceDB;

class RedefineMenus
{
    public const MENU_ITEMS_TO_REMOVE = [
        'config',
        'assets.content.allassets',
    ];

    public static function redefine($menus): array
    {
        $activeProfile        = $_SESSION['glpiactiveprofile']['id'] ?? null;
        $superAdminProfileIds = \Profile::getSuperAdminProfilesId();

        ViewsMenu::setDropdownNameAndIcon($menus);
        self::addDropdownWithHeaderIcons($menus);

        if (in_array($activeProfile, $superAdminProfileIds)) {
            return $menus;
        }

        foreach (self::MENU_ITEMS_TO_REMOVE as $item) {
            self::removeMenuItem($menus, $item);
        }

        return $menus;
    }

    public static function removeMenuItem(&$menus, $item): void
    {
        $keys    = explode('.', $item);
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

    public static function addDropdownWithHeaderIcons(&$menus): void
    {
        $menus['headerIcons'] = [
            'title' => 'Header Icons',
            'icon'  => 'fas fa-user',
            'content' => [
                'temp_element1' => [
                    'title' => 'temp_element1',
                    'icon'  => 'fa fa fa-star header-icon me-1 element1',
                    'page'   => '',
                ],
                'hMarfaImport' => self::getHMarfaMenuItem(),
                'temp_element3' => [
                    'title' => 'temp_element3',
                    'icon'  => 'fa fa-check-circle header-icon me-1 element3',
                    'page'   => '',
                ],
                'temp_element4' => [
                    'title' => 'temp_element2',
                    'icon'  => 'fa fa-print header-icon me-1 element4',
                    'page'   => '',
                ],
                'temp_element5' => [
                    'title' => 'temp_element3',
                    'icon'  => 'fa far fa-envelope header-icon me-1 element6',
                    'page'   => '',
                ],
            ]
        ];
    }

    public static function getHMarfaMenuItem(): array
    {
        $hmarfa_action_fields = [
            'execute' => 'hMarfaImport',
            '_glpi_csrf_token' => Session::getNewCSRFToken(),
            'D_glpi_simple_form' => 1
        ];

        $hmarfa_action_javascriptArray = [];
        foreach ($hmarfa_action_fields as $name => $value) {
            $hmarfa_action_javascriptArray[] = '"' . $name . '": "' . urlencode($value) . '"'; // Pay attention to the quotes, double quotes should be used for key-value pairs!
        }

        $serializedParams = '{' . implode(', ', $hmarfa_action_javascriptArray) . '}';

        $hmarfa_import_lastrun_array = PluginIserviceDB::getQueryResult("select lastrun from glpi_crontasks where itemtype='PluginIserviceHMarfaImporter' and name='hMarfaImport'");
        $hmarfa_import_lastrun       = $hmarfa_import_lastrun_array['0']['lastrun'];
        $hmarfa_button_color_class   = '';

        $hmarfa_import_time_diff = abs(time() - strtotime($hmarfa_import_lastrun)) / (60 * 60);

        if ($hmarfa_import_time_diff > 1) {
            $hmarfa_button_color_class = ($hmarfa_import_time_diff > 2) ? 'text-danger' : 'text-warning';
        }

        return [
            'title' => __('Last execution of hMarfa import', 'iservice') . ': ' . $hmarfa_import_lastrun,
            'icon'  => "fa fa-upload header-icon me-1 hMarfaImport $hmarfa_button_color_class",
            'page'   => $serializedParams,
        ];
    }

}

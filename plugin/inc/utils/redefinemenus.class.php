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
                'dataIntegrityTest' => self::getDataIntegrityTestMenuItem(),
                'hMarfaImport' => self::getHMarfaMenuItem(),
                'dataIntegrityTestNotEm' => self::getDataIntegrityTestMenuItem('!em_'),
                'dataIntegrityTestEm' => self::getDataIntegrityTestMenuItem('em_'),
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
        if (!Session::haveRight('plugin_iservice_hmarfa', UPDATE)) {
            return [];
        }

        $hmarfa_action_fields = [
            'execute' => 'hMarfaImport',
            '_glpi_csrf_token' => Session::getNewCSRFToken(),
            '_glpi_simple_form' => 1
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

    public static function getDataIntegrityTestMenuItem(string $type = ''): array
    {
        if (!Session::haveRight('plugin_iservice_hmarfa', UPDATE)) {
            return [];
        }

        $button_color_class = ''; //TODO add color class

        switch ($type) {
        case '!em_':
            $title = __('Data integrity test returned no errors', 'iservice'); //TODO add translation and custom message based on returned errors
            $icon  = "fa fa-check-circle header-icon me-1 keepUrl dataIntegrityTestNotEm $button_color_class";
            $page  = "/plugins/iservice/front/admintask.php?task=DataIntegrityTest&filter=!em_";
            break;
        case 'em_':
            $title = __('Nu s-au detectat erori E-Maintenance', 'iservice'); //TODO add translation and custom message based on returned errors
            $icon  = "fa fa-print header-icon me-1 keepUrl dataIntegrityTestEm $button_color_class";
            $page  = "/plugins/iservice/front/admintask.php?task=DataIntegrityTest&filter=em_";
            break;
        default:
            $title = __('Data integrity test', 'iservice'); //TODO add translation
            $icon  = "fa fa-star header-icon me-1 dataIntegrityTest $button_color_class";
            $page  = "/plugins/iservice/front/admintask.php?task=DataIntegrityTest";
        }

        return [
            'title' => $title,
            'icon'  => $icon,
            'page'  => $page,
        ];
    }

}

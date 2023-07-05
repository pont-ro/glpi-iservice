<?php

namespace GlpiPlugin\Iservice\Utils;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use \Session;
use \PluginIserviceDB;
use \PluginIserviceTask_DataIntegrityTest;

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
        $dataIntegrityTestMenuItems = self::getDataIntegrityTestMenuItems();

        $menus['headerIcons'] = [
            'title' => 'Header Icons',
            'icon'  => 'fas fa-user',
            'content' => [
                'hMarfaImport' => self::getHMarfaMenuItem(),
                'dataIntegrityTestNotEm' => $dataIntegrityTestMenuItems['!em'] ?? [],
                'dataIntegrityTestEm' => $dataIntegrityTestMenuItems['em'] ?? [],
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

    public static function getDataIntegrityTestMenuItems(string $type = ''): array
    {
        if (!Session::haveRight('plugin_iservice_admintask_DataIntegrityTest', READ)) {
            return [];
        }

        $dataIntegrityTestResults = (new PluginIserviceTask_DataIntegrityTest())->getDisplayResults('header');

        return [
            '!em' => [
                'title' => $dataIntegrityTestResults['notEm']['title'],
                'icon'  => "fa fa-check-circle header-icon me-1 keepUrl dataIntegrityTestNotEm " . $dataIntegrityTestResults['notEm']['color_class'],
                'page'  => "/plugins/iservice/front/admintask.php?task=DataIntegrityTest&filter=!em_",
            ],
            'em' => [
                'title' => $dataIntegrityTestResults['em']['title'],
                'icon' => "fa fa-print header-icon me-1 keepUrl dataIntegrityTestEm " . $dataIntegrityTestResults['em']['color_class'],
                'page' => "/plugins/iservice/front/admintask.php?task=DataIntegrityTest&filter=em_",
            ],
        ];
    }

}

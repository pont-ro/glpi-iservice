<?php

namespace GlpiPlugin\Iservice\Utils;

use PluginIserviceDB;
use PluginIserviceTask_DataIntegrityTest;
use Session;

class RedefineMenus
{
    public const MENU_ITEMS_TO_REMOVE = [
        'assets',
        'config',
        'helpdesk',
        'management',
        'reports',
        'plugins',
        'admin',
        'preference',
    ];

    public static function redefine($menus): array
    {
        $activeProfile        = $_SESSION['glpiactiveprofile']['id'] ?? null;
        $activeProfileName    = $_SESSION['glpiactiveprofile']['name'] ?? null;
        $superAdminProfileIds = \Profile::getSuperAdminProfilesId();

        ViewsMenu::setDropdownNameAndIcon($menus);
        SpecialViewsMenu::setDropdownNameAndIcon($menus);
        IserviceMenu::setDropdownNameAndIcon($menus);
        self::addDropdownWithHeaderIcons($menus);
        self::modifyMenuItems($menus, $activeProfileName);
        self::extendIserviceMenu($menus, $activeProfileName);

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
        $hmarfa_import_lastrun       = $hmarfa_import_lastrun_array['0']['lastrun'] ?? '';
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

        $dataIntegrityTestResults = (new PluginIserviceTask_DataIntegrityTest())->getResultsForHeaderIcons();

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

    public static function modifyMenuItems(&$menus, $activeProfileName): void
    {
        global $CFG_PLUGIN_ISERVICE;

        if ($activeProfileName !== 'super-admin') {
            return;
        }

//        $menus['assets']['content']['cartridgeitem']['page']   = "$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=GlpiPlugin\Iservice\SpecialViews\Cartridges";
//        $menus['assets']['content']['printer']['page']         = "$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=GlpiPlugin\Iservice\SpecialViews\Printers";
//        $menus['management']['content']['contract']['page']    = "$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=GlpiPlugin\Iservice\SpecialViews\Contracts";
//        $menus['tools']['content']['reminder']['page']         = "$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=GlpiPlugin\Iservice\SpecialViews\Reminders";
//        $menus['helpdesk']['content']['ticket']['page']        = "$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=GlpiPlugin\Iservice\SpecialViews\Tickets";
//        $menus['helpdesk']['content']['create_ticket']['page'] = "$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php";

        $menus['admin']['content']['backups'] = [
            'title' => __('Backup/restore', 'iservice'),
            'icon'  => 'fa fa-database',
            'page'  => "$CFG_PLUGIN_ISERVICE[root_doc]/front/admintask.php?task=Backup",
        ];
    }

    public static function extendIserviceMenu(&$menus, $activeProfileName): void
    {
        if (empty($activeProfileName)) {
            return;
        }

        $menus['iService']['content'] = array_merge($menus['iService']['content'] ?? [], IserviceMenu::getMenuUrls($activeProfileName));
    }

}

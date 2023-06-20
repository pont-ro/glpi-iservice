<?php

use Glpi\Dashboard\Dashboard;
use Glpi\Dashboard\Right as DashboardRight;
use GlpiPlugin\iService\PluginIserviceInstall;
use GlpiPlugin\Iservice\Utils\RedefineMenus;

/**
 * Install all necessary elements for the plugin
 *
 * @param array $args Arguments passed from CLI
 *
 * @return boolean
 */
function plugin_iservice_install(array $args = []): bool
{
    include_once __DIR__ . '/install/install.php';
    $install = new PluginIserviceInstall();

    return $install->install();
}

function plugin_iservice_uninstall(): void
{
    include_once __DIR__ . '/install/install.php';
    $install = new PluginIserviceInstall();
    $install->uninstall();
}

function plugin_iservice_hook_formcreator_update_profile(CommonDBTM $item): void
{
    $dashboard = new Dashboard();
    if (!$dashboard->getFromDB('plugin_formcreator_issue_counters')) {
        return;
    }

    $dashboardRight = new DashboardRight();
    $dashboardRight->getFromDBByCrit(
        [
            'dashboards_dashboards_id' => $dashboard->fields['id'],
            'itemtype'                 => Profile::getType(),
            'items_id'                 => $item->getID(),
        ]
    );

    if ($item->fields['interface'] === 'helpdesk') {
        if ($dashboardRight->isNewItem()) {
            $dashboardRight->add(
                [
                    'dashboards_dashboards_id' => $dashboard->fields['id'],
                    'itemtype'                 => Profile::getType(),
                    'items_id'                 => $item->getID(),
                ]
            );
        }
    } else {
        if (!$dashboardRight->isNewItem()) {
            $dashboardRight->delete(['id' => $dashboardRight->getID()], 1);
        }
    }
}

function plugin_iservice_redefine_menus($menus): array
{
    return RedefineMenus::redefine($menus);
}

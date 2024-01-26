<?php

use Glpi\Dashboard\Dashboard;
use Glpi\Dashboard\Right as DashboardRight;
use GlpiPlugin\iService\PluginIserviceInstall;
use GlpiPlugin\Iservice\Utils\RedefineMenus;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

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

function plugin_iservice_ticket_add(Ticket $item)
{
    plugin_iservice_ticket_update_related_movement($item);
}

function plugin_iservice_Ticket_update(Ticket $item)
{
    if (PluginIserviceTicket::wasTicketClosedStatusChanging($item)) {
        PluginIserviceTicket::moveCartridges($item);
    }

    plugin_iservice_ticket_update_related_movement($item);
}

function plugin_iservice_PluginFieldsTicketticketcustomfield_update(PluginFieldsTicketticketcustomfield $item)
{
    PluginIserviceTicket::handleDeliveredStatusChange($item);
}

function redirect_from_central()
{
    global $CFG_PLUGIN_ISERVICE;

    Html::redirect("$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Tickets");
}

function plugin_iservice_pre_Ticket_update(Ticket $item)
{
    // plugin_iservice_ticket_adjust_data_luc($item);
    // plugin_iservice_ticket_adjust_counters($item);
    // if (PluginIserviceTicket::isTicketClosing($item)) {
    // plugin_iservice_ticket_check_if_can_close($item);
    // }
    if (PluginIserviceTicket::isTicketOpening($item)) {
        plugin_iservice_ticket_reopen_newer_tickets($item);
    }
}

/**
 * Reopen all newer tickets.
 *
 * @param Ticket $item
 */
function plugin_iservice_ticket_reopen_newer_tickets(Ticket $parentItem)
{
    $item = new PluginIserviceTicket();
    $item->getFromDB($parentItem->getID());

    if (empty($item->customfields->fields['effective_date_field'])) {
        return;
    }

    global $CFG_PLUGIN_ISERVICE;

    $newer_closed_ticket_ids = PluginIserviceTicket::getNewerClosedTikcetIds(
        $item->getID(),
        $item->customfields->fields['effective_date_field'],
        IserviceToolBox::getInputVariable('suppliers_id'),
        IserviceToolBox::getInputVariable('printer_id')
    );

    $ticket           = new PluginIserviceTicket();
    $reopened_tickets = [];
    foreach (array_keys($newer_closed_ticket_ids) as $closed_ticket_id) {
        $ticket->getFromDB($closed_ticket_id);
        $ticket->processFieldsByInput();
        $ticket->fields['status'] = Ticket::SOLVED;
        if ($ticket->update($ticket->fields)) {
            $reopened_tickets[] = $closed_ticket_id;
        }
    }

    if (!empty($reopened_tickets)) {
        $message = __('The following tickets have been also reopened', 'iservice') . ':<br><ul>';
        foreach ($reopened_tickets as $reopened_ticket_id) {
            $message .= "<li><a class='reopened-ticket' href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=$reopened_ticket_id&show_history=cartridge&mode=" . PluginIserviceTicket::MODE_CLOSE . "'>$reopened_ticket_id</a></li>";
        }

        $message .= "</ul><a href='javascript:none;' onclick='openInNewTab(\".reopened-ticket\");return false;'>" . __('See all', 'iservice') . "</a>";
        Session::addMessageAfterRedirect($message, false, WARNING);
    }
}

/**
 * Updates the related movement if _services_invoiced.
 *
 * @param Ticket $item
 */
function plugin_iservice_ticket_update_related_movement(Ticket $item)
{
    if (!empty($item->input['_services_invoiced'])) {
        $movement = new PluginIserviceMovement();
        $movement->update(
            [
                'id' => ($item->input['movement_id_field'] ?? 0) ?: $item->input['movement2_id_field'] ?? 0,
                'invoice' => 1,
            ]
        );
    }
}

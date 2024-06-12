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

function plugin_iservice_Ticket_add(Ticket $item)
{
}

function plugin_iservice_Ticket_update(Ticket $item)
{
    if (PluginIserviceTicket::wasTicketClosedStatusChanging($item)) {
        PluginIserviceTicket::moveCartridges($item);
    }
}

function plugin_iservice_PluginFieldsTicketticketcustomfield_update(PluginFieldsTicketticketcustomfield $item)
{
    PluginIserviceTicket::handleDeliveredStatusChange($item);
}

function plugin_iservice_pre_Ticket_update(Ticket $item)
{
    // plugin_iservice_ticket_adjust_data_luc($item);
    // plugin_iservice_ticket_adjust_counters($item);
    if (PluginIserviceTicket::isTicketClosing($item)) {
        plugin_iservice_ticket_check_if_can_close($item);
    }

    if (PluginIserviceTicket::isTicketOpening($item)) {
        plugin_iservice_ticket_reopen_newer_tickets($item);
    }
}

function redirect_from_central()
{
    if (!IserviceToolBox::inProfileArray(['admin', 'super-admin'])) {
        global $CFG_PLUGIN_ISERVICE;
        Html::redirect("$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Tickets");
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
            $message .= "<li><a class='reopened-ticket' href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=$reopened_ticket_id&show_history=cartridge'>$reopened_ticket_id</a></li>";
        }

        $message .= "</ul><a href='javascript:none;' onclick='openInNewTab(\".reopened-ticket\");return false;'>" . __('See all', 'iservice') . "</a>";
        Session::addMessageAfterRedirect($message, false, WARNING);
    }
}

function plugin_iservice_ticket_check_if_can_close(Ticket $item)
{
    global $CFG_PLUGIN_ISERVICE;
    $can_close = true;

    // Do not allow to close a ticket if there is an older opened ticket.
    $first_open_ticket_id = PluginIserviceTicket::getFirstIdForItemWithInput($item, true);

    $first_open_ticket = new PluginIserviceTicket();
    if ($first_open_ticket->getFromDB($first_open_ticket_id) && $first_open_ticket->customfields->fields['effective_date_field'] <= $item->input['effective_date_field']) {
        if ($first_open_ticket_id > 0 && ($item->isNewID($item->getID()) || $first_open_ticket_id < $item->getID())) {
            $can_close = false;
            Session::addMessageAfterRedirect("Tichetul nu poate fi închis deoarece există un tichet deschis cu data efectivă mai mică (<a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=$first_open_ticket_id&mode=" . PluginIserviceTicket::MODE_CLOSE . "' target='_blank'>$first_open_ticket_id</a>)!", true, WARNING);
        }
    }

    // Do not allow to close a ticket if is a toner replacement ticket and no cartridges are installed.
    $installed_cartridges = PluginIserviceTicket::getTicketInstalledCartridgeIds($item);
    if (empty($installed_cartridges) && preg_match("/(replacement|inlocui|înlocui)/i", $item->input['content'])) {
        $can_close = false;
        Session::addMessageAfterRedirect("Tichetul de înlocuire cartuș nu poate fi închis fară cartuș instalat!", true, ERROR);
    }

    // Do not allow to close a ticket if it has older effective date then the last closed ticket.
    $last_closed_ticket_id = PluginIserviceTicket::getLastIdForItemWithInput($item, false);
    $last_closed_ticket    = new PluginIserviceTicket();
    if ($last_closed_ticket->getFromDB($last_closed_ticket_id) && $last_closed_ticket->customfields->fields['effective_date_field'] >= $item->input['effective_date_field']) {
        $can_close = false;
        Session::addMessageAfterRedirect("Tichetul nu poate fi închis deoarece are data efectivă mai mică decât ultimul tichet închis (<a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=$last_closed_ticket_id&mode=" . PluginIserviceTicket::MODE_CLOSE . "' target='_blank'>$last_closed_ticket_id</a>)!", true, WARNING);
    }

    if (!$can_close) {
        $item->input['status'] = $item->fields['status'] ?? Ticket::WAITING;
        $item->update_error    = 'cannot_close';
    }
}

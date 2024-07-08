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
    $menus                = RedefineMenus::redefine($menus);
    $_SESSION['glpimenu'] = $menus;

    return $menus;
}

function plugin_iservice_pre_Ticket_add(Ticket $item): void
{
    plugin_iservice_remove_new_lines_from_content($item->input);
}

function plugin_iservice_pre_PluginFieldsSuppliercustomfield_add(PluginFieldsSuppliersuppliercustomfield $item): void
{
    plugin_iservice_pre_PluginFieldsSuppliercustomfield_update($item);
}

function plugin_iservice_pre_Ticket_update(Ticket $item): void
{
    plugin_iservice_remove_new_lines_from_content($item->input);

    if (PluginIserviceTicket::isTicketClosing($item)) {
        plugin_iservice_ticket_check_if_can_close($item);
    }

    if (PluginIserviceTicket::isTicketOpening($item)) {
        plugin_iservice_ticket_reopen_newer_tickets($item);
    }
}

function plugin_iservice_pre_PluginFieldsSuppliercustomfield_update(PluginFieldsSuppliersuppliercustomfield $item): void
{
    if (empty($item->input['items_id'])) {
        return;
    }

    $suppliers = explode(',', $item->input['groupfield']);

    if (empty($item->input['groupfield']) || !in_array($item->input['items_id'], $suppliers)) {
        $suppliers = array_merge([$item->input['items_id']], $suppliers);
    }

    array_walk($suppliers, function(&$value) {
        $value = intval(trim($value, "' \t\n\r\0\x0B"));
    });

    $item->input['groupfield'] = implode(',', array_unique(array_filter($suppliers)));
}

function plugin_iservice_Ticket_update(Ticket $item): void
{
    if (PluginIserviceTicket::wasTicketClosedStatusChanging($item)) {
        PluginIserviceTicket::moveCartridges($item);
    }
}

function plugin_iservice_PluginFieldsTicketticketcustomfield_update(PluginFieldsTicketticketcustomfield $item): void
{
    PluginIserviceTicket::handleDeliveredStatusChange($item);
}

function plugin_iservice_Printer_update(Printer $item): void
{
    if (!in_array('locations_id', $item->updates)) {
        return;
    }
    $cartridge_object = new PluginIserviceCartridge();
    // Move all the installed cartridges to the new location
    foreach ($cartridge_object->find(["date_out is null AND not date_use is null AND printers_id = " . $item->getID()]) as $cartridge) {
        $cartridge_object->update(array('id' => $cartridge['id'], 'locations_id_field' => $item->fields['locations_id'] ?: '0'));
    }
}

function plugin_iservice_Infocom_update($item): void
{
    if (!in_array('suppliers_id', $item->updates) || empty($item->oldvalues['suppliers_id']) || $item->fields['itemtype'] !== 'Printer') {
        return;
    }

    $cartridge_object = new PluginIserviceCartridge();
    // Move all the installed cartridges to the new partner
    foreach ($cartridge_object->find(["NOT date_use IS null AND date_out IS null AND suppliers_id_field = {$item->oldvalues['suppliers_id']} AND printers_id = {$item->fields['items_id']}"]) as $cartridge) {
        $cartridge_object->update(array('id' => $cartridge['id'], 'suppliers_id_field' => $item->fields['suppliers_id'] ?? 0));
    }
}

function plugin_iservice_remove_new_lines_from_content(array &$input): void
{
    if (!empty($input['content'])) {
        $input['content'] = preg_replace('/\r\n/', '', $input['content']);
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

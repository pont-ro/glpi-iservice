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
    // If status has to be changed use $_SESSION['saveInput']['Ticket']['status'], as it will be used to inhibit automatic GLPI status changes.
    plugin_iservice_remove_new_lines_from_content($item->input);

    if (PluginIserviceTicket::isTicketClosing($item)) {
        plugin_iservice_ticket_check_if_can_close($item);
    }

}

function plugin_iservice_pre_PluginFieldsPrintercustomfield_add(PluginFieldsPrinterprintercustomfield $item): void
{
    plugin_iservice_pre_PluginFieldsPrintercustomfield_update($item);
}

function plugin_iservice_pre_PluginFieldsSuppliercustomfield_add(PluginFieldsSuppliersuppliercustomfield $item): void
{
    plugin_iservice_pre_PluginFieldsSuppliercustomfield_update($item);
}

function plugin_iservice_pre_PluginFieldsCartridgeitemcustomfield_add(PluginFieldsCartridgeitemcartridgeitemcustomfield $item): void
{
    plugin_iservice_pre_PluginFieldsCartridgeitemcustomfield_update($item);
}

function plugin_iservice_post_Ticket_prepareadd(Ticket $item)
{
    plugin_iservice_ticket_restore_status_from_session($item);
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

function plugin_iservice_pre_PluginFieldsPrintercustomfield_update(PluginFieldsPrinterprintercustomfield $item): void
{
    if (empty($item->input['items_id'] ?? $item->fields['items_id'] ?? 0)) {
        return;
    }

    if (!IserviceToolBox::isPrinterColorOrPlotter($item->input['items_id'] ?? $item->fields['items_id'] ?? 0)) {
        $item->input['daily_color_average_field'] = 0;
        $item->input['uc_cyan_field']             = 0;
        $item->input['uc_magenta_field']          = 0;
        $item->input['uc_yellow_field']           = 0;
    }

}

function plugin_iservice_pre_PluginFieldsSuppliercustomfield_update(PluginFieldsSuppliersuppliercustomfield $item): void
{
    if (empty($item->input['items_id'])) {
        return;
    }

    $suppliers = explode(',', $item->input['group_field'] ?? '');

    if (empty($item->input['group_field']) || !in_array($item->input['items_id'], $suppliers)) {
        $suppliers = array_merge([$item->input['items_id']], $suppliers);
    }

    array_walk(
        $suppliers, function (&$value) {
            $value = intval(trim($value, "' \t\n\r\0\x0B"));
        }
    );

    $item->input['group_field'] = implode(',', array_unique(array_filter($suppliers)));
}

function plugin_iservice_pre_PluginFieldsCartridgeitemcustomfield_update(PluginFieldsCartridgeitemcartridgeitemcustomfield $item): void
{
    if (empty($item->input['mercury_code_field'])) {
        return;
    }

    $mercuryCodes = explode(',', $item->input['compatible_mercury_codes_field']);
    if (empty($item->input['compatible_mercury_codes_field']) || !in_array($item->input['mercury_code_field'], $mercuryCodes)) {
        $mercuryCodes = array_merge([$item->input['mercury_code_field']], $mercuryCodes);
    }

    array_walk(
        $mercuryCodes, function (&$value) {
            $value = str_replace(["'", '"'], "", stripslashes($value));
        }
    );

    $mercuryCodes = array_unique(array_filter($mercuryCodes));
    array_walk(
        $mercuryCodes, function (&$value) {
            $value = addslashes("'" . trim($value, "' \t\n\r\0\x0B") . "'");
        }
    );

    $item->input['compatible_mercury_codes_field'] = implode(',', $mercuryCodes);

    $supported_types = explode(',', $item->input['supported_types_field']);
    array_walk(
        $supported_types, function (&$value) {
            $value = intval(trim($value, "' \t\n\r\0\x0B"));
        }
    );
    $item->input['supported_types_field'] = implode(',', array_unique(array_filter($supported_types)));
}

/**
 * Restore the status from $_SESSION
 * Do not allow automatic status changes coded in GLPI: change back to the originally saved status if it wasn`t deleted due to inability to close the ticket because there is an older one opened.
 * Do not allow empty status, default it to INCOMING
 *
 * @param Ticket $item
 */
function plugin_iservice_ticket_restore_status_from_session(Ticket $item): void
{
    if (!empty($_SESSION['saveInput']['Ticket']['status'])) {
        $item->input['status'] = $_SESSION['saveInput']['Ticket']['status'];
    }

    if (empty($item->input['status'])) {
        $item->input['status'] = Ticket::INCOMING;
    }
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
    // Move all the installed cartridges to the new location.
    foreach ($cartridge_object->find(["date_out is null AND not date_use is null AND printers_id = " . $item->getID()]) as $cartridge) {
        $cartridge_object->update(['id' => $cartridge['id'], 'locations_id_field' => $item->fields['locations_id'] ?: '0']);
    }
}

function plugin_iservice_Infocom_update($item): void
{
    if (!in_array('suppliers_id', $item->updates) || empty($item->oldvalues['suppliers_id']) || $item->fields['itemtype'] !== 'Printer') {
        return;
    }

    $cartridge_object = new PluginIserviceCartridge();
    // Move all the installed cartridges to the new partner.
    foreach ($cartridge_object->find(["NOT date_use IS null AND date_out IS null AND suppliers_id_field = {$item->oldvalues['suppliers_id']} AND printers_id = {$item->fields['items_id']}"]) as $cartridge) {
        $cartridge_object->update(['id' => $cartridge['id'], 'suppliers_id_field' => $item->fields['suppliers_id'] ?? 0]);
    }
}

function plugin_iservice_pre_Ticket_delete(Ticket $item): void
{
    plugin_iservice_ticket_disable_delete_if_has_consumables_or_cartridges($item);
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
        $message = _t('The following tickets have been also reopened') . ':<br><ul>';
        foreach ($reopened_tickets as $reopened_ticket_id) {
            $message .= "<li><a class='reopened-ticket' href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=$reopened_ticket_id&show_history=cartridge'>$reopened_ticket_id</a></li>";
        }

        $message .= "</ul><a href='javascript:none;' onclick='openInNewTab(\".reopened-ticket\");return false;'>" . _t('See all') . "</a>";
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
    $current_ticket    = new PluginIserviceTicket();
    $current_ticket_id = $item->getID();
    $effectiveDate     = $item->input['effective_date_field'] ?? ($current_ticket->getFromDB($item->getID()) ? $current_ticket->customfields->fields['effective_date_field'] : null);

    if (empty($effectiveDate)) {
        Session::addMessageAfterRedirect("Tichetul " . $item->getID() . " nu poate fi închis deoarece nu are data efectivă setată!", true, WARNING);
        $can_close = false;
    } else {
        if ($first_open_ticket->getFromDB($first_open_ticket_id) && $first_open_ticket->customfields->fields['effective_date_field'] <= $effectiveDate) {
            if ($first_open_ticket_id > 0 && ($item->isNewID($current_ticket_id) || $first_open_ticket_id < $current_ticket_id)) {
                $can_close = false;
                Session::addMessageAfterRedirect(
                    sprintf(
                        _t('The ticket (%s) cannot be closed because there is an open ticket with an earlier effective date (%s)!'),
                        "<a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=$current_ticket_id target='_blank'>$current_ticket_id</a>",
                        "<a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=$first_open_ticket_id target='_blank'>$first_open_ticket_id</a>"
                    ), true, WARNING
                );
            }
        }

        // Do not allow to close a ticket if is a toner replacement ticket and no cartridges are installed.
        $installed_cartridges = PluginIserviceTicket::getTicketInstalledCartridgeIds($item);
        if (empty($installed_cartridges) && preg_match("/(replacement|inlocui|înlocui)/i", $item->input['content'])) {
            $can_close = false;
            Session::addMessageAfterRedirect(sprintf(_t("The ticket (%s) cannot be closed without an installed cartridge!"), $current_ticket_id), true, ERROR);
        }

        // Do not allow to close a ticket if it has older effective date then the last closed ticket.
        $last_closed_ticket_id = PluginIserviceTicket::getLastIdForItemWithInput($item, false);
        $last_closed_ticket    = new PluginIserviceTicket();
        if ($last_closed_ticket->getFromDB($last_closed_ticket_id)) {
            if ($last_closed_ticket->customfields->fields['effective_date_field'] >= $effectiveDate) {
                $can_close = false;
                Session::addMessageAfterRedirect(
                    sprintf(
                        _t("The ticket (%s) cannot be closed because it has an effective date earlier than the last closed ticket (%s)!"),
                        "<a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=$current_ticket_id target='_blank'>$current_ticket_id</a>",
                        "<a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=$last_closed_ticket_id target='_blank'>$last_closed_ticket_id</a>"
                    ), true, WARNING
                );
            }

            $total2_black_field = $item->input['total2_black_field'] ?? ($current_ticket->getFromDB($item->getID()) ? $current_ticket->customfields->fields['total2_black_field'] : 0);
            $total2_color_field = $item->input['total2_color_field'] ?? ($current_ticket->getFromDB($item->getID()) ? $current_ticket->customfields->fields['total2_color_field'] : 0);
            if (($last_closed_ticket->customfields->fields['total2_black_field'] ?? 0) > $total2_black_field
                || ($last_closed_ticket->customfields->fields['total2_color_field'] ?? 0) > $total2_color_field
            ) {
                $can_close = false;
                Session::addMessageAfterRedirect(
                    sprintf(
                        _t('The ticket (%s) can not be closed because counters are lower than on the last closed ticket: (%s)!'),
                        "<a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=$current_ticket_id target='_blank'>$current_ticket_id</a>",
                        "<a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=$last_closed_ticket_id' target='_blank'>$last_closed_ticket_id</a>!"
                    ), true, WARNING
                );
            }
        }
    }

    if (!$can_close) {
        $item->input['status'] = $item->fields['status'] ?? Ticket::WAITING;
        $item->update_error    = 'cannot_close';
    }
}

/**
 * Do not allow the deletion of a ticket if it delivers a consumbale or installs a cartridge
 *
 * @param Ticket $item
 */
function plugin_iservice_ticket_disable_delete_if_has_consumables_or_cartridges(Ticket $item)
{
    $ticket_consumable = new PluginIserviceConsumable_Ticket();
    $ticket_cartridge  = new PluginIserviceCartridge_Ticket();
    if ($ticket_consumable->find(["tickets_id = {$item->getID()}"]) || $ticket_cartridge->find(["tickets_id = {$item->getID()}"])) {
        $item->input = null;
        Session::addMessageAfterRedirect("Nu puteți șterge un ticket daca acesta livrează un consumabil sau instalează un cartuș!", true, ERROR);
    }
}

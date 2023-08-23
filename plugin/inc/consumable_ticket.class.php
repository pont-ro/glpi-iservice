<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Consumable_Ticket Class
 *
 *  Relation between Tickets and Consumables
 * */
class PluginIserviceConsumable_Ticket extends CommonDBRelation
{

    // From CommonDBRelation.
    public static $itemtype_1               = 'Ticket';
    public static $items_id_1               = 'tickets_id';
    public static $itemtype_2               = 'PluginIserviceConsumable';
    public static $items_id_2               = 'plugin_iservice_consumables_id';
    public static $checkItem_2_Rights       = self::HAVE_VIEW_RIGHT_ON_ITEM;
    protected static $compatible_cartridges = [];

    public function getForbiddenStandardMassiveAction(): array
    {

        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }

    public function canCreateItem(): bool
    {

        $ticket = new Ticket();
        // Not item linked for closed tickets.
        if ($ticket->getFromDB($this->fields['tickets_id']) && in_array($ticket->fields['status'], $ticket->getClosedStatusArray())) {
            return false;
        }

        return parent::canCreateItem();
    }

    public function prepareInputForAdd($input): array
    {
        if ($input['locations_id'] ?? -1 < 0) {
            $input['locations_id'] = 0;
        }

        return parent::prepareInputForAdd($input);
    }

    public function getForTicket($id): array|bool
    {
        $query       = "SELECT ct.*, c.Denumire, c.cartridgeitem_name FROM " . $this->getTable() . " ct LEFT JOIN glpi_plugin_iservice_consumables c ON c.id = ct.plugin_iservice_consumables_id WHERE " . self::$items_id_1 . " = $id ORDER BY id";
        $result_data = PluginIserviceDB::getQueryResult($query);
        return empty($result_data) ? false : $result_data;
    }

    public static function showForTicket(PluginIserviceTicket $ticket, &$required_fields, $generate_form = true, $readonly = false)
    {
        global $DB, $CFG_GLPI;

        $html         = new PluginIserviceHtml();
        $instID       = $ticket->getID();
        $suppliers_id = $ticket->fields['_suppliers_id_assign'] ?? 0;
        $items_id     = $ticket->fields['items_id']['Printer'][0] ?? 0;

        $ticket_fields = $ticket->fields;
        if (!$ticket->can($instID, READ)) {
            return false;
        }

        $canedit = !$readonly && ($ticket->canEdit($instID) && isset($_SESSION["glpiactiveprofile"]) && $_SESSION["glpiactiveprofile"]["interface"] == "central");
        $rand    = mt_rand();

        $ticket->fields          = $ticket_fields;
        $ticket->consumable_data = [];

        $consumables = [];
        $used_ids    = [];

        $c_result = $DB->query(
            "SELECT 
                     ct.id IDD
                   , ct.plugin_fields_cartridgeitemtypedropdowns_id
                   , ct.locations_id
                   , ct.create_cartridge
                   , ct.amount
                   , ct.price
                   , ct.euro_price
                   , ct.new_cartridge_ids
                   , c.id
                   , c.name
                   , io.plugin_iservice_orderstatuses_id
                   , ieo.plugin_iservice_extorders_id
                 FROM glpi_plugin_iservice_consumables_tickets ct
                 JOIN glpi_plugin_iservice_consumables c ON c.id = ct.plugin_iservice_consumables_id
                 LEFT JOIN glpi_plugin_iservice_intorders io ON io.plugin_iservice_consumables_id = c.id AND io.tickets_id = $instID
                 LEFT JOIN glpi_plugin_iservice_intorders_extorders ieo ON ieo.plugin_iservice_intorders_id = io.id
                 WHERE ct.tickets_id = $instID ORDER BY ct.id"
        );
        if ($c_result) {
            while ($consumable = $DB->fetchAssoc($c_result)) {
                $consumables[$consumable['id']]             = $consumable;
                $used_ids[]                                 = $consumable['IDD'];
                $ticket->consumable_data[$consumable['id']] = $consumable;
            }
        }

        if ($canedit) {
            echo "<div class='add-consumable-div'>";
            if ($generate_form) {
                echo "<form name='ticketitem_form$rand' id='ticketitem_form$rand' method='post'
                                  action='" . Toolbox::getItemTypeFormURL(__CLASS__) . "'>";
            }

            echo "<table class='tab_cadre_fixe wide add-consumable-table no-margin full-selects'>";
            if ($generate_form) {
                echo "<tr class='tab_bg_2'><th colspan='2'>" . __('Add an item') . "</th></tr>";
            }

            echo "<tr class='tab_bg_1'><td width='81%'>";
            $compatible_consumables       = array_column(PluginIserviceCartridgeItem::getCompatiblesForTicket($ticket), 'ref');
            $consumables_selector_options = [
                'comments' => false,
                'display' => false,
                'name' => '_plugin_iservice_consumable[plugin_iservice_consumables_id]',
                'used' => array_keys($consumables),
                'specific_tags' => ['transform_function' => "PluginIserviceConsumable_Ticket::TransformDropdownValue($suppliers_id, $items_id, %s)"],
                'on_change' => '$("[name=\'add_consumable\']").click();'
            ];
            echo "<table><tr style='vertical-align:bottom;'>";
            echo "<td style='white-space:nowrap;'>";
            echo __('Amount', 'iservice') . " <input type='text' name='_plugin_iservice_consumable[amount]' class='consumables-amount' value='1'/>";
            echo "</td><td style='white-space:nowrap;'>";
            echo __('Price', 'iservice') . " <input type='text' name='_plugin_iservice_consumable[price]' class='consumables-price' value='0'/>";
            echo "</td>";
            if (count($compatible_consumables) > 0) {
                if (count(array_keys($consumables)) > 0) {
                    $compatible_consumables = array_diff($compatible_consumables, array_keys($consumables));
                }

                echo "<td style='width:50%;'>Listă completă:<br>";
                echo PluginIserviceConsumable::dropdown($consumables_selector_options);
                $consumables_selector_options['name']      = '_plugin_iservice_consumable[plugin_iservice_cartridge_consumables_id]';
                $consumables_selector_options['condition'] = ["id in ('" . implode("','", $compatible_consumables) . "')"];
                echo "</td><td style='width:50%;'>Listă cartușe compatibile:<br>", PluginIserviceConsumable::dropdown($consumables_selector_options);
            } else {
                echo "<td style='width:100%;'>";
                echo PluginIserviceConsumable::dropdown($consumables_selector_options);
            }

            echo "</td></tr></table>";
            echo "</td><td>";
            echo "<input type='submit' name='add_consumable' value=\"" . _sx('button', 'Add') . "\" class='submit' data-required='" . implode(',', array_keys(array_filter($required_fields))) . ",_export_type'>";
            echo "</td></tr>";
            echo "</table>";
            if ($generate_form) {
                Html::closeForm();
            }

            echo "</div>";
        }

        if (!($number = count($consumables))) {
            return 0;
        } else {
            $required_fields['_export_type'] = true;
        }

        echo "<table class='tab_cadre_fixe wide no-margin full-selects'>";
        echo "<tr><td width='81%'>";
        if ($canedit && $number && $generate_form) {
            Html::openMassiveActionsForm('mass' . __CLASS__ . $rand);
            $massiveactionparams = ['container' => 'mass' . __CLASS__ . $rand];
            Html::showMassiveActions($massiveactionparams);
        }

        echo "<table class='tab_cadre_fixe'>";
        $header = '<tr>';
        if ($canedit && $number) {
            $header .= "<th width='10'>"; // . Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand);
            $header .= "</th>";
        }

        $header .= "<th>" . __('Name') . "</th>";
        $header .= "<th>" . __('Cartridge') . "</th>";
        $header .= "<th>" . _n('Location', 'Locations', 1) . "</th>";
        $header .= "<th>" . __('Price', 'iservice') . "</th>";
        $header .= "<th style='white-space:nowrap;'>" . __('in €', 'iservice') . "</th>";
        $header .= "<th>" . __('Amount', 'iservice') . "</th>";
        $header .= "<th>" . _x('item', 'State') . "</th></tr>";
        echo $header;

        foreach ($consumables as $consumable) {
            echo "<tr class='tab_bg_1'>";
            if ($canedit) {
                echo "<td width='10'>";
                // Html::showMassiveActionCheckBox(__CLASS__, $consumable["IDD"]);
                echo Html::getCheckbox(
                    [
                        'name' => "_plugin_iservice_consumables_tickets[$consumable[IDD]]",
                        'zero_on_empty' => false,
                    ]
                );
                echo "</td>";
            }

            $ticket->consumable_data['installed_cartridges'] = [];
            if (!empty($consumable['new_cartridge_ids'])) {
                $cartridge_ids = str_replace('|', '', $consumable['new_cartridge_ids']);
                if (empty($ticket->consumable_data['delivery_date'])) {
                    $cartridge = new PluginIserviceCartridge();
                    foreach ($cartridge->find("id in ($cartridge_ids)") as $cartr) {
                        $ticket->consumable_data['delivery_date'] = $cartr['date_in'];
                    }
                }

                $cartridge_ticket = new PluginIserviceCartridge_Ticket();
                foreach ($cartridge_ticket->find("cartridges_id in ($cartridge_ids)") as $cartr) {
                    $ticket->consumable_data['installed_cartridges'][$cartr['cartridges_id']] = ['id' => $cartr['cartridges_id'], 'ticket_use' => $cartr['tickets_id']];
                }

                $title = str_replace(',', ', ', $cartridge_ids);
            } else {
                $title = "";
            }

            $force_cartridge_creation = null;
            $cartridge_creation_title = "Creează cartuș?";
            $would_create_cartridge   = in_array($consumable['id'], self::getCompatibleCartridges($suppliers_id, $items_id));
            if (!$would_create_cartridge) {
                $force_cartridge_creation = 0;
                $cartridge_creation_title = "Consumabilul nu este cartuș";
            } elseif ($consumable['amount'] < 0) {
                $force_cartridge_creation = 1;
                $cartridge_creation_title = "Consumabilul va ștrege " . abs($consumable['amount']) . " cartuș" . ($consumable['amount'] > 1 ? "e" : "");
            }

            echo "<td title='$title'>";
            echo "$consumable[name]" . ($would_create_cartridge ? " [*]" : "");
            $html->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, "_plugin_iservice_consumable_codes[$consumable[IDD]]", $consumable['id']);
            echo "</td>";
            echo "<td class='center'>";

            $in_cm = PluginIserviceDB::getQueryResult("SELECT cm_field FROM glpi_plugin_fields_suppliersuppliercustomfields WHERE items_id = $suppliers_id");
            if (!$in_cm[0]['cm_field']) {
                $force_cartridge_creation = 0;
                $cartridge_creation_title = "Aparatul nu este in Management cartușe";
            }

            $html->displayField(
                PluginIserviceHtml::FIELDTYPE_CHECKBOX,
                "_plugin_iservice_consumable_create_cartridges[$consumable[IDD]]",
                $force_cartridge_creation === null ? $consumable['create_cartridge'] : $force_cartridge_creation,
                $readonly || $force_cartridge_creation !== null,
                [
                    'title' => $cartridge_creation_title,
                    'onchange' => 'consumablesChanged = true;'
                ]
            );
            echo "</td>";
            echo "<td class='center'>";
            $location_condition_select = "
                SELECT distinct(p.locations_id)
                FROM glpi_infocoms ic 
                LEFT JOIN glpi_printers p ON p.id = ic.items_id AND itemtype = 'Printer'
                WHERE ic.suppliers_id = " . $ticket->getFirstAssignedPartner()->getID();
            $location_dropdown_options = [
                'class' => 'full',
                'type' => 'Location',
                'options' => [
                    'comments' => false,
                    'condition' => ["glpi_locations.id IN ($location_condition_select)"],
                    'on_change' => 'consumablesChanged = true;',
                ],
            ];
            $html->displayField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, "_plugin_iservice_consumable_locations[$consumable[IDD]]", $consumable['locations_id'], $readonly, $location_dropdown_options);
            echo "</td>";
            echo "<td class='center'>";
            $html->displayField(PluginIserviceHtml::FIELDTYPE_TEXT, "_plugin_iservice_consumable_prices[$consumable[IDD]]", $consumable['price'], $readonly, ['onchange' => 'consumablesChanged = true;', 'style' => 'width:3em;']);
            $html->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, "_plugin_iservice_consumable_orig_prices[$consumable[IDD]]", $consumable['price']);
            echo "</td>";
            echo "<td class='center'>";
            $html->displayField(PluginIserviceHtml::FIELDTYPE_CHECKBOX, "_plugin_iservice_consumable_prices_in_euro[$consumable[IDD]]", $consumable['euro_price'], $readonly, ['onchange' => 'consumablesChanged = true;', ]);
            echo "</td>";
            echo "<td class='center'>";
            $html->displayField(PluginIserviceHtml::FIELDTYPE_TEXT, "_plugin_iservice_consumable_amounts[$consumable[IDD]]", $consumable['amount'], $readonly, ['onchange' => 'consumablesChanged = true;', 'style' => 'width:3em;']);
            echo "</td>";
            if ($consumable['plugin_iservice_orderstatuses_id'] >= PluginIserviceOrderStatus::getIdReceived()) {
                $color = "_yellow";
                $title = "Recepționat";
            } elseif (!empty($consumable['plugin_iservice_extorders_id'])) {
                $color = "_blue";
                $title = "Comandă în așteptare";
            } elseif (!empty($consumable['plugin_iservice_orderstatuses_id'])) {
                $color = "_orange";
                $title = "Comandă internă";
            } else {
                $color = "_red";
                $title = "Necomandat";
            }

            echo "<td class='center'>";
            echo "<a href='view.php?view=intorders&intorders0[order_status]=1,2,3,4,5&intorders0[ticket_id]=$instID'>";
            echo "<img title='$title' src='$CFG_GLPI[root_doc]/plugins/iservice/pics/app_go$color.png' /></a></td>";
        }

        echo $header;
        echo "</table>";

        if ($canedit && $number && $generate_form) {
            $massiveactionparams['ontop'] = false;
            Html::showMassiveActions($massiveactionparams);
            Html::closeForm();
        }

        echo "</td><td>";
        if ($canedit) {
            echo "<input type='submit' name='remove_consumable' onclick='consumablesChanged=false;' value='" . __('Delete') . "' class='submit' style='margin: 2px;' data-required='" . implode(',', array_keys(array_filter($required_fields))) . "'><br>";
            echo "<input type='submit' name='update_consumable' onclick='consumablesChanged=false;' value='" . __('Update') . "' class='submit' style='margin: 2px;' data-required='" . implode(',', array_keys(array_filter($required_fields))) . "'>";
        }

        echo "</td></tr>";
        echo "</table>";

        return $number;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {

        if (!$withtemplate) {
            $nb = 0;
            switch ($item->getType()) {
            case 'Ticket' :
                if (($_SESSION["glpiactiveprofile"]["helpdesk_hardware"] != 0) && (count($_SESSION["glpiactiveprofile"]["helpdesk_item_type"]) > 0)) {
                    if ($_SESSION['glpishow_count_on_tabs']) {
                        $nb = countElementsInTable($this->getTable(), self::$items_id_1 . " = '" . $item->getID() . "'");
                    }

                    return self::createTabEntry(_n('Consumable', 'Consumables', Session::getPluralNumber()), $nb);
                }
            }
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): ?bool
    {

        switch ($item->getType()) {
        case 'Ticket' :
            // self::showForTicket($item);
            echo "Not implemented, contact hupu";
            break;
        }

        return true;
    }

    /**
     * Form for Followup on Massive action
     * */
    public static function showFormMassiveAction($ma): void
    {
        global $CFG_GLPI;

        switch ($ma->getAction()) {
        case 'add_item' :
            Dropdown::showAllItems("items_id", 0, 0, $_SESSION['glpiactive_entity'], $CFG_GLPI["ticket_types"], false, true, 'item_itemtype');
            echo "<br><input type='submit' name='add' value=\"" . _sx('button', 'Add') . "\" class='submit'>";
            break;

        case 'delete_item' :
            Dropdown::showAllItems("items_id", 0, 0, $_SESSION['glpiactive_entity'], $CFG_GLPI["ticket_types"], false, true, 'item_itemtype');
            echo "<br><input type='submit' name='delete' value=\"" . __('Delete permanently') . "\" class='submit'>";
            break;
        }
    }

    /*
     * @since version 0.85
     *
     * @see CommonDBTM::showMassiveActionsSubForm()
     * */
    public static function showMassiveActionsSubForm(MassiveAction $ma): bool
    {

        switch ($ma->getAction()) {
        case 'delete_item':
        case 'add_item' :
            static::showFormMassiveAction($ma);
            return true;
        }

        return parent::showMassiveActionsSubForm($ma);
    }

    /*
     * @since version 0.85
     *
     * @see CommonDBTM::processMassiveActionsForOneItemtype()
     * */
    public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids): void
    {

        switch ($ma->getAction()) {
        case 'add_item' :
            $input = $ma->getInput();

            $consumable_ticket = new static();
            foreach ($ids as $id) {
                if ($item->getFromDB($id) && !empty($input[self::$items_id_2])) {
                    $input[self::$items_id_1] = $id;

                    if ($consumable_ticket->can(-1, CREATE, $input)) {
                        $ok = true;
                        if (!$consumable_ticket->add($input)) {
                            $ok = false;
                        }

                        if ($ok) {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                            $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    }
                } else {
                    $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                    $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
                }
            }
            return;

        case 'delete_item' :
            $input             = $ma->getInput();
            $consumable_ticket = new static();
            foreach ($ids as $id) {
                if ($item->getFromDB($id) && !empty($input[self::$items_id_2])) {
                    $item_found = $consumable_ticket->find(self::$items_id_1 . " = $id AND " . self::$items_id_2 . " = " . $input[self::$items_id_2]);
                    if (!empty($item_found)) {
                        $item_founds_id = array_keys($item_found);
                        $input['id']    = $item_founds_id[0];

                        if ($consumable_ticket->can($input['id'], DELETE, $input)) {
                            $ok = true;
                            if (!$consumable_ticket->delete($input)) {
                                $ok = false;
                            }

                            if ($ok) {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                                $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                            }
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                            $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
                    }
                } else {
                    $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                    $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
                }
            }
            return;
        }

        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    /*
     * @since version 0.84
     *
     * @param $field
     * @param $values
     * @param $options   array
     * */
    public static function getSpecificValueToDisplay($field, $values, array $options = []): string
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        switch ($field) {
        case self::$items_id_2:
            $itemtype2 = new self::$itemtype_2();
            return Dropdown::getDropdownName($itemtype2->getTable(), $values[$field]);
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /*
     * @since version 0.84
     *
     * @param $field
     * @param $name            (default '')
     * @param $values          (default '')
     * @param $options   array
     *
     * @return string
     * */
    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = []): string
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        $options['display'] = false;
        switch ($field) {
        case self::$items_id_2:
            $options['name']  = $name;
            $options['value'] = $values[$field];
            return Dropdown::show(self::$itemtype_2, $options);
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    /*
     * Add a message on add action
     * */
    public function addMessageOnAddAction(): void
    {
        global $CFG_GLPI;

        $addMessAfterRedirect = false;
        if (isset($this->input['_add'])) {
            $addMessAfterRedirect = true;
        }

        if (isset($this->input['_no_message']) || !$this->auto_message_on_action) {
            $addMessAfterRedirect = false;
        }

        if ($addMessAfterRedirect) {
            $item = new self::$itemtype_2;
            $item->getFromDB($this->fields[self::$items_id_2]);

            $link = $item->getFormURL();
            if (!isset($link)) {
                return;
            }

            if (($name = $item->getName()) == NOT_AVAILABLE) {
                // TRANS: %1$s is the itemtype, %2$d is the id of the item.
                $item->fields['name'] = sprintf(__('Consumabil - ID %2$d'), $item->getID());
            }

            $display = (isset($this->input['_no_message_link']) ? $item->getNameID() : $item->getLink());

            // Do not display quotes.
            // TRANS : %s is the description of the added item.
            Session::addMessageAfterRedirect(sprintf(__('%1$s: %2$s'), __('Consumable successfully added'), stripslashes($display)));
        }
    }

    /**
     * Add a message on delete action
     * */
    public function addMessageOnPurgeAction(): void
    {

        if (!$this->maybeDeleted()) {
            return;
        }

        $addMessAfterRedirect = false;
        if (isset($this->input['_delete'])) {
            $addMessAfterRedirect = true;
        }

        if (isset($this->input['_no_message']) || !$this->auto_message_on_action) {
            $addMessAfterRedirect = false;
        }

        if ($addMessAfterRedirect) {
            $item = new self::$itemtype_2;
            $item->getFromDB($this->fields[self::$items_id_2]);

            $link = $item->getFormURL();
            if (!isset($link)) {
                return;
            }

            if (isset($this->input['_no_message_link'])) {
                $display = $item->getNameID();
            } else {
                $display = $item->getLink();
            }

            // TRANS : %s is the description of the updated item.
            Session::addMessageAfterRedirect(sprintf(__('%1$s: %2$s'), __('Consumable successfully deleted'), $display));
        }
    }

    public function add(array $input, $options = [], $history = true): bool|int
    {
        if (!empty($options['printer']) && $options['printer'] instanceof PluginIservicePrinter) {
            $printer = $options['printer'];
        } else {
            $printer = new PluginIservicePrinter();
            if (!empty($options['printer_id'])) {
                $printer->getFromDB($options['printer_id']);
            }
        }

        if (!isset($input['locations_id']) && isset($printer->fields['locations_id'])) {
            $input['locations_id'] = $printer->fields['locations_id'] > 0 ? $printer->fields['locations_id'] : 0;
        }

        $cartridge_management_enabled = $printer->hasCartridgeManagement();

        /*
        *
        * @var PluginIservicePartner $assigned_supplier
        */
        $assigned_supplier             = PluginIserviceTicket::get($input['tickets_id'])->getFirstAssignedPartner();
        $cartridge_management_enabled |= $assigned_supplier->hasCartridgeManagement();

        $success = true;
        $amount  = empty($input['amount']) ? 0 : $input['amount'];
        if ($amount < 0 && $cartridge_management_enabled) {
            $location_condition         = isset($input['locations_id']) ? "locations_id_field = $input[locations_id]" : "(locations_id_field < 1 OR locations_id_field is NULL)";
            $query                      = "
                SELECT c.* 
                FROM glpi_plugin_iservice_cartridges c
                JOIN glpi_cartridgeitems ci on ci.id = c.cartridgeitems_id
                WHERE ci.ref = '{$input['plugin_iservice_consumables_id']}'
                  AND (COALESCE(c.printers_id, 0) = 0 OR c.printers_id = -1) AND c.date_out IS NULL
                  AND FIND_IN_SET (c.suppliers_id_field, (SELECT group_field FROM glpi_plugin_fields_suppliersuppliercustomfields WHERE items_id = {$assigned_supplier->getID()}))";
            $query_with_location        = "$query AND $location_condition";
            $cartridges_to_delete       = PluginIserviceDB::getQueryResult("$query_with_location ORDER BY c.id LIMIT " . -$amount);
            $cartridges_to_delete_count = count($cartridges_to_delete);
            if ($cartridges_to_delete_count == 0) {
                $cartridges_to_delete             = PluginIserviceDB::getQueryResult($query);
                $cartridges_to_delete_by_location = [];
                foreach ($cartridges_to_delete as $cartridge_to_delete) {
                    $cartridges_to_delete_by_location[$cartridge_to_delete['locations_id_field']][] = $cartridge_to_delete;
                }

                foreach ($cartridges_to_delete_by_location as $location_id => $cartridges_to_delete_by_location_group) {
                    if (count($cartridges_to_delete_by_location_group) < abs($amount)) {
                        continue;
                    }

                    $cartridges_to_delete  = array_slice($cartridges_to_delete_by_location_group, 0, abs($amount));
                    $input['locations_id'] = $location_id;
                    break;
                }

                if (!isset($input['locations_id'])) {
                    Session::addMessageAfterRedirect("Numărul cartușelor neinstalate la fiecare locație a partenerului este mai mică decât " . abs($amount), false, ERROR);
                    $success = false;
                }
            } elseif (abs($amount) != $cartridges_to_delete_count) {
                if (isset($input['locations_id'])) {
                    Session::addMessageAfterRedirect("Numărul cartușelor neinstalate la partener în locația imprimantei selectate este $cartridges_to_delete_count. Pentru a selecta o altă locație, introduceți această cantitate și selectați locația ulterior sau selectați o altă imprimantă!", false, ERROR);
                } else {
                    Session::addMessageAfterRedirect("Numărul cartușelor neinstalate la partener fără locație este $cartridges_to_delete_count. Pentru a selecta o locație, introduceți această cantitate și selectați locația ulterior!", false, ERROR);
                }

                $success = false;
            }

            $input['new_cartridge_ids'] = '|' . implode('|,|', array_column($cartridges_to_delete, 'id')) . '|';
        } elseif ($amount > 0 && empty($input['new_cartridge_ids'])) {
            $input['new_cartridge_ids'] = 'NULL';
        }

        if (isset($input['locations_id']) && $input['locations_id'] < 0) {
            $input['locations_id'] = 0;
        }

        if (!$success) {
            return false;
        }

        return parent::add($input, $options, $history);
    }

    public function update(array $input, $history = 1, $options = []): bool
    {
        $consumable_ticket = new PluginIserviceConsumable_Ticket();
        $consumable_ticket->getFromDB($input['id']);

        /*
        *
        * @var PluginIservicePartner $assigned_supplier
        */
        $assigned_supplier = PluginIserviceTicket::get($consumable_ticket->fields['tickets_id'])->getFirstAssignedPartner();
        $amount            = empty($input['amount']) ? 0 : $input['amount'];
        $success           = true;
        if ($amount < 0 && $assigned_supplier->hasCartridgeManagement()) {
            $location_condition   = empty($input['locations_id']) ? "(locations_id_field < 1 OR locations_id_field is NULL)" : "locations_id_field = $input[locations_id]";
            $query                = "
                SELECT c.id 
                FROM glpi_plugin_iservice_cartridges c
                JOIN glpi_cartridgeitems ci on ci.id = c.cartridgeitems_id
                WHERE ci.ref = '{$consumable_ticket->fields['plugin_iservice_consumables_id']}'
                  AND printers_id = 0
                  AND $location_condition
                  AND FIND_IN_SET (suppliers_id_field, (SELECT group_field FROM glpi_plugin_fields_suppliersuppliercustomfields WHERE items_id = {$assigned_supplier->getID()}))
                  AND date_out is null
                ORDER BY c.date_in
                LIMIT " . -$amount;
            $cartridges_to_delete = PluginIserviceDB::getQueryResult($query);
            if (abs($amount) != count($cartridges_to_delete)) {
                Session::addMessageAfterRedirect("Numărul cartușelor neinstalate la partener la locația selectată este mai mică decât " . (-$amount), false, ERROR);
                $success = false;
            }

            $input['new_cartridge_ids'] = '|' . implode('|,|', array_column($cartridges_to_delete, 'id')) . '|';
        } elseif ($amount > 0 && empty($input['new_cartridge_ids'])) {
            $input['new_cartridge_ids'] = 'NULL';
        }

        unset($input['tickets_id']);
        return $success && parent::update($input, $history, $options);
    }

    public static function TransformDropdownValue($suppliers_id, $items_id, $dropdown_data): string
    {
        if (in_array($dropdown_data['id'], self::getCompatibleCartridges($suppliers_id, $items_id))) {
            return $dropdown_data['name'] . " [*]";
        } else {
            return $dropdown_data['name'];
        }
    }

    public static function getCompatibleCartridges($suppliers_id, $items_id): array
    {
        if (!isset(self::$compatible_cartridges["$suppliers_id - $items_id"])) {
            $ticket                                   = new PluginIserviceTicket();
            $ticket->fields['_suppliers_id_assign']   = $suppliers_id;
            $ticket->fields['items_id']['Printer'][0] = $items_id;
            self::$compatible_cartridges["$suppliers_id - $items_id"] = array_column(PluginIserviceCartridgeItem::getCompatiblesForTicket($ticket), 'ref', 'id');
        }

        return self::$compatible_cartridges["$suppliers_id - $items_id"];
    }

    public static function updateNewCartridgeIdsFields(): void
    {
        $consumable_tickets = new self();
        foreach ($consumable_tickets->find("not new_cartridge_ids like '|%|' and new_cartridge_ids != '' and not new_cartridge_ids is null") as $consumable_ticket) {
            $new_cartridge_ids = explode(',', $consumable_ticket['new_cartridge_ids']);
            $consumable_tickets->update(
                [
                    'id' => $consumable_ticket['id'],
                    'new_cartridge_ids' => '|' . implode('|,|', $new_cartridge_ids) . '|'
                ]
            );
        }
    }

}

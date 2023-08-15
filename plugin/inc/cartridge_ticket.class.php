<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

/**
 * Cartridge_Ticket Class
 *
 *  Relation between Tickets and Cartridges
 * */
class PluginIserviceCartridge_Ticket extends CommonDBRelation
{

    // From CommonDBRelation.
    public static $itemtype_1         = 'Ticket';
    public static $items_id_1         = 'tickets_id';
    public static $itemtype_2         = 'Cartridge';
    public static $items_id_2         = 'cartridges_id';
    public static $checkItem_2_Rights = self::HAVE_VIEW_RIGHT_ON_ITEM;

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
        if (isset($input['locations_id']) && $input['locations_id'] < 0) {
            $input['locations_id'] = 0;
        }

        return parent::prepareInputForAdd($input);
    }

    public function getForTicket($id): array|bool
    {
        $query       = "SELECT ct.*, ci.name FROM " . $this->getTable() . " ct LEFT JOIN glpi_cartridges c ON c.id = ct.cartridges_id LEFT JOIN glpi_cartridgeitems ci ON ci.id = c.cartridgeitems_id WHERE " . self::$items_id_1 . " = $id ORDER BY id";
        $result_data = PluginIserviceDB::getQueryResult($query);
        return empty($result_data) ? false : $result_data;
    }

    public static function showForTicket(Ticket $ticket, &$required_fields, $generate_form = true, $readonly = false): array|bool
    {
        global $DB;

        $id = $ticket->getID();

        $ticket_fields = $ticket->fields;
        if (!$ticket->can($id, READ)) {
            return false;
        }

        $canedit = !$readonly && $ticket->canEdit($id);
        $rand    = mt_rand();

        $ticket->fields = $ticket_fields;

        $cartridges = [];
        $used_ids   = [];

        $c_result = $DB->query(
            "SELECT ct.id IDD, ci.id cid, ct.locations_id, c.id, ci.name, ci.compatible_mercury_codes_field
                         FROM glpi_plugin_iservice_cartridges_tickets ct
                         LEFT JOIN glpi_cartridges c ON c.id = ct.cartridges_id
                         LEFT JOIN glpi_plugin_iservice_cartridge_items ci ON ci.id = c.cartridgeitems_id
                         WHERE ct.tickets_id = $id ORDER BY ct.id"
        );
        if ($c_result) {
            while ($cartridge = $DB->fetchAssoc($c_result)) {
                $cartridges[$cartridge['id']] = $cartridge;
                $used_ids[$cartridge['cid']]  = $cartridge['cid'];
            }
        }

        if ($canedit) {
            echo "<div class='add-cartridge-div'>";
            if ($generate_form) {
                echo "<form name='ticketitem_form$rand' id='ticketitem_form$rand' method='post' action='" . Toolbox::getItemTypeFormURL(__CLASS__) . "'>";
            }

            echo "<table class='tab_cadre_fixe add-cartridge-table full-selects'>";
            if ($generate_form) {
                echo "<tr class='tab_bg_2'><th colspan='2'>" . __('Add an item') . "</th></tr>";
            }

            echo "<tr class='tab_bg_1'><td width='81%'>";
            echo "<table><tr><td style='width:100%;'>";
            $cartridges_selector_options = [
                'comments' => false,
                // 'condition' => ['Stoc != 0'],
                // 'display' => false,
                'name' => '_plugin_iservice_cartridge[cartridgeitems_id]',
                'used' => $used_ids,
                'on_change' => '$("[name=\'add_cartridge\']").click();'
            ];
            if (false !== ($has_cartridge = PluginIserviceCartridgeItem::dropdownForTicket($ticket, $cartridges_selector_options))) {
                if ($has_cartridge > 1) {
                    echo '<script>
                        setTimeout(function() {                    
                            addRecurrentCheck(function() {
                                if ($("[name=\\"_plugin_iservice_cartridge[cartridgeitems_id]\"]").val() != 0) {
                                    $("[name=\\"add_cartridge\\"]").click();$("#page").hide();
                                    return true;
                                }
                                return false;
                            });}, 1000);
                      </script>';
                }

                echo "</td><td style='white-space:nowrap;'>";
                echo __('Location') . " <span class='cartridges-location'>";
                echo Location::dropdown(['display' => false, 'comments' => false, 'name' => '_plugin_iservice_cartridge[locations_id]']);
                echo "</span>";
                echo "</td><td style='white-space:nowrap;'>";
                echo __('Amount', 'iservice') . " <input type='text' name='_plugin_iservice_cartridge[amount]' class='cartridges-amount' value='1'/>";
                echo "</td></tr></table>";
                echo "</td><td>";
                echo "<input type='submit' name='add_cartridge' value=\"" . _sx('button', 'Add') . "\" class='submit' data-required='" . implode(',', array_keys(array_filter($required_fields))) . "'>";
            } else {
                echo "</td></tr></table>";
            }

            echo "</td></tr>";
            echo "</table>";
            if ($generate_form) {
                Html::closeForm();
            }

            echo "</div>";
        }

        if (!($number = count($cartridges))) {
            return $cartridges;
        }

        echo "<table class='tab_cadre_fixe full-selects'>";
        echo "<tr><td width='81%'>";
        if ($canedit && $number && $generate_form) {
            Html::openMassiveActionsForm('mass' . __CLASS__ . $rand);
            $massiveactionparams = ['container' => 'mass' . __CLASS__ . $rand];
            Html::showMassiveActions($massiveactionparams);
        }

        echo "<table class='tab_cadre_fixe' width='81%'>";
        $header = '<tr>';
        if ($canedit && $number) {
            $header .= "<th width='10'>"; // . Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand);
            $header .= "</th>";
        }

        $header .= "<th>" . __('Name') . "</th>";
        $header .= "<th>" . __('Location') . "</th>";
        $header .= "</tr>";
        echo $header;

        foreach ($cartridges as $cartridge) {
            echo "<tr class='tab_bg_1'>";
            if ($canedit) {
                echo "<td width='10'>";
                // Html::showMassiveActionCheckBox(__CLASS__, $cartridge["IDD"]).
                echo Html::getCheckbox(
                    [
                        'name' => "_plugin_iservice_cartridges_tickets[$cartridge[IDD]]",
                        'zero_on_empty' => false,
                    ]
                );
                echo "</td>";
            }

            echo "<td>$cartridge[name]</td>";
            echo "<td>" . Dropdown::getDropdownName('glpi_locations', $cartridge['locations_id']) . "</td>";
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
            echo "<input type='submit' name='remove_cartridge' value='" . __('Delete') . "' class='submit' style='margin: 2px;' data-required='" . implode(',', array_keys(array_filter($required_fields))) . "'><br>";
        }

        echo "</td></tr>";
        echo "</table>";

        return $cartridges;
    }

    public static function showChangeableForTicket(PluginIserviceTicket $ticket, &$required_fields, $generate_form = true, $readonly = false): int|bool
    {
        global $DB;

        $id = $ticket->getID();

        $ticket_fields = $ticket->fields;
        if (!$ticket->can($id, READ)) {
            return false;
        }

        $canedit = !$readonly && $ticket->canEdit($id);
        $rand    = mt_rand();

        $ticket->fields = $ticket_fields;

        $cartridges = [];
        $used_ids   = [];
        $used       = [];

        $c_result = $DB->query(
            "SELECT 
                      ct.id IDD
                    , ct.plugin_fields_cartridgeitemtypedropdowns_id selected_type_id
                    , ct.cartridges_id_emptied
                    , c.id
                    , ci.id cid
                    , ci.name
                    , ci.mercury_code_field mercurycode
                    , ci.compatible_mercury_codes_field mercurycodes
                    , ci.supported_types_field supportedtypes
                    , l.name location_name
                    , l.completename location_completename
                    , p.id pid
                    , p.name printer_name
                    , c.date_use
                    , c.date_out
                 FROM glpi_plugin_iservice_cartridges_tickets ct
                 INNER JOIN glpi_plugin_iservice_cartridges c ON c.id = ct.cartridges_id
                 INNER JOIN glpi_plugin_iservice_cartridge_items ci ON ci.id = c.cartridgeitems_id
                 LEFT JOIN glpi_locations l ON l.id = c.locations_id_field
                 LEFT JOIN glpi_printers p ON p.id = c.printers_id
                 WHERE ct.tickets_id = $id ORDER BY ct.id"
        );
        if ($c_result) {
            while ($cartridge = $DB->fetchAssoc($c_result)) {
                $cartridges[$cartridge['id']]               = $cartridge;
                $used_ids[$cartridge['cid']]                = $cartridge['cid'];
                $used[$cartridge['cid']]['last']            = $cartridge['id'];
                $used[$cartridge['cid']]['mercurycodes']    = $cartridge['mercurycodes'];
                $used[$cartridge['cid']]['supported_types'] = $cartridge['supportedtypes'];
                if (!empty($cartridge['selected_type_id'])) {
                    $used[$cartridge['cid']]['types'][] = $cartridge['selected_type_id'];
                }
            }

            foreach ($used as $cid => $used_data) {
                if ($used_data['supported_types'] !== implode(',', empty($used_data['types']) ? [] : $used_data['types'])) {
                    unset($used_ids[$cid]);
                }
            }
        }

        if ($canedit) {
            // echo "<span class='add-cartridge-div'>";
            if ($generate_form) {
                echo "<form name='ticketitem_form$rand' id='ticketitem_form$rand' method='post' action='" . Toolbox::getItemTypeFormURL(__CLASS__) . "'>";
            }

            echo "<div id='add-cartridge-div'>";
            echo "<table class='tab_cadre_fixe add-cartridge-table no-margin wide full-selects'>";
            if ($generate_form) {
                echo "<tr class='tab_bg_2'><th colspan='2'>" . __('Add an item') . "</th></tr>";
            }

            echo "<tr class='tab_bg_1'><td style='width:81%;'>";
            $cartridges_selector_options = [
                'comments' => false,
                // 'condition' => ['Stoc != 0'],
                // 'display' => false,
                'name' => '_plugin_iservice_cartridge[cartridgeitems_id]',
                'used' => $used_ids,
                'used_data' => $used,
            ];
            if (false !== ($has_changeable = PluginIserviceCartridgeItem::dropdownChangeableForTicket($ticket, $cartridges_selector_options))) {
                if ($has_changeable > 1) {
                    echo '<script>
                            setTimeout(function() {                    
                                addRecurrentCheck(function() {
                                    if ($("[name=\\"_plugin_iservice_cartridge[cartridgeitems_id]\"]").val() != 0) {
                                        $("[name=\\"add_cartridge\\"]").click();$("#page").hide();
                                        return true;
                                    }
                                    return false;
                                });}, 1000);
                          </script>';
                }

                echo "</td><td>";
                $name = empty($used_ids) ? __('Select', 'iservice') : _sx('button', 'Add');
                echo "<input type='submit' name='add_cartridge' value='$name' class='submit' data-required='" . implode(',', array_keys(array_filter($required_fields))) . "'>";
            }

            echo "</td></tr>";
            echo "</table>";
            echo "</div>";
            if ($generate_form) {
                Html::closeForm();
            }

            // echo "</span>";
        }

        if (!($number = count($cartridges))) {
            return empty($has_changeable) ? 0 : -1;
        }

        echo "<table class='tab_cadre_fixe no-margin wide full-selects'>";
        echo "<tr><td width='81%'>";
        if ($canedit && $number && $generate_form) {
            Html::openMassiveActionsForm('mass' . __CLASS__ . $rand);
            $massiveactionparams = ['container' => 'mass' . __CLASS__ . $rand];
            Html::showMassiveActions($massiveactionparams);
        }

        echo "<table class='tab_cadre_fixe no-margin wide'>";
        $header = '<tr>';
        if ($canedit && $number) {
            $header .= "<th width='10'>"; // . Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand);
            $header .= "</th>";
        }

        $header .= "<th>" . __('Name') . "</th>";
        $header .= "<th>" . __('Type') . "</th>";
        $header .= "<th>" . __('Empties', 'iservice') . "</th>";
        $header .= "</tr>";
        echo $header;

        foreach ($cartridges as $cartridge) {
            echo "<tr class='tab_bg_1'>";
            if ($canedit) {
                echo "<td>";
                // Html::showMassiveActionCheckBox(__CLASS__, $cartridge["IDD"]);
                echo Html::getCheckbox(
                    [
                        'name' => "_plugin_iservice_cartridges_tickets[$cartridge[IDD]]",
                        'zero_on_empty' => false,
                    ]
                );
                echo "</td>";
            }

            echo "<td>";
            echo "$cartridge[id] - $cartridge[name] ($cartridge[location_completename])";

            if (!empty($cartridge['date_use'])) {
                echo " instalat $cartridge[date_use]";
            }

            if (!empty($cartridge['date_out'])) {
                echo " golit $cartridge[date_use]";
            }

            echo "<input type='hidden' name='_plugin_iservice_cartridge_mercurycodes[$cartridge[IDD]]' value='$cartridge[mercurycode]' />";
            echo "</td>";
            echo "<td style='text-align: center;'>";
            $supported_types = explode(',', $cartridge['supportedtypes']);
            if (empty($used[$cartridge['cid']]['types'])) {
                $used[$cartridge['cid']]['types'] = [];
            }

            foreach ($used[$cartridge['cid']]['types'] as $used_type_id) {
                if ($used_type_id != $cartridge['selected_type_id'] && false !== ($unset_index = array_search($used_type_id, $supported_types))) {
                    unset($supported_types[$unset_index]);
                }
            }

            if (empty($cartridge['selected_type_id'])) {
                $cartridge['selected_type_id'] = reset($supported_types);
            }

            if (count($supported_types) > 1 && (empty($used[$cartridge['cid']]['last']) || $cartridge['id'] === $used[$cartridge['cid']]['last'])) {
                PluginFieldsTypefieldDropdown::dropdown(
                    [
                        'comments' => false,
                        'name' => "_plugin_iservice_cartridge_type_ids[$cartridge[IDD]]",
                        'value' => $cartridge['selected_type_id'],
                        'condition' => ['id in (' . implode(',', $supported_types) . ')'],
                        'on_change' => '$("[name=update_cartridge]").click();',
                    ]
                );
            } else {
                echo "<input name='_plugin_iservice_cartridge_type_ids[$cartridge[IDD]]' type='hidden' value='$cartridge[selected_type_id]' />";
                $type_dropdown = new PluginFieldsTypefieldDropdown();
                $type_dropdown->getFromDB($cartridge['selected_type_id']);
                echo "<input type='text' readonly='readonly' value='{$type_dropdown->fields['name']}' style='width:6em;'/>";
            }

            echo "</td>";
            echo "<td>";
            PluginIserviceCartridge::dropdownEmptyablesByCartridge(
                [
                    'mercury_code_field' => $cartridge['mercurycode'],
                    'plugin_fields_cartridgeitemtypedropdowns_id' => $cartridge['selected_type_id'],
                    'printers_id' => $cartridge['pid'],
                ], [
                    'comments' => false,
                    'name' => "_plugin_iservice_emptied_cartridge_ids[$cartridge[IDD]]",
                    'value' => $cartridge['cartridges_id_emptied'],
                    'readonly' => $readonly,
                    'on_change' => 'cartridgesChanged = true;',
                ]
            );
            echo "</td>";
            echo "</tr>";
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
            echo "<input type='submit' name='remove_cartridge' onclick='cartridgesChanged=false;' value='" . __('Delete') . "' class='submit' style='margin: 2px;' data-required='" . implode(',', array_keys(array_filter($required_fields))) . "'><br>";
            echo "<input type='submit' name='update_cartridge' onclick='cartridgesChanged=false;' value='" . __('Update') . "' class='submit' style='margin: 2px;' data-required='" . implode(',', array_keys(array_filter($required_fields))) . "'>";
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

                    return self::createTabEntry(_n('Cartridge', 'Cartridges', Session::getPluralNumber()), $nb);
                }
            }
        }

        return '';
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
        case 'add_item' :
            static::showFormMassiveAction($ma);
            return true;

        case 'delete_item' :
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

            $cartridge_ticket = new static();
            foreach ($ids as $id) {
                if ($item->getFromDB($id) && !empty($input[self::$items_id_2])) {
                    $input[self::$items_id_1] = $id;

                    if ($cartridge_ticket->can(-1, CREATE, $input)) {
                        $ok = true;
                        if (!$cartridge_ticket->add($input)) {
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
            $input            = $ma->getInput();
            $cartridge_ticket = new static();
            foreach ($ids as $id) {
                if ($item->getFromDB($id) && !empty($input[self::$items_id_2])) {
                    $item_found = $cartridge_ticket->find(self::$items_id_1 . " = $id AND " . self::$items_id_2 . " = " . $input[self::$items_id_2]);
                    if (!empty($item_found)) {
                        $item_founds_id = array_keys($item_found);
                        $input['id']    = $item_founds_id[0];

                        if ($cartridge_ticket->can($input['id'], DELETE, $input)) {
                            $ok = true;
                            if (!$cartridge_ticket->delete($input)) {
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

    /**
     * Add a message on add action
     * */
    public function addMessageOnAddAction(): void
    {
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
                $item->fields['name'] = sprintf(__('Cartridge - ID %2$d'), $item->getID());
            }

            $display = (isset($this->input['_no_message_link']) ? $item->getNameID() : $item->getLink());

            // Do not display quotes
            // TRANS : %s is the description of the added item.
            Session::addMessageAfterRedirect(sprintf(__('%1$s: %2$s'), __('Cartridge successfully added'), stripslashes($display)));
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

    public static function getForTicketId($ticket_id, $limit = null): array
    {
        $cartridge_ticket = new self();
        return $cartridge_ticket->find("tickets_id = $ticket_id", [], $limit);
    }

    static function install($ticket_id, $cartridge_id, $printer_id, $supplier_id, $location_id, $total2_black, $total2_color, $install_time, $installed_cartridges = null)
    {
        if (true) {
            echo "The use of this funciton is deprecated.";
            die;
        }

        $cartridge                  = new PluginIserviceCartridge();
        $cartridgeitem_custom_field = new PluginFieldsCartridgeitemcartridgeitemcustomfield();
        if ($installed_cartridges === null) {
            $installed_cartridges = PluginIservicePrinter::getInstalledCartridges($printer_id);
        }

        if (!$cartridge->getFromDB($cartridge_id) || !$cartridgeitem_custom_field->getFromDBByItemsId($cartridge->fields['cartridgeitems_id'])) {
            return "Could not find cartridge with id $cartridge_id to install it.";
        }

        if (false !== ($index = CartridgeItem::getSameCartridgeIndex($installed_cartridges, $cartridgeitem_custom_field->fields['mercury_code_field'], $cartridge->fields['plugin_fields_cartridgeitemtypedropdowns_id']))) {
            if ($installed_cartridges[$index]['type_id'] != $cartridge->fields['plugin_fields_cartridgeitemtypedropdowns_id']) {
                return "Type of cartridge to install ({$cartridge->fields['plugin_fields_cartridgeitemtypedropdowns_id']}) differs from type of installed cartridge ({$installed_cartridges[$index]['type_id']}) for the same mercury code ({$installed_cartridges[$index]['mercury_code']})";
            }

            $old_cartridge                  = new PluginIserviceCartridge();
            $old_cartridgeitem_customfields = new PluginFieldsCartridgeitemcartridgeitemcustomfield();
            if (!$old_cartridge->getFromDB($installed_cartridges[$index]['id'] || PluginIserviceDB::populateByItemsId($old_cartridgeitem_customfields, $cartridge->fields['cartridgeitems_id']))) {
                return "Could not find cartridge with id {$installed_cartridges[$index]['id']} to uninstall it.";
            }

            $printed_pages       = $old_cartridge->fields['printed_pages_field'] + $total2_black - $old_cartridge->fields['pages_use_field'];
            $printed_pages_color = $old_cartridge->fields['printed_pages_color_field'] + $total2_color - $old_cartridge->fields['pages_color_use_field'];
            if (in_array($old_cartridge->fields['plugin_fields_cartridgeitemtypedropdowns_id'], [2, 3, 4])) {
                $printed_pages_to_compare = $printed_pages_color;
            } else {
                $printed_pages_to_compare = $printed_pages + $printed_pages_color;
            }

            $ignore_in_calculations = ($printed_pages_to_compare > $old_cartridgeitem_customfields->fields['atc_field'] * 1.6) || ($printed_pages_to_compare < $old_cartridgeitem_customfields->fields['atc_field'] * 0.4);
            if (!$old_cartridge->update(
                [
                    '_no_message' => true,
                    $old_cartridge->getIndexName() => $installed_cartridges[$index]['id'],
                    'date_out' => $install_time,
                    'tickets_id_out_field' => $ticket_id,
                    'pages' => $total2_black,
                    'pages_color_field' => $total2_color,
                    'printed_pages_field' => $printed_pages,
                    'printed_pages_color_field' => $printed_pages_color,
                    'ignore_in_calculations' => $ignore_in_calculations ? 1 : 0
                ]
            )
            ) {
                return "Could not update old cartridge with id {$installed_cartridges[$index]['id']}";
            }

            if (!empty($GLOBALS['ECHO_CARTRIDGE_INSTALL_INFO'])) {
                echo " Changed cartridge {$installed_cartridges[$index]['id']} with Mercury Code {$installed_cartridges[$index]['mercury_code']}. Total2_black: $total2_black, total2_color: $total2_color. Printed pages: {$old_cartridge->fields['printed_pages_field']}, printed color pages: {$old_cartridge->fields['printed_pages_color_field']}";
            }

            $uninstalled_multiplier = 1;
        } else {
            $uninstalled_multiplier = -1;
        }

        if (!$cartridge->update(
            [
                $cartridge->getIndexName() => $cartridge->getID(),
                '_no_message' => true,
                'printers_id' => $printer_id,
                'suppliers_id_field' => $supplier_id,
                'locations_id_field' => empty($location_id) ? '0' : $location_id,
                'date_use' => $install_time,
                'tickets_id_use_field' => $ticket_id,
                'date_out' => 'NULL',
                'pages_use_field' => $total2_black,
                'pages_color_use_field' => $total2_color,
            ]
        )
        ) {
            return "Could not update cartridge with id $cartridge_id";
        }

        return $uninstalled_multiplier * $cartridge->getID();
    }

    static function uninstall($ticket_id, $cartridge_id, $printer_id, $supplier_id, $location_id, $total2_black, $total2_color, $install_time, $installed_cartridges = null)
    {
        if (true) {
            echo "The use of this funciton is deprecated.";
            die;
        }

        $cartridge                  = new PluginIserviceCartridge();
        $cartridgeitem_custom_field = new PluginFieldsCartridgeitemcartridgecustomfield();
        if (!$cartridge->getFromDB($cartridge_id) || !$cartridgeitem_custom_field->getFromDBByItemsId($cartridge->fields['cartridgeitems_id'])) {
            return "Could not find cartridge with id $cartridge_id to uninstall it.";
        }

        $old_cartridge = new PluginIserviceCartridge();
        if (PluginIserviceDB::populateByQuery(
            $old_cartridge,
            "join glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cfci on cfci.items_id = `glpi_cartridges`.cartridgeitems_id and cfci.itemtype = 'CartridgeItem'
            join glpi_plugin_fields_cartridgecartridgecustomfields cfc on cfc.items_id = id and cfc.itemtype = 'Cartridge'
            where cfc.tickets_id_out_field = $ticket_id and cfci.mercury_code_field in ({$cartridgeitem_custom_field->fields['compatible_mercury_codes_field']}) and `glpi_cartridges`.plugin_fields_cartridgeitemtypedropdowns_id = {$cartridge->fields['plugin_fields_cartridgeitemtypedropdowns_id']} limit 1"
        )
        ) {
            $old_cartridge->update(
                [
                    '_no_message' => true,
                    $old_cartridge->getIndexName() => $old_cartridge->getID(),
                    'date_out' => 'NULL',
                    'tickets_id_out_field' => 'NULL',
                    'pages' => '0',
                    'pages_color_field' => '0',
                    'printed_pages_field' => $old_cartridge->fields['printed_pages_field'] + $old_cartridge->fields['pages_use_field'] - $cartridge->fields['pages_use_field'],
                    'printed_pages_color_field' => $old_cartridge->fields['printed_pages_color_field'] + $old_cartridge->fields['pages_use_color'] - $cartridge->fields['pages_use_color'],
                    'ignore_in_calculations' => 0,
                ]
            );
            $installed_multiplier = 1;
        } else {
            $installed_multiplier = -1;
        }

        if (!$cartridge->update(
            [
                $cartridge->getIndexName() => $cartridge->getID(),
                '_no_message' => true,
                'suppliers_id_field' => $supplier_id,
                'date_use' => 'NULL',
                'tickets_id_use_field' => 'NULL',
                'date_out' => 'NULL',
                'pages_use_field' => 0,
                'pages_color_use_field' => 0,
            ]
        )
        ) {
            return "Could not update cartridge with id $cartridge_id";
        }

        return $installed_multiplier * $cartridge->getID();
    }

    public static function installWithType($ticket_id, $cartridge_id, $type_id, $emptied_cartridge_id, $printer_id, $supplier_id, $location_id, $total2_black, $total2_color, $install_time): string|int
    {
        $cartridge                  = new PluginIserviceCartridge();
        $cartridgeitem_custom_field = new PluginFieldsCartridgeitemcartridgeitemcustomfield();
        if (!$cartridge->getFromDB($cartridge_id) || !PluginIserviceDB::populateByItemsId($cartridgeitem_custom_field, $cartridge->fields['cartridgeitems_id'])) {
            return "Could not find cartridge with id $cartridge_id to install it.";
        }

        if (!empty($emptied_cartridge_id)) {
            $old_cartridge = new PluginIserviceCartridge();
            if (!$old_cartridge->getFromDB($emptied_cartridge_id)) {
                return "Could not find cartridge with id $emptied_cartridge_id to uninstall it.";
            }

            if (!$old_cartridge->update(
                [
                    '_no_message' => true,
                    $old_cartridge->getIndexName() => $emptied_cartridge_id,
                    'date_out' => $install_time,
                    'tickets_id_out_field' => $ticket_id,
                    'pages' => $total2_black,
                    'pages_color_field' => $total2_color,
                    'printed_pages_field' => $old_cartridge->fields['printed_pages_field'] + $total2_black - $old_cartridge->fields['pages_use_field'],
                    'printed_pages_color_field' => $old_cartridge->fields['printed_pages_color_field'] + $total2_color - $old_cartridge->fields['pages_color_use_field'],
                ]
            )
            ) {
                return "Could not update old cartridge with id $emptied_cartridge_id";
            }

            if (!empty($GLOBALS['ECHO_CARTRIDGE_INSTALL_INFO'])) {
                echo " Changed cartridge $emptied_cartridge_id. Total2_black: $total2_black, total2_color: $total2_color. Printed pages: {$old_cartridge->fields['printed_pages_field']}, printed color pages: {$old_cartridge->fields['printed_pages_color_field']}";
            }

            $uninstalled_multiplier = 1;
        } else {
            $uninstalled_multiplier = -1;
        }

        if (!$cartridge->update(
            [
                $cartridge->getIndexName() => $cartridge->getID(),
                '_no_message' => true,
                'plugin_fields_cartridgeitemtypedropdowns_id' => $type_id,
                'printers_id' => $printer_id,
                'suppliers_id_field' => $supplier_id,
                'locations_id_field' => empty($location_id) ? '0' : $location_id,
                'date_use' => $install_time,
                'tickets_id_use_field' => $ticket_id,
                'date_out' => 'NULL',
                'pages_use_field' => $total2_black,
                'pages_color_use_field' => $total2_color,
            ]
        )
        ) {
            return "Could not update cartridge with id $cartridge_id";
        }

        return $uninstalled_multiplier * $cartridge->getID();
    }

    public static function uninstallWithType($ticket_id, $cartridge_id, $type_id, $emptied_cartridge_id, $printer_id, $supplier_id, $location_id, $total2_black, $total2_color, $install_time): string|int
    {
        $cartridge                  = new PluginIserviceCartridge();
        $cartridgeitem_custom_field = new PluginFieldsCartridgeitemcartridgeitemcustomfield();
        if (!$cartridge->getFromDB($cartridge_id) || !PluginIserviceDB::populateByItemsId($cartridgeitem_custom_field, $cartridge->fields['cartridgeitems_id'])) {
            return "Could not find cartridge with id $cartridge_id to uninstall it.";
        }

        $old_cartridge          = new PluginIserviceCartridge();
        $uninstalled_multiplier = 0;
        if (empty($emptied_cartridge_id)) {
            if (!PluginIserviceDB::populateByQuery(
                $old_cartridge, "join glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cfci on cfci.items_id = `glpi_cartridges`.cartridgeitems_id and cfci.itemtype = 'CartridgeItem' 
            join glpi_plugin_fields_cartridgecartridgecustomfields cfc on cfc.items_id = id and cfc.itemtype = 'Cartridge'
            where cfc.tickets_id_out_field = $ticket_id and cfc.mercury_code_field in ({$cartridgeitem_custom_field->fields['compatible_mercury_codes_field']}) and `glpi_cartridges`.plugin_fields_cartridgeitemtypedropdowns_id = {$cartridge->fields['plugin_fields_cartridgeitemtypedropdowns_id']} limit 1"
            )
            ) {
                $uninstalled_multiplier = -1;
            }
        } else {
            if (!$old_cartridge->getFromDB($emptied_cartridge_id)) {
                return "Could not find cartridge with id $emptied_cartridge_id to reinstall it.";
            }
        }

        if ($uninstalled_multiplier === 0) {
            $old_cartridge->update(
                [
                    '_no_message' => true,
                    $old_cartridge->getIndexName() => $old_cartridge->getID(),
                    'date_out' => 'NULL',
                    'tickets_id_out_field' => 'NULL',
                    'pages' => '0',
                    'pages_color_field' => '0',
                    'printed_pages_field' => $old_cartridge->fields['printed_pages_field'] + $old_cartridge->fields['pages_use_field'] - $cartridge->fields['pages_use_field'],
                    'printed_pages_color_field' => $old_cartridge->fields['printed_pages_color_field'] + $old_cartridge->fields['pages_color_use_field'] - $cartridge->fields['pages_color_use_field'],
                ]
            );
            $installed_multiplier = 1;
        }

        if (!$cartridge->update(
            [
                $cartridge->getIndexName() => $cartridge->getID(),
                '_no_message' => true,
                'suppliers_id_field' => $supplier_id,
                'date_use' => 'NULL',
                'tickets_id_use_field' => 'NULL',
                'date_out' => 'NULL',
                'pages_use_field' => 0,
                'pages_color_use_field' => 0,
            ]
        )
        ) {
            return "Could not update cartridge with id $cartridge_id";
        }

        return $installed_multiplier * $cartridge->getID();
    }

}

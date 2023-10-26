<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use Glpi\Application\View\TemplateRenderer;

class PluginIserviceTicket extends Ticket
{

    use PluginIserviceItem, PluginIserviceCommonITILObject;

    /*
     *
     * @var PluginFieldsTicketcustomfield
     */
    public $customfields = null;

    const MODE_NONE                = 0;
    const MODE_CREATENORMAL        = 1;
    const MODE_READCOUNTER         = 2;
    const MODE_CREATEINQUIRY       = 3;
    const MODE_CREATEQUICK         = 4;
    const MODE_MODIFY              = 5;
    const MODE_CREATEREQUEST       = 6;
    const MODE_PARTNERCONTACT      = 7;
    const MODE_CARTRIDGEMANAGEMENT = 9;
    const MODE_HMARFAEXPORT        = 1000;
    const MODE_CLOSE               = 9999;

    const USER_ID_READER = 27;

    const ITIL_CATEGORY_ID_CITIRE_CONTOR         = 17;
    const ITIL_CATEGORY_ID_CITIRE_CONTOR_ESTIMAT = 30;
    const ITIL_CATEGORY_ID_CITIRE_EMAINTENANCE   = 28;

    public static $field_settings          = null;
    public static $field_settings_id       = 0;
    protected static $itil_categories      = null;
    protected static $installed_cartridges = [];

    public $printer = null;

    public static function getFormModeUrl($mode): string
    {
        switch ($mode) {
        case self::MODE_READCOUNTER:
            return "ticket.form.php?mode=$mode&_redirect_on_success=" . urlencode('views.php?view=GlpiPlugin\Iservice\Specialviews\Tickets');
        default:
            return "ticket.form.php?mode=$mode";
        }
    }

    public static function getRedirectOnSuccessLink($mode): string
    {
        switch ($mode) {
        case self::MODE_READCOUNTER:
            return urlencode('views.php?view=GlpiPlugin\Iservice\Specialviews\Tickets');
        default:
            return '';
        }
    }

    public static function getItilCategoryId($itilcategory_name): int
    {
        if (self::$itil_categories == null) {
            self::refreshItilCategories();
        }

        if (isset(self::$itil_categories[strtolower($itilcategory_name)])) {
            return self::$itil_categories[strtolower($itilcategory_name)];
        } else {
            return 0;
        }
    }

    public static function refreshItilCategories(): void
    {
        global $DB;
        self::$itil_categories  = [];
        $itil_categories_result = $DB->query("SELECT id, name FROM glpi_itilcategories") or die($DB->error());
        while (($itil_category_row = $DB->fetchAssoc($itil_categories_result)) !== null) {
            self::$itil_categories[strtolower($itil_category_row['name'])] = $itil_category_row['id'];
        }
    }

    public static function getType(): string
    {
        return Ticket::getType();
    }

    public static function getModeTemplate($mode = 0): int
    {
        switch ($mode) {
        case self::MODE_CLOSE:
            return 5;
        case self::MODE_CREATENORMAL:
        case self::MODE_READCOUNTER:
        case self::MODE_CREATEINQUIRY:
        case self::MODE_CREATEQUICK:
        case self::MODE_CREATEREQUEST:
        case self::MODE_PARTNERCONTACT:
        case self::MODE_CARTRIDGEMANAGEMENT:
            return $mode;
            // Do not include MODE_MODIFY here, it should not have a template (yet!).
            // Neither does MODE_HMARFAEXPORT.
        default:
            return 0;
        }
    }

    public static function getTable($classname = null): string
    {
        return Ticket::getTable($classname);
    }

    /*
     * @return PluginIservicePrinter
     */
    public function getFirstPrinter($printerId = null): PluginIservicePrinter
    {
        $item_ticket = new Item_Ticket();
        $data        = $item_ticket->find(["`tickets_id` = {$this->getID()} and `itemtype` = 'Printer'"]);
        $printer     = new PluginIservicePrinter();
        foreach ($data as $val) {
            if ($printer->getFromDB($val["items_id"]) && !$printer->isDeleted()) {
                return $printer;
            }
        }

        return new PluginIservicePrinter();
    }

    public function getPrinterUsageAddress(): string
    {
        if (!empty($this->printer->customfields->fields['usage_address_field'])) {
            return $this->printer->customfields->fields['usage_address_field'];
        }

        return '';
    }

    public function getPrinterFieldLabel(): string
    {
        return __('Printer', 'iservice') . ($this->printer->isNewItem() ? '' : ($this->printer->isColor() ? ' color' : __(' black and white', 'iservice')));
    }

    public function getLocation(): bool|Location
    {
        $location = new Location();

        if (!empty($this->fields['locations_id'])) {
            $location->getFromDB($this->fields['locations_id']);
        } else if (empty($id) && $this->printer->getID() > 0) {
            $location->getFromDB($this->printer->fields['locations_id']);
        } else {
            return false;
        }

        return $location;
    }

    public function setPrinter($printerId = null): void
    {
        $printer = new PluginIservicePrinter();

        if (!empty($printerId)) {
            $printer->getFromDB($printerId);
        } else {
            $printer = $this->getFirstPrinter();
        }

        if (!$printer->isDeleted()) {
            $this->printer = $printer;
        }
    }

    public function getPartnerHMarfaCode($partnerId = null): ?string
    {
        if (!empty($partnerId)) {
            $partner = new PluginIservicePartner();
            $partner->getFromDB($partnerId);
        } else {
            $partner = $this->getFirstAssignedPartner();
        }

        return $partner->customfields->fields['hmarfa_code_field'] ?? null;
    }

    /*
     * @return PluginIservicePartner
     */
    public function getFirstAssignedPartner(): PluginIservicePartner
    {
        if ($this->getID() > 0) {
            $this->reloadActors();
        }

        $partner = new PluginIservicePartner();
        foreach ($this->getSuppliers(CommonITILActor::ASSIGN) as $partner_data) {
            if ($partner->getFromDB($partner_data['suppliers_id']) && !$partner->isDeleted()) {
                return $partner;
            }
        }

        return new PluginIservicePartner();
    }

    /*
     * @return PluginIserviceUser
     */
    public function getFirstAssignedUser(): PluginIserviceUser
    {
        $this->reloadActors();
        $user = new PluginIserviceUser();
        foreach ($this->getUsers(CommonITILActor::ASSIGN) as $user_data) {
            if ($user->getFromDB($user_data['users_id']) && !$user->isDeleted()) {
                return $user;
            }
        }

        return new PluginIserviceUser();
    }

    public static function getAllStatusArray($withmetaforsearch = false): array
    {
        // To be overridden by class.
        $tab = [
            self::INCOMING => _x('status', 'New'),
            self::ASSIGNED => _x('status', 'Processing (assigned)'),
            self::PLANNED => _x('status', 'Processing (planned)'),
            self::EVALUATION => __('Order', 'iservice'),
            self::WAITING => __('Pending'),
            self::SOLVED => _x('status', 'Solved'),
            self::CLOSED => _x('status', 'Closed')
        ];

        if ($withmetaforsearch) {
            $tab['notold']    = _x('status', 'Not solved');
            $tab['notclosed'] = _x('status', 'Not closed');
            $tab['process']   = __('Processing');
            $tab['old']       = _x('status', 'Solved + Closed');
            $tab['all']       = __('All');
        }

        return $tab;
    }

    public static function getPreviousIdForItemWithInput($item, $open = null): int
    {
        return self::getPreviousIdForPrinterOrSupplier(
            IserviceToolBox::getValueFromInput('_suppliers_id_assign', $item->input),
            IserviceToolBox::getItemsIdFromInput($item->input, 'Printer'),
            $item->input['effective_date_field'] ?? $item->customfields->fields['effective_date_field'] ?? '',
            $item->getID(),
            $open
        );
    }

    public static function getPreviousIdForPrinterOrSupplier($supplier_id = 0, $printer_id = 0, $effective_date = '', $id = 0, $open = null): string
    {
        return self::getLastIdForPrinterOrSupplier($supplier_id, $printer_id, $open, IserviceToolBox::isDateEmpty($effective_date) ? '' : "and (t.effective_date_field < '$effective_date' or (t.effective_date_field = '$effective_date' and t.id < $id))");
    }

    public static function getLastForPrinterOrSupplierFromInput($input, $open = null, $additional_condition = '', $additional_join = ''): self
    {
        $result = new self();
        $result->getFromDB(self::getLastIdForPrinterOrSupplierFromInput($input, $open, $additional_condition, $additional_join));
        return $result;
    }

    public static function getLastIdForItemWithInput($item, $open = null, $additional_condition = '', $additional_join = ''): string
    {
        return self::getLastIdForPrinterOrSupplierFromInput($item->input, $open, $additional_condition, $additional_join);
    }

    public static function getLastIdForPrinterOrSupplierFromInput($input, $open = null, $additional_condition = '', $additional_join = ''): string
    {
        return self::getLastIdForPrinterOrSupplier(
            IserviceToolBox::getValueFromInput('_suppliers_id_assign', $input),
            IserviceToolBox::getItemsIdFromInput($input, 'Printer'),
            $open, $additional_condition, $additional_join
        );
    }

    public static function getLastForPrinterOrSupplier($supplier_id = 0, $printer_id = 0, $open = null, $additional_condition = '', $additional_join = ''): self
    {
        $result = new self();
        $result->getFromDB(self::getLastIdForPrinterOrSupplier($supplier_id, $printer_id, $open, $additional_condition, $additional_join));
        return $result;
    }

    public static function getLastIdForPrinterOrSupplier($supplier_id = 0, $printer_id = 0, $open = null, $additional_condition = '', $additional_join = ''): int
    {
        return self::getIdForPrinterOrSupplier($supplier_id, $printer_id, $open, 'desc', $additional_condition, $additional_join);
    }

    public static function getFirstIdForItemWithInput($item, $open = null, $additional_condition = '', $additional_join = ''): int
    {
        return self::getFirstIdForPrinterOrSupplier(
            IserviceToolBox::getValueFromInput('_suppliers_id_assign', $item->input),
            IserviceToolBox::getItemsIdFromInput($item->input, 'Printer'),
            $open, $additional_condition, $additional_join
        );
    }

    public static function getFirstIdForPrinterOrSupplier($supplier_id = 0, $printer_id = 0, $open = null, $additional_condition = '', $additional_join = ''): int
    {
        return self::getIdForPrinterOrSupplier($supplier_id, $printer_id, $open, 'asc', $additional_condition, $additional_join);
    }

    public static function getIdForPrinterOrSupplier($supplier_id = 0, $printer_id = 0, $open = null, $order = 'asc', $additional_condition = '', $additional_join = ''): int
    {
        if (empty($printer_id)) {
            if (empty($supplier_id)) {
                return 0;
            }

            $object             = new Supplier_Ticket();
            $join_and_condition = self::getConditionForSupplier($supplier_id, $open, $order, $additional_condition, $additional_join);
        } else {
            $object             = new Item_Ticket();
            $join_and_condition = self::getConditionForPrinter($printer_id, $open, $order, $additional_condition, $additional_join);
        }

        if (PluginIserviceDB::populateByQuery($object, $join_and_condition, true)) {
            return $object->fields['tickets_id'];
        }

        return 0;
    }

    public static function getNewerClosedTikcetIds($ticket_id, $effective_date, $supplier_id, $printer_id): array
    {
        return self::getAllIdsForPrinterOrSupplier($supplier_id, $printer_id, 'desc', false, "and (t.effective_date_field > '$effective_date' or (t.effective_date_field = '$effective_date' and t.id > $ticket_id))");
    }

    public static function getAllIdsForPrinterOrSupplier($supplier_id = 0, $printer_id = 0, $order = 'asc', $open = null, $additional_condition = '', $additional_join = ''): array
    {
        if (empty($printer_id)) {
            if (empty($supplier_id)) {
                return [];
            }

            $table              = 'glpi_suppliers_tickets';
            $join_and_condition = self::getConditionForSupplier($supplier_id, $open, $order, $additional_condition, $additional_join);
        } else {
            $table              = 'glpi_items_tickets';
            $join_and_condition = self::getConditionForPrinter($printer_id, $open, $order, $additional_condition, $additional_join);
        }

        return PluginIserviceDB::getQueryResult("select `$table`.tickets_id from `$table` $join_and_condition", "tickets_id");
    }

    protected static function getConditionForPrinter($printer_id = 0, $open = null, $order = 'asc', $additional_condition = '', $additional_join = ''): string
    {
        if (is_array($printer_id) && !empty($printer_id['Printer']) && is_array($printer_id['Printer'])) {
            $printer_id = $printer_id['Printer'][0];
        }

        $join           = "JOIN glpi_plugin_iservice_tickets t ON t.id = `glpi_items_tickets`.tickets_id AND t.is_deleted = 0 ";
        $join          .= $additional_join;
        $open_condition = $open === null ? "" : "t.status " . ($open ? "!=" : "=") . Ticket::CLOSED . " AND";
        return "$join WHERE $open_condition `glpi_items_tickets`.items_id = $printer_id and `glpi_items_tickets`.itemtype = 'Printer' $additional_condition ORDER BY t.effective_date_field $order, t.id $order";
    }

    protected static function getConditionForSupplier($supplier_id = 0, $open = null, $order = 'asc', $additional_condition = '', $additional_join = ''): string
    {
        $join           = "JOIN glpi_plugin_iservice_tickets t ON t.id = `glpi_suppliers_tickets`.tickets_id AND t.is_deleted = 0
                 LEFT JOIN
                    (SELECT COUNT(git.tickets_id) item_count, git.tickets_id 
                     FROM glpi_items_tickets git
                     WHERE git.itemtype = 'Printer'
                     GROUP BY git.tickets_id 
                    ) ic on ic.tickets_id = t.id
                 $additional_join
                 ";
        $open_condition = $open === null ? "" : "t.status " . ($open ? "!=" : "=") . Ticket::CLOSED . " AND";
        return "$join WHERE $open_condition ic.item_count IS NULL
                AND `glpi_suppliers_tickets`.suppliers_id = $supplier_id
                AND `glpi_suppliers_tickets`.type = " . CommonITILActor::ASSIGN . " $additional_condition
                ORDER BY t.effective_date_field $order, t.id $order";
    }

    public function displayResult($result_type, $result): string
    {
        $result_texts = [
            'global_readcounter' => [
                -1 => __('Error saving ticket(s)', 'iservice') . ', ' . sprintf(__("%d ticket(s) saved.", 'iservice'), $result),
                0 => __('Error saving ticket(s)', 'iservice') . '.',
                1 => sprintf(__("%d ticket(s) saved.", 'iservice'), $result),
            ]
        ];

        if (empty($result_texts[$result_type])) {
            $message = "Internal error (invalid result type: $result_type)";
        } else {
            $message = $result_texts[$result_type][$result < 0 ? -1 : ($result > 0 ? 1 : ($result === false ? 0 : 1))];
        }

        echo "<span class='b'>$message</span>";
    }

    public function showForm($ID, $options = []): bool
    {
        $this->initForm($ID, $options);
        $this->setPrinter($options['printerId'] ?? null);
        $location = $this->getLocation();
        $this->setTicketUsersFields($ID);
        $this->setEffectiveDateField();
        $canupdate = !$ID
            || (Session::getCurrentInterface() == "central"
                && $this->canUpdateItem());

        $templateParams = [
            'item'                    => $this,
            'params'                  => $options,
            'partnerId'               => $options['partnerId'] ?? ($ID > 0 ? $this->getFirstAssignedPartner()->getID() : ''),
            'partnersFieldDisabled'   => $this->getFirstAssignedPartner()->getID() > 0,
            'printerId'               => $options['printerId'] ?? ($ID > 0 ? $this->getFirstPrinter()->getID() : ''),
            'printerFieldLabel'       => $this->getPrinterFieldLabel(),
            'printersFieldDisabled'   => $this->getFirstPrinter()->getID() > 0,
            'usageAddressField'       => $this->getPrinterUsageAddress(),
            'locationName'            => $location->fields['completename'] ?? null,
            'locationId'              => empty($this->fields['locations_id']) ? ($location ? ($location->getID() > 0 ? $location->getID() : 0) : null) : null,
            'sumOfUnpaidInvoicesLink' => IserviceToolBox::getSumOfUnpaidInvoicesLink(
                $options['partnerId'] ?? $this->getFirstAssignedPartner()->getID(),
                $this->getPartnerHMarfaCode($options['partnerId'] ?? null)
            ),
            'lastInvoiceAndCountersTable' => $this->getLastInvoiceAndCountersTable($this->printer),
            'followups'                   => $this->getFollowups($ID),
            'canupdate'                   => $canupdate,
        ];

        if ($options['mode'] == self::MODE_CLOSE) {
            $lastClosedTicket = self::getLastForPrinterOrSupplier(0, $options['printerId'] ?? $this->getFirstPrinter()->getID(), false);

            $templateParams['printer']                    = $this->printer;
            $templateParams['total2BlackRequiredMinimum'] = $lastClosedTicket->customfields->fields['total2_black_field'] ?? 0;
            $templateParams['total2ColorRequiredMinimum'] = $lastClosedTicket->customfields->fields['total2_color_field'] ?? 0;

            // If there are newer closed tickets, we do not allow counter change, as counters on the cartridges will be messed up.
            if ($ID > 0 && ($lastClosedTicket->customfields->fields['effective_date_field'] ?? '') > $this->customfields->fields['effective_date_field']) {
                $templateParams['total2BlackDisabled'] = true;
                $templateParams['total2ColorDisabled'] = true;
            }

            $templateParams['observerVisible'] = true;
            $templateParams['assignedVisible'] = true;
        }

        if ($options['mode'] == self::MODE_CLOSE) {
            TemplateRenderer::getInstance()->display("@iservice/pages/support/ticket.html.twig", $templateParams);
        } else {
            TemplateRenderer::getInstance()->display("@iservice/pages/support/inquiry.html.twig", $templateParams);
        }

        return true;
    }

    public function additionalGetFromDbSteps($ID = null): void
    {
        $this->fields['items_id']['Printer'] = array_column(PluginIserviceDB::getQueryResult("select it.items_id from glpi_items_tickets it where tickets_id = $ID and itemtype = 'Printer'"), 'items_id');
    }

    public function getCustomFieldsModelName(): string
    {
        return 'PluginFieldsTicketticketcustomfield';
    }

    /*
     * @return int Returns 0 if no order was placed, a positive number if all ordered consumables were received indicating their number, or a negative number indicating the not yet received consumables.
     */
    public function getOrderStatus(): int
    {
        if ($this->isNewItem()) {
            return 0;
        }

        global $DB;
        $query = "
            SELECT oc.ordered_consumables, rc.received_consumables
            FROM glpi_tickets t
            LEFT JOIN ( SELECT COUNT(id) ordered_consumables, tickets_id
                                    FROM glpi_plugin_iservice_intorders io
                                    WHERE NOT tickets_id IS NULL
                                    GROUP BY tickets_id
                                ) oc ON oc.tickets_id = t.id
            LEFT JOIN ( SELECT COUNT(io.id) received_consumables, tickets_id
                                    FROM glpi_plugin_iservice_intorders io
                                    JOIN glpi_plugin_iservice_orderstatuses os ON os.id = io.plugin_iservice_orderstatuses_id
                                    WHERE NOT tickets_id IS NULL AND os.weight >= " . PluginIserviceOrderStatus::WEIGHT_RECEIVED . "
                                    GROUP BY tickets_id
                                ) rc ON rc.tickets_id = t.id
            WHERE t.id = {$this->getID()}
            ";
        if (($result = $DB->query($query)) !== false) {
            $query_result = $DB->fetchAssoc($result);
            return intval($query_result['ordered_consumables']) == intval($query_result['received_consumables']) ? intval($query_result['ordered_consumables']) : intval($query_result['received_consumables']) - intval($query_result['ordered_consumables']);
        }

        return 0;
    }

    public function explodeArrayFields(): void
    {
        foreach ($this->fields as $field_name => $field_value) {
            if (strpos($field_name, '[')) {
                $parts                                             = explode('[', $field_name, 2);
                $this->fields[$parts[0]][substr($parts[1], 0, -1)] = $field_value;
            }
        }
    }

    public function addCartridge(&$error_message, $cartridgeitems_id, $supplier_id, $printer_id, $install_date = null, $imposed_cartridge_id = null): bool
    {
        $cartridge_item_data = explode('l', $cartridgeitems_id, 2);
        $cartridge_item_id   = $cartridge_item_data[0];
        $base_condition      = "AND EXISTS (SELECT * FROM glpi_plugin_iservice_consumables_tickets WHERE amount > 0 AND new_cartridge_ids LIKE CONCAT('%|', glpi_cartridges.id, '|%'))";
        $location_condition  = 'AND (locations_id_field IS null OR locations_id_field < 1)';
        $printer_condition   = 'AND printers_id = 0 AND date_use IS null AND date_out IS null';
        $date_condition      = empty($install_date) ? '' : "AND date_in <= '$install_date'";
        if (count($cartridge_item_data) > 1) {
            $cartridge_item_data = explode('p', $cartridge_item_data[1], 2);
            $location_condition  = "AND locations_id_field = $cartridge_item_data[0]";
            if (count($cartridge_item_data) > 1) {
                $printer_condition = "AND printers_id = $cartridge_item_data[1] AND date_out IS null";
            }
        }

        $cartridge              = new PluginIserviceCartridge();
        $cartridge_customfields = new PluginFieldsCartridgeitemcartridgeitemcustomfield();
        $cartridges             = $cartridge->find("suppliers_id_field = $supplier_id AND cartridgeitems_id = $cartridge_item_id $base_condition $location_condition $printer_condition $date_condition", ["id ASC"]);

        // First check the cartridges at the given partner. If there are none, check the partners in the same group.
        if (count($cartridges) === 0) {
            $cartridges = $cartridge->find("FIND_IN_SET (suppliers_id_field, (SELECT group_field FROM glpi_plugin_fields_suppliersuppliercustomfields WHERE items_id = $supplier_id)) AND cartridgeitems_id = $cartridge_item_id $location_condition $printer_condition $date_condition", ["id ASC"]);
        }

        if (count($cartridges) === 0) {
            $error_message = "Stoc insuficient!";
            return false;
        }

        if (!empty($imposed_cartridge_id)) {
            if (in_array($imposed_cartridge_id, array_column($cartridges, 'id'))) {
                $cartridge_id_to_install = $imposed_cartridge_id;
            } else {
                $error_message = "Cartușul impus pentru instalare nu este instalabil pe acest aparat!";
                return false;
            }
        } else {
            $cartridge_id_to_install = array_shift($cartridges)['id'];
        }

        $cartridge->getFromDB($cartridge_id_to_install);
        PluginIserviceDB::populateByItemsId($cartridge_customfields, $cartridge->fields['cartridgeitems_id']);
        $cartridge->fields['printers_id']        = $printer_id;
        $cartridge->fields['mercury_code_field'] = $cartridge_customfields->fields['mercury_code_field'];

        $plugin_iservice_cartridges_tickets = new PluginIserviceCartridge_Ticket();

        $used_types = PluginIserviceDB::getQueryResult(
            "
            select ct.plugin_fields_cartridgeitemtypedropdowns_id selected_type
            from glpi_plugin_iservice_cartridges_tickets ct
            join glpi_cartridges c on c.id = ct.cartridges_id
            where ct.tickets_id = {$this->getID()}
              and c.cartridgeitems_id = {$cartridge->fields['cartridgeitems_id']}
            "
        );

        foreach (explode(',', $cartridge_customfields->fields['supported_types_field']) as $supported_type) {
            if (!in_array($supported_type, array_column($used_types, 'selected_type'))) {
                $cartridge->fields['plugin_fields_cartridgeitemtypedropdowns_id'] = $supported_type;
                break;
            }
        }

        $first_emptiable_cartridge = PluginIserviceCartridge::getFirstEmptiableByCartridge($cartridge);

        if (!$plugin_iservice_cartridges_tickets->add(
            [
                'add' => 'add',
                'tickets_id' => $this->getID(),
                'cartridges_id' => $cartridge_id_to_install,
                'plugin_fields_cartridgeitemtypedropdowns_id' => $cartridge->fields['plugin_fields_cartridgeitemtypedropdowns_id'],
                'cartridges_id_emptied' => empty($first_emptiable_cartridge[$cartridge->getIndexName()]) ? 'NULL' : $first_emptiable_cartridge[$cartridge->getIndexName()],
                '_no_message' => true
            ]
        )
        ) {
            return false;
        }

        if (!$cartridge->update(['id' => $cartridge_id_to_install, 'printers_id' => $printer_id, '_no_message' => true])) {
            return  false;
        }

        return true;
    }

    public function addMessageOnAddAction(): void
    {
        $this->internalAddMessageOnChange('add');
    }

    public function addMessageOnUpdateAction(): void
    {
        $this->internalAddMessageOnChange('update');

    }

    protected function internalAddMessageOnChange($changeType): void
    {
        $addMessAfterRedirect = false;
        if (isset($this->input["_$changeType"])) {
            $addMessAfterRedirect = true;
        }

        if (isset($this->input['_no_message']) || !$this->auto_message_on_action) {
            $addMessAfterRedirect = false;
        }

        if (!$addMessAfterRedirect) {
            return;
        }

        global $CFG_PLUGIN_ISERVICE;

        $ticket_name = (isset($this->input['_no_message_link']) ? $this->getID() : $this->getLink(['mode' => $this->input['_mode']]));

        switch ($this->input['_mode']) {
        case PluginIserviceTicket::MODE_CREATENORMAL:
            $ticketreport_href = "$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.report.php?id={$this->getID()}";
            $suffix            = sprintf("%s <a href='$ticketreport_href' target='_blank'>%s</a>", ucfirst(__('see', 'iservice')), lcfirst(__('intervention report', 'iservice')));
            break;
        case PluginIserviceTicket::MODE_CREATEQUICK:
        case PluginIserviceTicket::MODE_CLOSE:
            $supplier = PluginIservicePartner::getFromTicketInput($this->input);
            $s2       = PluginIservicePartner::get(1);
            if ($supplier->isNewItem()) {
                $suffix = "";
            } else {
                $printers_href = "$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=GlpiPlugin\Iservice\Specialviews\Printers&printers0[supplier_name]=" . $supplier->fields['name'];
                $suffix        = sprintf("%s <a href='$printers_href'>%s</a>", ucfirst(__('see', 'iservice')), lcfirst(_n('Printer', 'Printers', 2, 'iservice')));
            }
            break;
        case PluginIserviceTicket::MODE_PARTNERCONTACT:
            $suffix = "<a href='#' onclick='window.close();'>" . __('Close', 'iservice') . "</a>";
            break;
        default:
            $suffix = '';
            break;
        }

        if (!empty($suffix)) {
            $suffix = "<br>$suffix";
        }

        IserviceToolBox::clearAfterRedirectMessages(INFO);

        Session::addMessageAfterRedirect(sprintf(__('Ticket %s saved successfully.', 'iservice'), stripslashes($ticket_name)) . stripslashes($suffix));
    }

    public function getLink($options = []): string
    {
        if (empty($this->fields['id'])) {
            return '';
        }

        $label = $this->getID();

        if ($this->no_form_page || !$this->can($this->fields['id'], READ)) {
            return $label;
        }

        return "<a href='{$this->getLinkURL($options)}'>$label</a>";
    }

    public function getLinkURL($additionalParams = []): string
    {
        $link = parent::getLinkURL();
        foreach ($additionalParams as $paramName => $paramValue) {
            $link .= "&$paramName=$paramValue";
        }

        return $link;
    }

    public function getInstalledCartridgeIds(): array
    {
        return self::getTicketInstalledCartridgeIds($this);
    }

    public static function getTicketInstalledCartridgeIds(Ticket $ticket): array
    {
        if (empty(self::$installed_cartridges[$ticket->getID()])) {
            self::$installed_cartridges[$ticket->getID()] = array_column(PluginIserviceCartridge_Ticket::getForTicketId($ticket->getID()), 'cartridges_id');
        }

        return self::$installed_cartridges[$ticket->getID()];
    }

    public function isClosed(): bool
    {
        return self::isTicketClosed($this);
    }

    public static function isTicketClosed(Ticket $ticket): bool
    {
        return ($ticket->fields['status'] ?? '') == TICKET::CLOSED;
    }

    public static function wasTicketClosed(Ticket $ticket): bool
    {
        return ($ticket->oldvalues['status'] ?? '') == TICKET::CLOSED;
    }

    public function isClosing(): bool
    {
        return self::isTicketClosing($this);
    }

    public static function isTicketClosing(Ticket $ticket): bool
    {
        return self::isTicketChangingStatus($ticket) && ($ticket->input['status'] ?? '') == Ticket::CLOSED;
    }

    public static function wasTicketClosing(Ticket $ticket): bool
    {
        return self::wasTicketChangingStatus($ticket) && ($ticket->input['status'] ?? '') == Ticket::CLOSED;
    }

    public function isOpening(): bool
    {
        return self::isTicketOpening($this);
    }

    public static function isTicketOpening(Ticket $ticket): bool
    {
        return self::isTicketClosedStatusChanging($ticket) && ($ticket->input['status'] ?? Ticket::CLOSED) != Ticket::CLOSED;
    }

    public static function wasTicketOpening(Ticket $ticket): bool
    {
        return self::wasTicketClosedStatusChanging($ticket) && ($ticket->input['status'] ?? Ticket::CLOSED) != Ticket::CLOSED;
    }

    public function isStatusChanging(): bool
    {
        return self::isTicketChangingStatus($this);
    }

    public static function isTicketChangingStatus(Ticket $ticket): bool
    {
        if (empty($ticket->input['status'])) {
            return false;
        }

        return $ticket->input['status'] != $ticket->fields['status'] ?? '';
    }

    public static function wasTicketChangingStatus(Ticket $ticket): bool
    {
        if (empty($ticket->input['status'])) {
            return false;
        }

        return $ticket->input['status'] != ($ticket->oldvalues['status'] ?? '');
    }

    public function isClosedStatusChanging(): bool
    {
        return self::isTicketClosedStatusChanging($this);
    }

    public static function isTicketClosedStatusChanging(Ticket $ticket): bool
    {
        return self::isTicketClosing($ticket) xor self::isTicketClosed($ticket);
    }

    public static function wasTicketClosedStatusChanging(Ticket $ticket): bool
    {
        return self::wasTicketClosing($ticket) xor self::wasTicketClosed($ticket);
    }

    public function updateItem($ticketId, $post): void
    {

        $this->addPartner($ticketId, $post);

        $this->addPrinter($ticketId, $post);

        $this->createFollowup($ticketId, $post);
    }

    public function addPartner($ticketId, $post): bool
    {
        if (!empty($post['suppliers_id']) && ($this->getFirstAssignedPartner())->getID() < 1) {
            if (is_array($post['suppliers_id'])) {
                $tab_assign = $post['suppliers_id'];
            } else {
                $tab_assign   = [];
                $tab_assign[] = $post['suppliers_id'];
            }

            $supplierToAdd   = [];
            $supplier_ticket = new Supplier_Ticket();
            foreach ($tab_assign as $assign) {
                if (in_array($assign, $supplierToAdd) || empty($assign)) {
                    // This assigned supplier ID is already added.
                    continue;
                }

                if ($supplier_ticket->add(
                    [
                        'tickets_id'       => $ticketId,
                        'suppliers_id'     => $assign,
                        'type'             => CommonITILActor::ASSIGN,
                        'use_notification' => 0,
                    ]
                )
                ) {
                    $supplierToAdd[] = $assign;
                }
            }

            return !empty($supplierToAdd);
        }

        return false;
    }

    public function addPrinter($ticketId, $post): bool
    {
        if (!empty($post['printer_id']) && ($this->getFirstPrinter())->getID() < 1) {
            return (new Item_Ticket())->add(
                [
                    'items_id'      => $post['printer_id'],
                    'itemtype'      => 'Printer',
                    'tickets_id'    => $ticketId,
                    '_disablenotif' => true
                ]
            );
        }

        return false;
    }

    public function prepareInputForAdd($input): array|bool
    {
        foreach (array_keys($input['items_id']) as $itemtype) {
            foreach ($input['items_id'][$itemtype] as $key => $value) {
                if (empty($value)) {
                    unset($input['items_id'][$itemtype][$key]);
                }
            }

            if (empty($input['items_id'][$itemtype])) {
                unset($input['items_id'][$itemtype]);
            }
        }

        return parent::prepareInputForAdd($input);
    }

    public function prepareInputForUpdate($input): array
    {
        foreach ([CommonITILActor::ASSIGN => 'assign', CommonITILActor::REQUESTER => 'requester', CommonITILActor::OBSERVER => 'observer'] as $user_type_key => $user_type) {
            foreach (['glpi_tickets_users' => 'users_id', 'glpi_suppliers_tikcets' => 'suppliers_id'] as $table => $item_type) {
                if ((($input['_' . $item_type . '_' . $user_type] ?? 0) ?: 0) != (($input['_' . $item_type . '_' . $user_type . '_original'] ?? 0) ?: 0)) {
                    global $DB;
                    $DB->query("delete from $table where type = $user_type_key and tickets_id = $input[id]");
                }

                if (!is_array($input['_' . $item_type . '_' . $user_type] ?? [])) {
                    if (empty($input['_' . $item_type . '_' . $user_type])) {
                        unset($input['_' . $item_type . '_' . $user_type]);
                    } else {
                        $input['_' . $item_type . '_' . $user_type] = [$input['_' . $item_type . '_' . $user_type]];
                    }
                }
            }
        }

        $input = parent::prepareInputForUpdate($input);
        if (isset($this->fields['items_id']['Printer'][0])) {
            $this->fields['items_id'] = $this->fields['items_id']['Printer'][0];
        }

        if (isset($input['items_id']['Printer'][0])) {
            $input['items_id'] = $input['items_id']['Printer'][0];
        }

        return $input;
    }

    public static function sendNotificationForTicket($ticket, $config_key = '', $params = []): ?bool
    {
        global $CFG_GLPI;

        /*
        * @var $ticket PluginIserviceTicket
        */
        if (!($ticket instanceof PluginIserviceTicket)) {
            $ticket_id = ($ticket instanceof Ticket) ? $ticket->getID() : intval($ticket);
            $ticket    = new PluginIserviceTicket();
            if (!$ticket->getFromDB($ticket_id)) {
                return false;
            }
        }

        $ticket->customfields->getFromDB($ticket->customfields->getID());
        $supplier     = $ticket->getFirstAssignedPartner();
        $printer      = $ticket->getFirstPrinter();
        $followup     = new PluginIserviceTicketFollowup();
        $itilcategory = new ITILCategory();
        $itilcategory->getFromDB($ticket->fields['itilcategories_id']);

        $config_array = [
            'notification' => [
                'to_addresses' => $ticket->customfields->fields['notification_mail_field'],
                'subject' => $ticket->fields['name'],
                'body' =>
                    "Pentru partenerul {$supplier->fields['name']}\n\n" .
                    "Tichetul {$ticket->getID()} a fost [ticket_verb] la data de " . date('d.m.Y H:i:s') . "\n\n" .
                    "Titlu tichet: {$ticket->fields['name']}\n" .
                    "Nume aparat: {$printer->fields['name']}\n" .
                    "Serie aparat: {$printer->fields['serial']}\n\n" .
                    "Descriere tichet:\n" . strip_tags(IserviceToolBox::br2nl($ticket->fields['content'])) . "\n\n" .
                    "Adnotări:\n" . $followup->getShortForMail($ticket->getID()) . "\n"
            ],
            'readcounter' => [
                'subject' => "{$itilcategory->fields['name']} nr. {$ticket->getID()} - {$supplier->fields['name']}",
                'body' => $printer->isNewItem() ? "{$ticket->fields['name']}\nAcest tichet nu are aparat asociat." :
                    "Vă mulțumim pentru raportare. Următoarele informații au fost salvate:\n\n" .
                    "Număr tichet: {$ticket->getID()}\n" .
                    "Dată tichet: {$ticket->fields['date']}\n" .
                    "Titlu tichet: {$ticket->fields['name']}\n" .
                    "Partener: {$supplier->fields['name']}\n" .
                    "Nume aparat: {$printer->fields['name']}\n" .
                    "Serie aparat: {$printer->fields['serial']}\n" .
                    (empty($ticket->customfields->fields['total2_black_field']) ? "" : "Contor alb-negru: {$ticket->fields['total2_black_field']}\n") .
                    (empty($ticket->customfields->fields['total2_color_field']) ? "" : "Contor color: {$ticket->fields['total2_color_field']}\n") .
                    "Descriere tichet:\n" . strip_tags(IserviceToolBox::br2nl($ticket->fields['content'])) . "\n\n" .
                    "Adnotări:\n" . $followup->getShortForMail($ticket->getID()) . "\n"
            ],
        ];

        $config = array_merge(
            $config_array[$config_key] ?? [
                'force_new' => false,
            ], $params
        );

        if ($config['force_new'] || $ticket->fields['status'] === Ticket::INCOMING) {
            $ticket_verb = 'creat';
        } elseif (in_array($ticket->fields['status'], Ticket::getClosedStatusArray())) {
            $ticket_verb = 'închis';
        } elseif ($ticket->fields['status'] === Ticket::SOLVED) {
            $ticket_verb = 'soluționat';
        } else {
            $ticket_verb = 'modificat';
        }

        $config['body'] = str_replace('[ticket_verb]', $ticket_verb, $config['body']);

        $mmail = new GLPIMailer();

        $mmail->AddCustomHeader("Auto-Submitted: auto-generated");
        $mmail->AddCustomHeader("X-Auto-Response-Suppress: OOF, DR, NDR, RN, NRN");

        $mmail->SetFrom($CFG_GLPI["admin_email"], $CFG_GLPI["admin_email_name"], false);

        foreach (preg_split("/(,|;)/", $config['to_addresses']) as $to_address) {
            $mmail->AddAddress(trim($to_address));
        }

        $mmail->Subject = "[iService] " . $config['subject'];

        $mmail->Body = $config['body'] . "\n\n--\n$CFG_GLPI[mailing_signature]";

        if (!$mmail->Send()) {
            Session::addMessageAfterRedirect(__('Could not send ticketreport to', 'iservice') . " {$config['to_addresses']}: $mmail->ErrorInfo", false, ERROR);
        } else {
            Session::addMessageAfterRedirect(__('Confirmation mail sent to', 'iservice') . " {$config['to_addresses']}");
        }

        return true;

    }

    public function getLastInvoiceAndCountersTable($printer)
    {
        if ($printer->getID() < 1) {
            return null;
        }

        $colorPrinter   = $printer->isColor();
        $plotterPrinter = $printer->isPlotter();

        $infoTableHeader = new PluginIserviceHtml_table_row();
        $infoTableHeader->populateCells(
            [
                __('Last invoice date', 'iservice'),
                __('Invoice expiry date', 'iservice'),
                ($plotterPrinter ? __('Printed surface', 'iservice') : __('Color counter', 'iservice')) . ' ' . __('last invoice', 'iservice'),
                ($plotterPrinter ? __('Printed surface', 'iservice') : __('Color counter', 'iservice')) . ' ' . __('last closed ticket', 'iservice'),
                __('Black counter', 'iservice') . ' ' . __('last invoice', 'iservice'),
                __('Black counter', 'iservice') . ' ' . __('last closed ticket', 'iservice'),
            ], '', '', 'th'
        );
        return new PluginIserviceHtml_table(
            'tab_cadrehov wide80', $infoTableHeader, new PluginIserviceHtml_table_row(
                '', [
                    new PluginIserviceHtml_table_cell($printer->customfields->fields['invoice_date_field'] ?? '', 'nowrap'),
                    new PluginIserviceHtml_table_cell($printer->customfields->fields['invoice_expiry_date_field'] ?? '', 'nowrap'),
                    $printer->customfields->fields['invoiced_total_color_field'] ?? '',
                    $printer->lastClosedTicket()->customfields->fields['total2_color_field'] ?? '',
                    $printer->customfields->fields['invoiced_total_black_field'] ?? '',
                    $printer->lastClosedTicket()->customfields->fields['total2_black_field'] ?? '',
                ]
            ), 'display: inline-block;text-align: center;'
        );
    }

    public function getFollowUps($ticketId)
    {
        if ($ticketId < 1) {
            return null;
        }

        return (new PluginIserviceTicketFollowup())->showShortForTicket($ticketId);
    }

    public function setTicketUsersFields($ticketId): void
    {
        if ($ticketId < 1) {
            return;
        }

        $ticketUsers = (new Ticket_User())->getActors($ticketId);

        $this->fields['_users_id_assign']    = $ticketUsers[CommonITILActor::ASSIGN][0]['users_id'] ?? '';
        $this->fields['_users_id_observer']  = $ticketUsers[CommonITILActor::OBSERVER][0]['users_id'] ?? '';
        $this->fields['_users_id_requester'] = $ticketUsers[CommonITILActor::REQUESTER][0]['users_id'] ?? '';

        if (empty($this->fields['_users_id_assign']) || $this->fields['_users_id_assign'] < 1) {
            $this->fields['_users_id_assign'] = empty($this->printer->fields['users_id_tech']) ? '' : $this->printer->fields['users_id_tech'];
        }
    }

    public function preProcessPostData($post): array
    {
        if (isset($post['_followup_content'])) {
            $post['_followup']['content'] = $post['_followup_content'];
            unset($post['_followup_content']);
        }

        return $post;
    }

    public function createFollowup($id, $post)
    {
        if (isset($post['_followup'])) {
            $followup = new ITILFollowup();
            $type     = "new";
            if (isset($item->fields["status"]) && ($item->fields["status"] == Ticket::SOLVED)) {
                $type = "solved";
            }

            $post['_followup']['items_id'] = $id;
            $post['_followup']['itemtype'] = 'Ticket';
            $post['_followup']['type']     = $type;
            if (!empty($post['_followup']['content'])) {
                $followup->add($post['_followup']);
            }
        }

    }

    public function setEffectiveDateField(): void
    {
        if (empty($this->customfields)) {
            $this->customfields = new PluginFieldsTicketticketcustomfield();
        }

        if (!isset($this->customfields->fields['effective_date_field']) || IserviceToolBox::isDateEmpty($this->customfields->fields['effective_date_field'])) {
            $this->customfields->fields['effective_date_field'] = date('Y-m-d H:i:s');
        }
    }

}

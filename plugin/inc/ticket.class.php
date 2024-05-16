<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use Glpi\Application\View\TemplateRenderer;

class PluginIserviceTicket extends Ticket
{

    use PluginIserviceCommonITILObject;
    use PluginIserviceItem {
        post_addItem as postAddItem;
        post_updateItem as postUpdateItem;
    }

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

    public static $field_settings          = null;
    public static $field_settings_id       = 0;
    protected static $itilCategories       = null;
    protected static $installed_cartridges = [];

    public static $customFieldsModelName = 'PluginFieldsTicketticketcustomfield';

    public $printer             = null;
    const EXPORT_TYPE_NOTICE_ID = 1;

    const EXPORT_TYPE_INVOICE_ID = 2;

    public function canViewItem(): bool
    {
        return parent::canViewItem() || $this->isUserTechPark();
    }

    public function isUserTechPark(): bool
    {
        $printer = $this->getFirstPrinter();
        return ($printer->fields['users_id_tech'] ?? '') === Session::getLoginUserID();
    }

    public static function getFormModeUrl($mode): string
    {
        switch ($mode) {
        case self::MODE_READCOUNTER:
            return "ticket.form.php?mode=$mode&_redirect_on_success=" . urlencode('views.php?view=Tickets');
        default:
            return "ticket.form.php?mode=$mode";
        }
    }

    public static function getRedirectOnSuccessLink($mode): string
    {
        switch ($mode) {
        case self::MODE_READCOUNTER:
            return urlencode('views.php?view=Tickets');
        default:
            return '';
        }
    }

    public static function getItilCategoryId(string $itilcategoryName): int
    {
        if (self::$itilCategories == null) {
            self::refreshItilCategories();
        }

        return self::$itilCategories[strtolower($itilcategoryName)] ?? 0;
    }

    public static function refreshItilCategories(): void
    {
        global $DB;
        self::$itilCategories   = [];
        $itil_categories_result = $DB->query("SELECT id, name FROM glpi_itilcategories") or die($DB->error());
        while (($itil_category_row = $DB->fetchAssoc($itil_categories_result)) !== null) {
            self::$itilCategories[strtolower($itil_category_row['name'])] = $itil_category_row['id'];
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
        $label = __('Printer', 'iservice');

        if (!empty($this->printer)) {
            $label .= $this->printer->isNewItem() ? '' : ($this->printer->isColor() ? ' color' : __(' black and white', 'iservice'));
        }

        return $label;
    }

    public function getLocation(): bool|Location
    {
        $location = new Location();

        if (!empty($this->fields['locations_id'])) {
            $location->getFromDB($this->fields['locations_id']);
        } else if (empty($id) && !empty($this->printer) && $this->printer->getID() > 0) {
            $location->getFromDB($this->printer->fields['locations_id']);
        } else {
            return false;
        }

        return $location;
    }

    public function setPrinter($printerId = null): void
    {
        $printer = $this->getFirstPrinter();

        if ($printer->getID() > 0) {
            $this->printer = $printer;
            return;
        } elseif (!empty($printerId)
            && $printer->getFromDB($printerId)
            && !$printer->isDeleted()
            && $printer->getID() > 0
        ) {
            $this->printer = $printer;
            return;
        }

        $this->printer = null;
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
            IserviceToolBox::getInputVariable('suppliers_id') ?? IserviceToolBox::getValueFromInput('_suppliers_id_assign', $input),
            IserviceToolBox::getInputVariable('printer_id') ?? IserviceToolBox::getValueFromInput('printer_id', $input),
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
            IserviceToolBox::getInputVariable('suppliers_id') ?? IserviceToolBox::getValueFromInput('_suppliers_id_assign', $item->input),
            IserviceToolBox::getInputVariable('printer_id') ?? IserviceToolBox::getItemsIdFromInput($item->input, 'Printer'),
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

    public function displayResult($result_type, $result): void
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
        $printerId = $this->getPrinterId();
        if (isset($this->fields['items_id']) && !isset($this->fields['items_id']['Printer'])) {
            $this->fields['items_id'] = ['Printer' => [$printerId]];
        }

        $this->fields['_suppliers_id_assign'] = $partnerId = $this->getPartnerId($options);
        $location                             = $this->getLocation();
        $this->setTicketUsersFields($ID, $options);
        $canUpdate                       = !$ID || (Session::getCurrentInterface() == "central" && $this->canUpdateItem());
        $prepared_data['field_required'] = [];
        $closed                          = $this->isClosed();

        $isColorPrinter        = $this->printer?->isColor();
        $isPlotterPrinter      = $this->printer?->isPlotter();
        $printersFieldReadonly = $this->getFirstPrinter()->getID() > 0;

        $options['target']       = $options['target'] ?? '';
        $options['withtemplate'] = 'ticket';

        $templateParams = [
            'item'                    => $this,
            'params'                  => $options,
            'partnerId'               => $partnerId,
            'partnersFieldReadonly'   => $this->getFirstAssignedPartner()->getID() > 0,
            'printerId'               => $printerId,
            'printerFieldLabel'       => $this->getPrinterFieldLabel(),
            'printersFieldReadonly'   => $printersFieldReadonly,
            'usageAddressField'       => $this->getPrinterUsageAddress(),
            'locationName'            => $location->fields['completename'] ?? null,
            'locationId'              => !empty($this->fields['locations_id']) ? $this->fields['locations_id'] : ($location ? ($location->getID() > 0 ? $location->getID() : 0) : null),
            'sumOfUnpaidInvoicesLink' => $partnerId ? IserviceToolBox::getSumOfUnpaidInvoicesLink(
                $partnerId,
                $this->getPartnerHMarfaCode($partnerId)
            ) : null,
            'lastInvoiceAndCountersTable' => $this->getLastInvoiceAndCountersTable($this->printer),
            'followupsData'               => $this->getFollowups($ID),
            'canUpdate'                   => $canUpdate,
            'alertOnStatusChange'         => $this->fields['status'] == self::SOLVED && $this->getID() > 0,
            'solvedStatusValue'           => self::SOLVED,
            'consumablesTableData'        => PluginIserviceConsumable_Ticket::getDataForTicketConsumablesSection($this, $prepared_data['field_required'], (empty($ID) || ($ID > 0 && $this->customfields->fields['delivered_field']))),

            'effectiveDate'                => $this->customfields->fields['effective_date_field'] ?? date('Y-m-d H:i:s'),
            'effectiveDateFieldReadonly'   => $this->fields['status'] == self::CLOSED,
            'cartridgeInstallDateFieldReadonly' => $this->fields['status'] == self::CLOSED,
            'emMailIdField'             => $options['em_mail_id_field'] ?? null,
            'technicians'               => IserviceToolBox::getUsersByProfiles(['tehnician']),
            'iServiceTicketTemplate'    => new PluginIserviceTicketTemplate(),
            'title' => !empty($this->fields['name']) ? $this->fields['name'] : $options['getParams']['title'] ?? '',
            'content' => !empty($this->fields['content']) ? $this->fields['content'] : $options['getParams']['content'] ?? '',
        ];

        $renderExtendedForm = $ID > 0 || $printerId > 0;

        if ($renderExtendedForm) {
            $lastTicket       = self::getLastForPrinterOrSupplier($partnerId, $printerId);
            $lastClosedTicket = self::getLastForPrinterOrSupplier(0, $printerId, false);

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

            $lastTicketWithCartridge = self::getLastForPrinterOrSupplier(0, $printerId, null, '', 'JOIN glpi_plugin_iservice_cartridges_tickets ct on ct.tickets_id = t.id');
            if ($ID > 0 && ($lastTicketWithCartridge->customfields->fields['effective_date_field'] ?? '') > $this->customfields->fields['effective_date_field']) {
                $warning = "Atenție. Există un tichet mai nou ({$lastTicketWithCartridge->getID()}) cu cartușe instalate. Ștergeți întâi cartușele de pe acel tichet.";
                $warning = sprintf(__('Warning. There is a newer ticket %1$d with installed cartridges. First remove cartridges from that ticket.', 'iservice'), [$lastTicketWithCartridge->getID()]);
            }

            $templateParams['changeablesTableData'] = array_merge(
                [
                    'cartridge_link' => $this->printer ? "views.php?view=Cartridges&pmi={$this->printer->fields['printermodels_id']}&cartridges0[filter_description]=compatibile {$this->printer->fields['name']}" : null,
                    'warning' => $warning ?? null,
                ],
                PluginIserviceCartridge_Ticket::getDataForTicketChangeableSection($this, $prepared_data['field_required'], false, ($closed || (($lastTicketWithCartridge->customfields->fields['effective_date_field'] ?? '') > ($this->customfields->fields['effective_date_field'] ?? '') && $ID > 0))),
            );

            if (!empty($this->printer->fields['printermodels_id'])) {
                $templateParams['changeablesTableData']['cartridgeLink'] = "views.php?view=Cartridges&pmi={$this->printer->fields['printermodels_id']}&cartridges0[filter_description]=compatibile {$this->printer->fields['name']}";
            }

            $templateParams['exportTypeOptions'] = [
                self::EXPORT_TYPE_NOTICE_ID => __('Notice', 'iservice'),
                self::EXPORT_TYPE_INVOICE_ID => _n('Invoice', 'Invoices', 1, 'iservice'),
                '' => Dropdown::EMPTY_VALUE,
            ];

            if ($this->customfields->fields['exported_field'] ?? false) {
                $months   = [
                    1 => 'ianuarie',
                    2 => 'februarie',
                    3 => 'martie',
                    4 => 'aprilie',
                    5 => 'mai',
                    6 => 'iunie',
                    7 => 'iulie',
                    8 => 'august',
                    9 => 'septembrie',
                    10 => 'octombrie',
                    11 => 'noiembrie',
                    12 => 'decembrie',
                ];
                $supplier = new PluginIservicePartner();
                $supplier->getFromDB($partnerId);
                $mailSubject                            = "Factura ExpertLine - {$supplier->fields['name']} - " . $months[date("n")] . ", " . date("Y");
                $mailBody                               = $supplier->getMailBody();
                $templateParams['sendMailButtonConfig'] = [
                    'label' => __('Send email to client', 'iservice'),
                    'href' => "mailto:{$supplier->customfields->fields['email_for_invoices_field']}?subject=$mailSubject&body=$mailBody",
                    'class' => 'vsubmit',
                ];
            }

            $templateParams['csvCounterButtonConfig'] = $this->getCsvCounterButtonConfig($closed, $this->printer ?? null, $isColorPrinter, $isPlotterPrinter, $templateParams['total2BlackDisabled'] ?? false, $templateParams['total2BlackRequiredMinimum'] ?? null, $templateParams['total2ColorRequiredMinimum'], $lastClosedTicket);
            $templateParams['estimateButtonConfig']   = $this->getEstimateButtonConfig($this, $closed, $printerId, $this->printer ?? null, $lastTicket, $lastClosedTicket, $isColorPrinter, $isPlotterPrinter);
        }

        $movementRelatedData = $this->getMovementRelatedData($ID, $printerId, $canUpdate);

        $templateParams['movementRelatedFields'] = $movementRelatedData['fields'] ?? null;

        $options['ticketHasConsumables'] = !empty($templateParams['consumablesTableData']['consumablesTableSection']['rows']);
        $templateParams['submitButtons'] = $this->getButtonsConfig($ID, $options, $movementRelatedData['movement'] ?? null);

        if ($renderExtendedForm) {
            TemplateRenderer::getInstance()->display("@iservice/pages/support/ticket.html.twig", $templateParams);
        } else {
            TemplateRenderer::getInstance()->display("@iservice/pages/support/inquiry.html.twig", $templateParams);
        }

        return true;
    }

    public function getCsvCounterButtonConfig($closed, $printer, $colorPrinter, $plotterPrinter, $total2BlackDisabled, $total2BlackRequiredMinimum, $total2ColorRequiredMinimum, $lastClosedTicket): array
    {
        if (empty($printer)) {
            return [];
        }

        $csv_data = PluginIserviceEmaintenance::getDataFromCsvsForSpacelessSerial($printer->getSpacelessSerial());
        if (!empty($csv_data) && !$total2BlackDisabled) {
            $style   = '';
            $onclick = '';
            $title   = "Click pentru valoarea din CSV\nData contor: {$csv_data['effective_date_field']}\nContor black: ";

            if (!empty($csv_data['total2_black_field']['error'])) {
                $style  = "style='color: red;'";
                $title .= $csv_data['total2_black_field']['error'];
            } else {
                $title .= $csv_data['total2_black_field'];
                if ($csv_data['total2_black_field'] < $total2BlackRequiredMinimum) {
                    $style  = "style='color: red;'";
                    $title .= " < $total2BlackRequiredMinimum (minim valid)!";
                } else {
                    $onclick .= sprintf("$(\"[name=total2_black_field]\").val(%d);", $csv_data['total2_black_field']);
                }
            }

            if ($colorPrinter || $plotterPrinter) {
                $title .= $plotterPrinter ? "\nSuprafață printată:" : "\nContor color: ";
                if (!empty($csv_data['total2_color_field']['error'])) {
                    $style  = "style='color: red;'";
                    $title .= $csv_data['total2_color_field']['error'];
                } else {
                    $title .= $csv_data['total2_color_field'];
                    if ($csv_data['total2_color_field'] < $total2ColorRequiredMinimum) {
                        $style  = "color: red;";
                        $title .= " < $total2ColorRequiredMinimum (minim valid)!";
                    } else {
                        $onclick .= sprintf("$(\"[name=total2_color_field]\").val(%d);", $csv_data['total2_color_field']);
                    }
                }
            }

            $dataLucRequieredMinimum = $closed ? '2000-01-01' : date('Y-m-d H:i', strtotime($lastClosedTicket->customfields->fields['effective_date_field'] ?? '2000-01-01'));
            if (!isset($csv_data['effective_date_field']['error']) && $csv_data['effective_date_field'] >= $dataLucRequieredMinimum) {
                $onclick .= sprintf("setGlpiDateField($(\"[name=effective_date_field]\").closest(\".flatpickr\"), \"%s\");", $csv_data['effective_date_field']);
            }

            $onclick .= 'return false;';

            return [
                'options' => [
                    'buttonClass' => 'submit',
                    'on_click' => $onclick,
                    'style' => $style,
                    'title' => $title,
                ],
                'value' => __('from', 'iservice') . ' CSV',
            ];
        }

        return [];
    }

    public function getEstimateButtonConfig($ticket, $closed, $printerId, $printer, $lastTicket, $lastClosedTicket, $colorPrinter, $plotterPrinter): array
    {
        if (empty($printer)) {
            return [];
        }

        if (!$closed && $printerId > 0 && !$printer->isRouter() && (empty($id) || ($lastTicket->customfields->fields['effective_date_field'] ?? '') <= $ticket->customfields->fields['effective_date_field']) && $lastClosedTicket->getID() > 0 && !$printer->customfields->fields['no_invoice_field']) {
            $lastDataLuc          = new DateTime($lastClosedTicket->customfields->fields['effective_date_field'] ?? '');
            $daysSinceLastCounter = $lastDataLuc->diff(new DateTime(empty($ticket->customfields->fields['effective_date_field']) ? null : $ticket->customfields->fields['effective_date_field']))->format("%a");
            $estimatedBlack       = $lastClosedTicket->customfields->fields['total2_black_field'] + $printer->customfields->fields['daily_bk_average_field'] * $daysSinceLastCounter;
            $estimatedColor       = $lastClosedTicket->customfields->fields['total2_color_field'] + $printer->customfields->fields['daily_color_average_field'] * $daysSinceLastCounter;
            $title                = "";
            $onclick              = '';
            if ($estimatedBlack > 0) {
                $title   .= "black: $estimatedBlack ({$lastClosedTicket->customfields->fields['total2_black_field']} + {$printer->customfields->fields['daily_bk_average_field']}*$daysSinceLastCounter)";
                $onclick .= "$(\"[name=total2_black_field]\").val($estimatedBlack);";
            }

            if (($colorPrinter || $plotterPrinter) && $estimatedColor > 0) {
                $title   .= ", " . ($plotterPrinter ? "suprafață hârtie" : "color") . ": $estimatedColor ({$lastClosedTicket->customfields->fields['total2_color_field']} + {$printer->customfields->fields['daily_color_average_field']}*$daysSinceLastCounter)";
                $onclick .= "$(\"[name=total2_color_field]\").val($estimatedColor);";
            }

            $onclick .= 'return false;';
            // Uncomment this line to see date explanation
            // $title .= sprintf(" [%s - %s]", date('Y-m-d', strtotime($ticket->fields['data_luc'])), date('Y-m-d', strtotime($last_closed_ticket->fields['data_luc'])));
            return [
                'options' => [
                    'buttonClass' => 'submit',
                    'on_click' => $onclick,
                    'title' => $title,
                ],
                'value' => __('Estimation', 'iservice'),
            ];
        }

        return [];
    }

    public function isClosed(): bool
    {
        return isset($this->fields['status']) && in_array($this->fields['status'], $this->getClosedStatusArray());
    }

    public function additionalGetFromDbSteps($ID = null): void
    {
        $this->fields['items_id']['Printer'] = array_column(PluginIserviceDB::getQueryResult("select it.items_id from glpi_items_tickets it where tickets_id = $ID and itemtype = 'Printer'"), 'items_id');
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

    public function addCartridge($ticketId, $input, &$errorMessage = ''): bool
    {
        $supplierId = $input['suppliers_id'] ?? $this->getFirstAssignedPartner()->getID();
        $printerId  = $input['printer_id'] ?? null;

        if (!$this->preCartridgeAddChecks($input, $supplierId, $printerId) || !$this->getFromDB($ticketId)) {
            return false;
        }

        $cartridgeitemsId   = $input['_plugin_iservice_cartridge']['cartridgeitems_id'] ?? '';
        $installDate        = $input['_cartridge_installation_date'] ?? '';
        $imposedCartridgeId = $input['_cartridge_id'] ?? null;

        $cartridgeItemData = explode('l', $cartridgeitemsId, 2);
        $cartridgeItemId   = $cartridgeItemData[0];
        $baseCondition     = "AND EXISTS (SELECT * FROM glpi_plugin_iservice_consumables_tickets WHERE amount > 0 AND new_cartridge_ids LIKE CONCAT('%|', glpi_plugin_iservice_cartridges.id, '|%'))";
        $locationCondition = 'AND (locations_id_field IS null OR locations_id_field < 1)';
        $printerCondition  = 'AND printers_id = 0 AND date_use IS null AND date_out IS null';
        $dateCondition     = empty($installDate) ? '' : "AND date_in <= '$installDate'";
        if (count($cartridgeItemData) > 1) {
            $cartridgeItemData = explode('p', $cartridgeItemData[1], 2);
            $locationCondition = "AND locations_id_field = $cartridgeItemData[0]";
            if (count($cartridgeItemData) > 1) {
                $printerCondition = "AND printers_id = $cartridgeItemData[1] AND date_out IS null";
            }
        }

        $cartridge             = new PluginIserviceCartridge();
        $cartridgeCustomfields = new PluginFieldsCartridgeitemcartridgeitemcustomfield();
        $cartridges            = $cartridge->find(["suppliers_id_field = $supplierId AND cartridgeitems_id = $cartridgeItemId $baseCondition $locationCondition $printerCondition $dateCondition"], ["id ASC"]);

        // First check the cartridges at the given partner. If there are none, check the partners in the same group.
        if (count($cartridges) === 0) {
            $cartridges = $cartridge->find(["FIND_IN_SET (suppliers_id_field, (SELECT group_field FROM glpi_plugin_fields_suppliersuppliercustomfields WHERE items_id = $supplierId)) AND cartridgeitems_id = $cartridgeItemId $locationCondition $printerCondition $dateCondition"], ["id ASC"]);
        }

        if (count($cartridges) === 0) {
            $errorMessage = "Stoc insuficient!";
            return false;
        }

        if (!empty($imposedCartridgeId)) {
            if (in_array($imposedCartridgeId, array_column($cartridges, 'id'))) {
                $cartridgeIdToInstall = $imposedCartridgeId;
            } else {
                $errorMessage = "Cartușul impus pentru instalare nu este instalabil pe acest aparat!";
                return false;
            }
        } else {
            $cartridgeIdToInstall = array_shift($cartridges)['id'];
        }

        $cartridge->getFromDB($cartridgeIdToInstall);
        PluginIserviceDB::populateByItemsId($cartridgeCustomfields, $cartridge->fields['cartridgeitems_id']);
        $cartridge->fields['printers_id']        = $printerId;
        $cartridge->fields['mercury_code_field'] = $cartridgeCustomfields->fields['mercury_code_field'];

        $pluginIserviceCartridgesTickets = new PluginIserviceCartridge_Ticket();

        $usedTypes = PluginIserviceDB::getQueryResult(
            "
            select ct.plugin_fields_cartridgeitemtypedropdowns_id selected_type
            from glpi_plugin_iservice_cartridges_tickets ct
            join glpi_cartridges c on c.id = ct.cartridges_id
            where ct.tickets_id = {$this->getID()}
              and c.cartridgeitems_id = {$cartridge->fields['cartridgeitems_id']}
            "
        );

        foreach (explode(',', $cartridgeCustomfields->fields['supported_types_field']) as $supportedType) {
            if (!in_array($supportedType, array_column($usedTypes, 'selected_type'))) {
                $cartridge->fields['plugin_fields_cartridgeitemtypedropdowns_id'] = $supportedType;
                break;
            }
        }

        $firstEmptiableCartridge = PluginIserviceCartridge::getFirstEmptiableByCartridge($cartridge);

        if (!$pluginIserviceCartridgesTickets->add(
            [
                'add'                                         => 'add',
                'tickets_id'                                  => $this->getID(),
                'cartridges_id'                               => $cartridgeIdToInstall,
                'plugin_fields_cartridgeitemtypedropdowns_id' => $cartridge->fields['plugin_fields_cartridgeitemtypedropdowns_id'],
                'cartridges_id_emptied'                       => empty($firstEmptiableCartridge[$cartridge->getIndexName()]) ? 'NULL' : $firstEmptiableCartridge[$cartridge->getIndexName()],
                '_no_message'                                 => true
            ]
        )
        ) {
            return false;
        }

        if (!$cartridge->update(['id' => $cartridgeIdToInstall, 'printers_id' => $printerId, '_no_message' => true])) {
            return  false;
        }

        return true;
    }

    public function preCartridgeAddChecks($post, $supplierId, $printerId): bool
    {
        if ((PluginIserviceTicket::getLastForPrinterOrSupplier($supplierId, $printerId, false)->customfields->fields['effective_date_field'] ?? '') > $post['effective_date_field']) {
            Session::addMessageAfterRedirect(__('You can not add new cartridges while there is a newer closed ticket.', 'iservice'), false, WARNING);
            return false;
        } elseif ((PluginIserviceTicket::getLastForPrinterOrSupplier($supplierId, $printerId, null, '', 'JOIN glpi_plugin_iservice_cartridges_tickets ct on ct.tickets_id = t.id')->customfields->fields['effective_date_field'] ?? '') > $post['effective_date_field']) {
            Session::addMessageAfterRedirect(__('You can not add new cartridges while there is a newer ticket with cartridges.', 'iservice'), false, WARNING);
            return false;
        }

        return !empty($post['_plugin_iservice_cartridge']['cartridgeitems_id']);
    }

    public function removeCartridge($ticketId, $input): bool
    {
        $this->check($ticketId, UPDATE);

        $supplierId = $input['suppliers_id'] ?? null;
        $printerId  = $input['printer_id'] ?? null;

        if (!$this->getFromDB($ticketId)) {
            return false;
        }

        $success = true;

        if ((PluginIserviceTicket::getLastForPrinterOrSupplier($supplierId, $printerId, false)->customfields->fields['effective_date_field'] ?? '') > $input['effective_date_field']) {
            $success = false;
            Session::addMessageAfterRedirect('Nu puteți șterge cartușe cât timp există un tichet închis mai nou', false, WARNING);
        } elseif ((PluginIserviceTicket::getLastForPrinterOrSupplier($supplierId, $printerId, null, '', 'JOIN glpi_plugin_iservice_cartridges_tickets ct on ct.tickets_id = t.id')->customfields->fields['effective_date_field'] ?? '') > $input['effective_date_field']) {
            $success = false;
            Session::addMessageAfterRedirect('Nu puteți șterge cartușe cât timp există un tichet mai nou cu cartușe.', false, WARNING);
        }

        if (!$success) {
            // Do nothing in particular if no success so far but check if there is a selected cartridge.
        } elseif (!empty($input['_plugin_iservice_cartridges_tickets']) && is_array($input['_plugin_iservice_cartridges_tickets'])) {
            $cartridge                       = new PluginIserviceCartridge();
            $pluginIserviceCartridgesTickets = new PluginIserviceCartridge_Ticket();
            foreach (array_keys($input['_plugin_iservice_cartridges_tickets']) as $idToDelete) {
                if ($input['_plugin_iservice_cartridges_tickets'][$idToDelete] === '0' || !$pluginIserviceCartridgesTickets->getFromDB($idToDelete)) {
                    continue;
                }

                $success &= $cartridge->update(
                    [
                        'id' => $pluginIserviceCartridgesTickets->fields['cartridges_id'],
                        'printers_id' => 0,
                        'date_use' => 'NULL',
                        'tickets_id_use' => 'NULL',
                        'date_out' => 'NULL',
                        'tickets_id_out' => 'NULL',
                        'pages_out_field' => 0,
                        'pages_color' => 0,
                        'pages_use' => 0,
                        'pages_color_use' => 0,
                    ]
                );
                $success &= $pluginIserviceCartridgesTickets->delete(['id' => $idToDelete]);
            }
        } else {
            Session::addMessageAfterRedirect(__('Select a cartridge', 'iservice'), false, ERROR);
        }

        return true;
    }

    public function updateCartridge($ticketId, $post): bool
    {
        $this->check($ticketId, UPDATE);

        $success                        = false;
        $pluginIserviceCartridgesTicket = new PluginIserviceCartridge_Ticket();
        foreach ($post['_plugin_iservice_cartridge_type_ids'] as $cartridgeTicketId => $cartridgeTypeId) {
            $emptyableCartridges = PluginIserviceCartridge::getEmptyablesByParams(
                $post['_plugin_iservice_cartridge_mercurycodes'][$cartridgeTicketId],
                $cartridgeTypeId,
                $post['printer_id'],
            );
            if (empty($post['_plugin_iservice_emptied_cartridge_ids'][$cartridgeTicketId]) || !in_array($post['_plugin_iservice_emptied_cartridge_ids'][$cartridgeTicketId], $emptyableCartridges)) {
                $emptied_id = count($emptyableCartridges) > 0 ? array_shift($emptyableCartridges)['id'] : 'NULL';
            } else {
                $emptied_id = $post['_plugin_iservice_emptied_cartridge_ids'][$cartridgeTicketId];
            }

            $success &= $pluginIserviceCartridgesTicket->update(
                [
                    'id' => $cartridgeTicketId,
                    'plugin_fields_cartridgeitemtypedropdowns_id' => $cartridgeTypeId,
                    'cartridges_id_emptied' => $emptied_id,
                ]
            );
        }

        return $success;
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

        $ticket_name = (isset($this->input['_no_message_link']) ? $this->getID() : $this->getLink());

        IserviceToolBox::clearAfterRedirectMessages(INFO);

        Session::addMessageAfterRedirect(sprintf(__('Ticket %s saved successfully.', 'iservice'), stripslashes($ticket_name)));
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

    public function updateItem($ticketId, $post, $add = false): void
    {
        if (!$add) {
            $this->check($ticketId, UPDATE, $post);
            $this->update($post);
        }

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
                        '_from_object' => true, // This is needed to avoid ticket status change in CommonITILActor.php:post_addItem() method, line 438.
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
        foreach (array_keys($input['items_id'] ?? []) as $itemtype) {
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

    public function post_addItem($history = 1): void
    {
        $this->postAddItem($history);
        $this->updateRelatedMovement($this->input['_services_invoiced'] ?? 0);
    }

    public function post_updateItem($history = 1): void
    {
        $this->postUpdateItem($history);
        $this->updateRelatedMovement($this->input['_services_invoiced'] ?? 0);
    }

    protected function updateRelatedMovement($servicesInvoiced): void
    {
        if (empty($servicesInvoiced)) {
            return;
        }

        if (0 === ($movementId = ($this->customfields->fields['movement_id_field'] ?? 0) ?: $this->customfields->fields['movement2_id_field'] ?? 0)) {
            return;
        }

        $movement = new PluginIserviceMovement();
        $movement->update(
            [
                'id' => $movementId,
                'invoice' => 1,
            ]
        );
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
        if (empty($printer) || $printer->getID() < 1) {
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

        return (new PluginIserviceTicketFollowup())->getTicketFollowupsData($ticketId);
    }

    public function setTicketUsersFields($ticketId, $options = []): void
    {
        $ticketUsers = (new Ticket_User())->getActors($ticketId);

        $this->fields['_users_id_assign']    = $options['_users_id_assign'] ?? $ticketUsers[CommonITILActor::ASSIGN][0]['users_id'] ?? '';
        $this->fields['_users_id_observer']  = $options['_users_id_observer'] ?? $ticketUsers[CommonITILActor::OBSERVER][0]['users_id'] ?? '';
        $this->fields['_users_id_requester'] = $optionsfields['_users_id_requester'] ?? $ticketUsers[CommonITILActor::REQUESTER][0]['users_id'] ?? '';

        if (empty($this->fields['_users_id_assign']) || $this->fields['_users_id_assign'] < 1) {
            $this->fields['_users_id_assign'] = empty($this->printer->fields['users_id_tech']) ? '' : $this->printer->fields['users_id_tech'];
        }
    }

    public static function preProcessPostData($post): array
    {
        if (isset($post['content'])) {
            $post['content'] = IserviceToolBox::clearNotAllowedTags($post['content']);
        }

        if (isset($post['_followup_content'])) {
            $post['_followup']['content'] = IserviceToolBox::clearNotAllowedTags($post['_followup_content']);
            unset($post['_followup_content']);
        }

        if (!isset($post['effective_date_field']) && (empty($post['id']) || $post['id'] < 0)
        ) {
            $post['effective_date_field'] = date('Y-m-d H:i:s');
        }

        if (isset($post['_cartridge_installation_date'])
            && !empty($post['cartridge_install_date_manually_changed'])
        ) {
            $post['cartridge_install_date_field'] = $post['_cartridge_installation_date'];
        }

        if (isset($post['_export_type'])) {
            $post['plugin_fields_ticketexporttypedropdowns_id'] = $post['_export_type'] === '' ? 0 : $post['_export_type'];
        }

        return $post;
    }

    public function createFollowup($id, $post)
    {
        if (isset($post['_followup']) && !empty($post['_followup']['content'])) {
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

    public function getPartnerId($options = []): ?int
    {
        $partnerId = $this->getID() > 0 ? $this->getFirstAssignedPartner()->getID() : null;

        if ($partnerId > 0) {
            return $partnerId;
        }

        $partnerId = $this->printer->fields['supplier_id'] ?? $options['partnerId'] ?? null;

        return intval($partnerId);
    }

    public function getPrinterId(): ?int
    {
        return $this->printer ? $this->printer->getID() : null;
    }

    public function addConsumable($ticketId, $post): void
    {
        if (!empty($post['_plugin_iservice_consumable']) && (!empty($post['_plugin_iservice_consumable']['plugin_iservice_consumables_id']) || !empty($post['_plugin_iservice_consumable']['plugin_iservice_cartridge_consumables_id']))) {
            $this->check($ticketId, UPDATE);

            if (empty($post['_plugin_iservice_consumable']['plugin_iservice_consumables_id'])) {
                $post['_plugin_iservice_consumable']['plugin_iservice_consumables_id'] = $post['_plugin_iservice_consumable']['plugin_iservice_cartridge_consumables_id'];
            }

            unset($post['_plugin_iservice_consumable']['plugin_iservice_cartridge_consumables_id']);

            $plugin_iservice_consumable_ticket_data                     = $post['_plugin_iservice_consumable'];
            $plugin_iservice_consumable_ticket_data['tickets_id']       = $ticketId;
            $create_cartridge                                           = in_array($plugin_iservice_consumable_ticket_data['plugin_iservice_consumables_id'], PluginIserviceConsumable_Ticket::getCompatibleCartridges(IserviceToolBox::getValueFromInput('suppliers_id', $post), IserviceToolBox::getValueFromInput('printer_id', $post)));
             $create_cartridge                                         &= self::isNotice(intval($post['_export_type'])) || $plugin_iservice_consumable_ticket_data['amount'] < 0;
            $plugin_iservice_consumable_ticket_data['create_cartridge'] = $create_cartridge;
            $cartridgeitem                                              = new PluginIserviceCartridgeItem();
            if ($cartridgeitem->getFromDBByRef($post['_plugin_iservice_consumable']['plugin_iservice_consumables_id'])) {
                $plugin_iservice_consumable_ticket_data['plugin_fields_typefielddropdowns_id'] = $cartridgeitem->getSupportedTypes()[0];
            }

            (new PluginIserviceConsumable_Ticket())->add($plugin_iservice_consumable_ticket_data);
        } else {
            Session::addMessageAfterRedirect('Selectați un consumabil / o piesă', false, ERROR);
        }
    }

    public static function isInvoice($value): bool
    {
        return self::EXPORT_TYPE_INVOICE_ID === $value;
    }

    public function isExportTypeInvoice(): bool
    {
        return self::isInvoice($this->fields['plugin_fields_ticketexporttypedropdowns_id'] ?? '');
    }

    public static function isNotice($value): bool
    {
        return self::EXPORT_TYPE_NOTICE_ID === $value;
    }

    public function isExportTypeNotice(): bool
    {
        return self::isNotice($this->fields['plugin_fields_ticketexporttypedropdowns_id'] ?? '');
    }

    public function removeConsumable($ticketId, $post): void
    {
        $this->check($ticketId, UPDATE);

        if (!empty($post['_plugin_iservice_consumables_tickets']) && is_array($post['_plugin_iservice_consumables_tickets'])) {
            $plugin_iservice_consumable_ticket = new PluginIserviceConsumable_Ticket();
            foreach (array_keys($post['_plugin_iservice_consumables_tickets']) as $id_to_delete) {
                $plugin_iservice_consumable_ticket->delete(['id' => $id_to_delete]);
            }
        } else {
            Session::addMessageAfterRedirect('Selectați un consumabil / o piesă', false, ERROR);
        }

    }

    public function updateConsumable($ticketId, $post): void
    {
        $this->check($ticketId, UPDATE);

        $success                   = $this->update($post);
        $consumable_prices         = explode('###', $this->customfields->fields['consumable_prices_field']);
        $consumable_prices_by_code = [];
        foreach ($consumable_prices as $consumable_price_row) {
            $consumable_price_data = explode(':', $consumable_price_row);
            if (count($consumable_price_data) > 1) {
                $consumable_prices_by_code[$consumable_price_data[0]] = $consumable_price_row;
            }
        }

        $plugin_iservice_consumable_ticket = new PluginIserviceConsumable_Ticket();
        foreach ($post['_plugin_iservice_consumable_amounts'] as $consumable_ticket_id => $amount) {
            $success &= $plugin_iservice_consumable_ticket->update(
                [
                    'id' => $consumable_ticket_id,
                    'amount' => $amount,
                    'plugin_fields_typefielddropdowns_id' => 0,
                    'create_cartridge' => $post['_plugin_iservice_consumable_create_cartridges'][$consumable_ticket_id],
                    'price' => $post['_plugin_iservice_consumable_prices'][$consumable_ticket_id],
                    'euro_price' => $post['_plugin_iservice_consumable_prices_in_euro'][$consumable_ticket_id] ? 1 : 0,
                    'locations_id' => $post['_plugin_iservice_consumable_locations'][$consumable_ticket_id],
                ]
            );
            if ($success && $post['_plugin_iservice_consumable_prices'][$consumable_ticket_id] != $post['_plugin_iservice_consumable_orig_prices'][$consumable_ticket_id]) {
                unset($consumable_prices_by_code[$post['_plugin_iservice_consumable_codes'][$consumable_ticket_id]]);
            }
        }

        $consumable_prices = implode('###', $consumable_prices_by_code);
        if ($this->customfields->fields['consumable_prices_field'] !== $consumable_prices) {
            $this->customfields->update(
                [
                    'id' => $this->customfields->getID(),
                    'consumable_prices_field' => $consumable_prices
                ]
            );
        }

    }

    public function updateEffectiveDate($post): array
    {
        // If ticket status is Ticket::SOLVED or Ticket::CLOSED, effective date should not change unless it was manually changed.
        // We presume that in such cases effective date is always set.
        if ((intval($post['status'] ?? null)) === Ticket::SOLVED || (intval($post['status'] ?? null)) === Ticket::CLOSED || !empty($post['effective_date_manually_changed'])) {
            return $post;
        }

        // Do not update if this is a cartridge or consumable handling operation.
        foreach (['add_cartridge', 'remove_cartridge', 'update_cartridge', 'add_consumable', 'remove_consumable', 'update_consumable'] as $operation) {
            if (isset($post[$operation])) {
                return $post;
            }
        }

        $lastTicket = self::getLastForPrinterOrSupplier($post['printer_id'], $post['suppliers_id']);

        if (empty($lastTicket->fields) || ($lastTicket->customfields->fields['effective_date_field'] ?? '') < $post['effective_date_field']) {
            $post['effective_date_field'] = date('Y-m-d H:i:s');
        }

        return $post;
    }

    public static function handleDeliveredStatusChange(PluginFieldsTicketticketcustomfield $item)
    {
        // This is used only to handle the consumables when changing the "delivered" checkbox.
        if (!in_array('delivered_field', $item->updates) || !array_key_exists('without_paper_field', $item->fields)) {
            return;
        }

        global $DB;

        $ticket_id = $item->fields['items_id'];
        $supplier  = PluginIserviceTicket::get($ticket_id)->getFirstAssignedPartner();
        if (!$supplier->hasCartridgeManagement()) {
            return;
        }

        $cartridge         = new PluginIserviceCartridge();
        $ticket            = new PluginIserviceTicket();
        $consumable_ticket = new PluginIserviceConsumable_Ticket();

        if (($consumables = $consumable_ticket->find(['tickets_id' => $ticket_id])) == [] || !$ticket->getFromDB($ticket_id)) {
            return;
        }

        if (($compatible_cartridges = PluginIserviceCartridgeItem::getCompatiblesForTicket($ticket)) == []) {
            return;
        }

        foreach ($consumables as $consumable) {
            if (!$consumable['create_cartridge'] || false === ($index = array_search($consumable['plugin_iservice_consumables_id'], array_column($compatible_cartridges, 'ref', 'id')))) {
                continue;
            }

            $add_cartridges = null;
            if ($consumable['amount'] > 0) {
                if ($item->input['delivered_field']) {
                    $add_cartridges = true;
                } elseif (!empty($consumable['new_cartridge_ids'])) {
                    $add_cartridges = false;
                }
            } elseif ($consumable['amount'] < 0) {
                if (!$item->input['delivered_field']) {
                    $add_cartridges = true;
                } elseif (!empty($consumable['new_cartridge_ids'])) {
                    $add_cartridges = false;
                }
            }

            if ($add_cartridges) {
                $new_cartridge_ids = str_replace('|', '', $consumable['new_cartridge_ids']) ?: [];
                if (!empty($new_cartridge_ids)) {
                    $DB->queryOrDie("UPDATE glpi_cartridges SET date_out = null WHERE id IN ($new_cartridge_ids)", __('Error restoring cartridges', 'iservice'));
                    $DB->queryOrDie("UPDATE glpi_plugin_fields_cartridgecartridgecustomfields SET tickets_id_out_field = null WHERE items_id IN ($new_cartridge_ids) AND itemtype='Cartridge'", __('Error restoring cartridges custom fields', 'iservice'));
                } else {
                    for ($i = 0; $i < abs($consumable['amount']); $i++) {
                        $new_cartridge_ids[] = $cartridge->add(
                            [
                                'add'                => 'add',
                                'cartridgeitems_id'  => $compatible_cartridges[$index]['id'],
                                'suppliers_id_field' => $supplier->getID(),
                                'locations_id_field' => empty($consumable['locations_id']) ? '0' : $consumable['locations_id'],
                            ]
                        );
                        // Due to glpi Cartridge adding code, we have to update FK_enterprise, FK_location and date_in in a separate process.
                        $cartridge->update(
                            [
                                'id'                                          => $new_cartridge_ids[count($new_cartridge_ids) - 1],
                                'plugin_fields_cartridgeitemtypedropdowns_id' => $consumable['plugin_fields_cartridgeitemtypedropdowns_id'],
                                'date_in'                                     => $ticket->customfields->fields['effective_date_field'],
                            ]
                        );
                    }

                    $consumable_ticket->update(
                        [
                            'id'                => $consumable['id'],
                            'new_cartridge_ids' => '|' . implode('|,|', $new_cartridge_ids) . '|',
                        ]
                    );
                }
            } elseif ($add_cartridges !== null) {
                $ids_to_revoke     = str_replace('|', '', $consumable['new_cartridge_ids']);
                $installer_tickets = PluginIserviceDB::getQueryResult("select * from glpi_plugin_iservice_cartridges_tickets ct where ct.cartridges_id in ($ids_to_revoke)");
                if ($installer_tickets) {
                    foreach ($installer_tickets as $installer_ticket) {
                        Session::addMessageAfterRedirect("Cartușul $installer_ticket[cartridges_id] nu poate fi retras deoarece tichetul $installer_ticket[tickets_id] îl instalează.", false, ERROR);
                    }

                    $DB->queryOrDie("UPDATE glpi_plugin_fields_ticketticketcustomfields SET delivered_field = " . ($item->input['deliveredfield'] ? 0 : 1) . " WHERE itemtype = 'Ticket' and items_id = $ticket_id", "Error reverting delivered state");
                } else {
                    if ($item->input['delivered_field']) {
                        $DB->queryOrDie("UPDATE glpi_cartridges SET date_out = '{$ticket->customfields->fields['effective_date_field']}'WHERE id IN ($ids_to_revoke)", __('Error deleting cartridges', 'iservice'));
                        $DB->queryOrDie("UPDATE glpi_plugin_fields_cartridgecartridgecustomfields SET tickets_id_out_field = {$ticket->fields['id']} WHERE items_id IN ($ids_to_revoke) AND itemtype = 'Cartridge'", __('Error deleting cartridges custom fields', 'iservice'));
                    } else {
                        $consumable_ticket->update(
                            [
                                'id'                => $consumable['id'],
                                'new_cartridge_ids' => 'NULL',
                            ]
                        );
                        $DB->queryOrDie("DELETE FROM glpi_cartridges WHERE id IN ($ids_to_revoke)", __('Error deleting cartridges', 'iservice'));
                        $DB->queryOrDie("DELETE FROM glpi_plugin_fields_cartridgecartridgecustomfields WHERE items_id IN ($ids_to_revoke) AND itemtype = 'Cartridge'", __('Error deleting cartridges custom fields', 'iservice'));
                        $DB->queryOrDie("DELETE FROM glpi_infocoms WHERE items_id IN ($ids_to_revoke) AND itemtype = 'Cartridge'", __('Error deleting cartridges infocoms', 'iservice'));
                    }
                }
            }
        }
    }

    public function hasConsumables(): bool
    {
        $consumableTicket = new PluginIserviceConsumable_Ticket();
        return $consumableTicket->find(['tickets_id' => $this->getID()]) !== [];
    }

    public function isCloseable(): bool
    {
        return !$this->hasConsumables() || (!empty($this->customfields->fields['delivered_field']) && !empty($this->customfields->fields['exported_field']));
    }

    public function getButtonsConfig($ID, $options, $movement = null): array
    {
        if (IserviceToolBox::inProfileArray(['client', 'superclient'])) {
            return [];
        }

        $close_confirm_message = '';
        $available_cartridges  = PluginIserviceCartridgeItem::getChangeablesForTicket($this);

        foreach (array_column($available_cartridges, 'ref') as $available_cartridge_id) {
            if (substr($available_cartridge_id, 0, 4) !== 'CTON' && substr($available_cartridge_id, 0, 2) !== 'CC') {
                $close_confirm_message .= "- $available_cartridge_id\n";
            }
        }

        if (!empty($close_confirm_message)) {
            $close_confirm_message = "Există consumabile instalabile dar neinstalate la client:\n$close_confirm_message\nSigur vreți să închideți tichetul?";
        }

        $buttons = [];
        $closed  = $this->isClosed();
        if ($ID > 0) {
            if ($closed && IserviceToolBox::inProfileArray(['super-admin'])) {
                $newer_closed_ticket_ids = self::getNewerClosedTikcetIds($this->getID(), $this->customfields->fields['effective_date_field'], $this->getPartnerId(), $this->getPrinterId());
                if (count($newer_closed_ticket_ids)) {
                    $confirm = count($newer_closed_ticket_ids) . __(" closed tickets will be reopened. Are you sure you want to continue?", 'iservice');
                } else {
                    $confirm = __('Are you sure you want to reopen the ticket?', 'iservice');
                }

                $buttons['reopen'] = [
                    'type'    => 'submit',
                    'name'    => 'update',
                    'label'   => __('Reopen', 'iservice'),
                    'value'   => 1,
                    'options' => [
                        'on_click' => 'if (confirm("' . $confirm . '")) {
                            $("[name=status]").val(' . Ticket::SOLVED . '); 
                            } else {this.stopPropagation()}'
                    ],
                ];
            } else {
                if ($this->isCloseable()) {
                    $buttons['close'] = [
                        'type'    => 'submit',
                        'name'    => 'update',
                        'label'   => __('Close', 'iservice'),
                        'value'   => 1,
                        'options' => [
                            'data-confirm-message' => $close_confirm_message,
                            'on_click'             => '$("[name=status]").val(' . Ticket::CLOSED . ');'
                        ],
                    ];
                }

                $button_statuses = [Ticket::SOLVED, Ticket::WAITING, Ticket::PLANNED, Ticket::ASSIGNED];
                if (in_array($_SESSION["glpiactiveprofile"]["name"], ['super-admin'])) {
                    $button_statuses[] = Ticket::INCOMING;
                }

                $statusClassMap = [
                    Ticket::SOLVED   => 'far fa-circle solved',
                    Ticket::WAITING  => 'fas fa-circle waiting',
                    Ticket::PLANNED  => 'far fa-calendar planned',
                    Ticket::ASSIGNED => 'far fa-circle assigned',
                    Ticket::INCOMING => 'fas fa-circle new',
                ];

                foreach ($button_statuses as $status) {
                    $confirm_alert    = ($status === Ticket::SOLVED || $this->fields['status'] != Ticket::SOLVED) ? '' : "if (!confirm(\"ATENȚIE! Schimbând starea tichetului, data efectivă va deveni data curentă în loc de {$this->customfields->fields['effective_date_field']}!\")) return false;";
                    $buttons[$status] = [
                        'type'    => 'submit',
                        'name'    => 'update',
                        'label'   => '',
                        'value'   => 1,
                        'options' => [
                            'buttonClass' => "itilstatus  $statusClassMap[$status]",
                            'title'       => Ticket::getStatus($status),
                            'on_click'    => "$confirm_alert$(\"[name=status]\").val($status);"
                        ],
                    ];
                }

                $exportButtonOptions = [
                    'onclick'    => 'if ($(this).hasClass("disabled")) { return false; }',
                    'data-title' => 'Ticketul nu poate fi exportat până livrarea nu este finalizată',
                ];
                if (empty($this->customfields->fields['delivered_field'])) {
                    $exportButtonOptions['buttonClass'] = 'submit disabled';
                    $exportButtonOptions['title']       = 'Ticketul nu poate fi exportat până livrarea nu este finalizată';
                }

                if (!empty($options['ticketHasConsumables'])) {
                    $buttons['export'] = [
                        'type'    => 'submit',
                        'name'    => 'export',
                        'value'   => __('Save') . ' + ' . __('hMarfa export', 'iservice'),
                        'options' => $exportButtonOptions,
                    ];
                }
            }
        }

        // If it is a movement ticket, it cannot be closed until the services invoice is not created.
        if (!empty($this->customfields->fields['movement_id_field']) || !empty($this->customfields->fields['movement2_id_field'])) {
            unset($buttons['services_export']);
            if (!empty($this->customfields->fields['movement_id_field']) && empty($movement->fields['invoice'])) {
                unset($buttons['close']);
            }
        }

        return $buttons;
    }

    public static function moveCartridges(Ticket $item): void
    {
        if ($item instanceof PluginIserviceTicket) {
            $ticket = $item;
        } else {
            $ticket = new PluginIserviceTicket();
            $ticket->getFromDB($item->getID());
        }

        if (PluginIserviceTicket::wasTicketClosing($item)) {
            $operation      = 'installWithType';
            $ticket->fields = $item->input;
        } else {
            $operation      = 'uninstallWithType';
            $ticket->fields = PluginIserviceTicket::preProcessPostData($_POST);
        }

        $cartridge_ticket = new PluginIserviceCartridge_Ticket();
        foreach ($cartridge_ticket->find(["tickets_id" => $item->getID()]) as $cartridge_item) {
            $install_result = PluginIserviceCartridge_Ticket::$operation(
                $item->getID(),
                $cartridge_item['cartridges_id'],
                $cartridge_item['plugin_fields_cartridgeitemtypedropdowns_id'],
                $cartridge_item['cartridges_id_emptied'],
                $ticket->getPrinterId(),
                $ticket->getPartnerId(),
                $ticket->fields['locations_id'] ?? null,
                $ticket->customfields->fields['total2_black_field'] ?? null,
                $ticket->customfields->fields['total2_color_field'] ?? null,
                $ticket->customfields->fields['cartridge_install_date_field'] ?? $ticket->customfields->fields['effective_date_field']
            );
            if (abs($install_result) != $cartridge_item['cartridges_id']) {
                Session::addMessageAfterRedirect($install_result, false, ERROR);
            } else {
                $cartridge_ticket->update(
                    [
                        'id' => $cartridge_item->fields['id'],
                        'plugin_fields_cartridgeitemtypedropdowns_id' => $cartridge_item['plugin_fields_cartridgeitemtypedropdowns_id'],
                    ]
                );
            }
        }
    }

    public function processFieldsByInput()
    {
        $result = [];

        $printer_id = IserviceToolBox::getInputVariable('printer_id');
        $printer    = new PluginIservicePrinter();
        if (empty($printer_id) && $this->getID() > 0) {
            $printer = $this->getFirstPrinter();
            if (!$printer->isNewItem()) {
                $printer_id = $printer->getID();
            }
        } else {
            $printer->getFromDB($printer_id);
        }

        $result['variables']['printer']    = $printer;
        $result['variables']['printer_id'] = $printer_id;

        $this->fields['_suppliers_id_assign'] = $supplier_id = IserviceToolBox::getInputVariable('suppliers_id', $this->fields['_suppliers_id_assign'] ?? '');
        if (empty($supplier_id) && !empty($printer_id)) {
            $infocom = new Infocom();
            if ($infocom->getFromDBforDevice('Printer', $printer_id)) {
                $this->fields['_suppliers_id_assign'] = $supplier_id = $infocom->fields['suppliers_id'];
            }
        }

        $result['variables']['supplier_id'] = $supplier_id;

        return $result;
    }

    public static function createGlobalReadCounterTickets(array $data): ?int
    {
        if (false !== ($success = isset($data['printer']) && is_array($data['printer']))) {
            $ticket_count = 0;
            foreach ($data['printer'] as $printerId => $ticketData) {
                if ($ticketData['effective_date_field'] < $ticketData['effective_date_old']
                    || (intval($ticketData['total2_black_field']) < intval($ticketData['total2_black_old']))
                    || (intval($ticketData['total2_color_field']) < intval($ticketData['total2_color_old']))
                    || (intval($ticketData['total2_black_field']) + intval($ticketData['total2_color_field']) < intval($ticketData['total2_black_old']) + intval($ticketData['total2_color_old']) + 10)
                ) {
                    continue;
                }

                $track = new PluginIserviceTicket();
                PluginIserviceTicket::prepareDataForGlobalReadCounter($ticketData);
                $track->explodeArrayFields();
                $last_opened_ticket = PluginIserviceTicket::getLastForPrinterOrSupplier(0, $printerId, true);
                if ($last_opened_ticket->getID() > 0 && $last_opened_ticket->customfields->fields['effective_date_field'] < $ticketData['effective_date_field']) {
                    $ticketData['_dont_close'] = true;
                }

                if (!empty($ticketData['_dont_close'])) {
                    $track->fields['status'] = Ticket::SOLVED;
                    unset($ticketData['_dont_close']);
                }

                if (in_array($_SESSION["glpiactiveprofile"]["name"], ['tehnician', 'admin', 'super-admin'])) {
                    $ticketData['_users_id_assign'] = $_SESSION['glpiID'];
                }

                if ($track->add(array_merge($track->fields, $ticketData, ['add' => 'add', '_no_message' => 1]))) {
                    $ticket_count++;
                } else {
                    $success = false;
                }
            }

            return $success ? $ticket_count : -$ticket_count;
        }

        return 0;
    }

    private static function prepareDataForGlobalReadCounter(&$ticketData): void
    {
        $ticketData = array_merge(
            $ticketData,
            [
                'name'                => 'Citire contor eMaintenance',
                'content'             => 'Periodic',
                'status'              => IserviceToolBox::inProfileArray(['tehnician', 'admin', 'super-admin']) ? parent::CLOSED : parent::SOLVED,
                'without_paper_field' => 1,
                'no_travel_field'     => 1,
            ]
        );
    }

    public function prepareDataForMovement($values)
    {
        $this->originalFields = $this->fields;

        $this->fields['movement_id_field']                          = $values['_movement_id'] ?? null;
        $this->fields['movement2_id_field']                         = $values['_movement2_id'] ?? null;
        $this->fields['plugin_fields_ticketexporttypedropdowns_id'] = $values['_export_type'] ?? null;

        foreach ($values as $key => $val) {
            if (!isset($this->fields[$key])) {
                $this->fields[$key] = $val;
            }
        }

    }

    private function getMovementRelatedData($id, $printerId, $canEdit): ?array
    {
        $fields = [
            '_movement_id' => [
                'render' => false,
                'type' => 'hidden',
                'name' => 'movement_id_field',
                'options' => [
                    'no_label' => true,
                ],
            ],
            '_movement2_id' => [
                'render' => false,
                'type' => 'hidden',
                'name' => 'movement2_id_field',
                'options' => [
                    'no_label' => true,
                ],
            ],
            '_services_invoiced' => [
                'render' => false,
                'type' => 'checkboxExtended' ,
                'label' => __('Services invoice', 'iservice'),
                'name' => '_services_invoiced',
                'disabled' => !$canEdit,
            ],
        ];

        $movement_id  = $this->customfields->fields['movement_id_field'] ?? IserviceToolBox::getInputVariable('_movement_id');
        $movement2_id = $this->customfields->fields['movement2_id_field'] ?? IserviceToolBox::getInputVariable('_movement2_id');

        if (empty($movement_id) && empty($movement2_id)) {
            return [];
        }

        if (empty($id) && ($movement = PluginIserviceMovement::getOpenFor('Printer', $printerId)) !== false && empty($movement_id) && empty($movement2_id)) {
            Html::displayErrorAndDie("<a href='movement.form.php?id=$movement' target='_blank'>" . sprintf(__('There is an unfinished movement for this printer, please finish movement %s first!', 'iservice'), $movement) . "</a>");
        } else {
            $movement = new PluginIserviceMovement();
            $movement->getFromDB($movement_id ?: $movement2_id ?: -1);
        }

        $customFields = new PluginFieldsTicketticketcustomfield();
        if (PluginIserviceDB::populateByQuery($customFields, "WHERE movement_id_field = " . IserviceToolBox::getInputVariable('_movement_id', -2) . " LIMIT 1")) {
            Html::displayErrorAndDie(sprintf(__("Ticket already exists for movement %d", "iservice"), IserviceToolBox::getInputVariable('_movement_id')));
        }

        if (PluginIserviceDB::populateByQuery($customFields, "WHERE movement2_id_field = " . IserviceToolBox::getInputVariable('_movement2_id', -2) . " LIMIT 1")) {
            Html::displayErrorAndDie(sprintf(__("Ticket already exists for movement %d", "iservice"), IserviceToolBox::getInputVariable('_movement2_id')));
        }

        $fields['_movement_id']['value']   = $movement_id;
        $fields['_movement_id']['render']  = true;
        $fields['_movement2_id']['value']  = $movement2_id;
        $fields['_movement2_id']['render'] = true;

        // Services invoiced.
        global $CFG_PLUGIN_ISERVICE;
        $fields['_services_invoiced']['render'] = true;
        $fields['_services_invoiced']['value']  = $movement->fields['invoice'] ?? false;

        if (!$fields['_services_invoiced']['value']) {
            $fields['_services_invoiced']['options']['label2raw'] = "<div class='ms-2'><span class='text-danger'>" . __('Do not check before invoice is issued, operation can not be undone!', "iservice") . "</span> " . __('To create an invoice, press the link', "iservice") . " <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/hmarfaexport.form.php?mode=3&kcsrft=1&item[printer][{$printerId}]=1' target='_blank'>" . __("invoicing", "iservice") . "</a></div>";
        }

        $fields['_services_invoiced']['disabled'] = !$canEdit || $fields['_services_invoiced']['value'];

        return [
            'fields' => $fields,
            'movement' => $movement ?? null,
        ];

    }

}

<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

class PluginIservicePrinter extends Printer
{

    use PluginIserviceItem;

    const ID_COLOR_TYPE   = 2;
    const ID_ROUTER_TYPE  = 22;
    const ID_PLOTTER_TYPE = 20;

    /*
     * @var PluginFieldsPrinterprintercustomfield
     */
    public $customfields = null;

    public static $customFieldsModelName = 'PluginFieldsPrinterprintercustomfield';
    /*
     * @var bool
     */

    protected $has_cartridge_management = null;
    /*
     * @var PluginIserviceTicket
     */

    protected $last_ticket = null;
    /*
     * @var PluginIserviceTicket
     */

    protected $last_closed_ticket = null;

    public static function getTypeName($nb = 0): string
    {
        return __('Manage printer', 'iservice');
    }

    public function isColor(): bool
    {
        return !$this->isNewItem() && $this->fields['printertypes_id'] == self::ID_COLOR_TYPE;
    }

    public function isRouter(): bool
    {
        return !$this->isNewItem() && $this->fields['printertypes_id'] == self::ID_ROUTER_TYPE;
    }

    public function isPlotter(): bool
    {
        return !$this->isNewItem() && $this->fields['printertypes_id'] == self::ID_PLOTTER_TYPE;
    }

    public function hasCartridgeManagement(): bool
    {
        if ($this->has_cartridge_management === null) {
            $infocom              = new Infocom();
            $supplier_customfield = new PluginFieldsSuppliersuppliercustomfield();
            if ($this->isNewItem() || !$infocom->getFromDBforDevice('Printer', $this->getID()) || !PluginIserviceDB::populateByItemsId($supplier_customfield, $infocom->fields['suppliers_id'])) {
                return false;
            }

            $this->has_cartridge_management = !empty($supplier_customfield->fields['cm_field']);
        }

        return $this->has_cartridge_management;
    }

    public function lastTicket(): PluginIserviceTicket
    {
        if ($this->isNewItem()) {
            return new PluginIserviceTicket();
        }

        if ($this->last_ticket === null) {
            $last_ticket_ids   = PluginIserviceDB::getQueryResult("select tickets_id from glpi_plugin_iservice_printers_last_tickets where printers_id = {$this->getID()}");
            $last_ticket_ids   = array_column($last_ticket_ids, 'tickets_id');
            $this->last_ticket = new PluginIserviceTicket();
            $this->last_ticket->getFromDB(array_pop($last_ticket_ids));
        }

        return $this->last_ticket;
    }

    public function lastClosedTicket(): PluginIserviceTicket
    {
        if ($this->isNewItem()) {
            return new PluginIserviceTicket();
        }

        if ($this->last_closed_ticket === null) {
            $last_closed_ticket_ids   = PluginIserviceDB::getQueryResult("select tickets_id from glpi_plugin_iservice_printers_last_closed_tickets where printers_id = {$this->getID()}");
            $last_closed_ticket_ids   = array_column($last_closed_ticket_ids, 'tickets_id');
            $this->last_closed_ticket = new PluginIserviceTicket();
            $this->last_closed_ticket->getFromDB(array_pop($last_closed_ticket_ids));
        }

        return $this->last_closed_ticket;
    }

    public static function getAccessibleIds(): ?array
    {
        if (!Session::haveRight('plugin_iservice_ticket_all_printers', READ)) {
            global $DB;
            $printer_conditions     = [];
            $accessible_printer_ids = [];
            if (Session::haveRight('plugin_iservice_ticket_own_printers', READ)) {
                $printer_conditions[] = "users_id = " . $_SESSION['glpiID'];
            }

            if (Session::haveRight('plugin_iservice_ticket_assigned_printers', READ)) {
                $printer_conditions[] = "users_id_tech = " . $_SESSION['glpiID'];
            }

            if (Session::haveRight('plugin_iservice_ticket_group_printers', READ) && is_array($_SESSION['glpigroups']) && count($_SESSION['glpigroups']) > 0) {
                $printer_conditions[] = "groups_id IN (" . join(',', $_SESSION['glpigroups']) . ")";
            }

            if (count($printer_conditions) < 1) {
                $printer_conditions = ["1=2"];
            }

            if (($result = $DB->query("SELECT id FROM glpi_plugin_iservice_printers	WHERE (" . join(' OR ', $printer_conditions) . ")")) === false) {
                echo $DB->error();
                die();
            }

            while (($row = $DB->fetchAssoc($result)) !== null) {
                $accessible_printer_ids[] = $row['id'];
            }

            return $accessible_printer_ids ?: [0];
        } else {
            return null;
        }
    }

    public function showForm($printer_id = null, $supplier_id = null, $contract_id = null): bool
    {
        global $CFG_GLPI;

        $printer               = new Printer();
        $printer_customfields  = new PluginFieldsPrinterprintercustomfield();
        $supplier              = new Supplier();
        $supplier_customfields = new PluginFieldsSuppliersuppliercustomfield();
        $contract              = new PluginIserviceContract();
        $contract_customfields = new PluginFieldsContractcontractcustomfield();

        $accessible_printer_ids = self::getAccessibleIds();

        if (!empty($contract_id)) {
            if (!$contract->getFromDB($contract_id) || !PluginIserviceDB::populateByItemsId($contract_customfields, $contract_id)) {
                echo "Contract $contract_id ";
                Html::displayNotFoundError();
            } else {
                $printer  = null;
                $supplier = null;
            }
        } elseif (!empty($supplier_id)) {
            if (!$supplier->getFromDB($supplier_id) || !PluginIserviceDB::populateByItemsId($supplier_customfields, $supplier_id)) {
                echo "Partener $supplier_id ";
                Html::displayNotFoundError();
            } else {
                $printer  = null;
                $contract = null;
            }
        } elseif (!empty($printer_id)) {
            if (!$printer->getFromDB($printer_id)) {
                echo "Aparat $printer_id ";
                Html::displayNotFoundError();
            } else {
                if ($accessible_printer_ids !== null && !in_array($printer_id, $accessible_printer_ids)) {
                    Html::displayRightError();
                }

                PluginIserviceDB::populateByItemsId($printer_customfields, $printer_id);
                // Supplier data.
                $infocom = new Infocom();
                if (!$infocom->getFromDBforDevice('Printer', $printer_id)) {
                    $supplier->getEmpty();
                    $supplier_customfields->getEmpty();
                } else {
                    if (!$supplier->getFromDB($infocom->fields['suppliers_id']) || !PluginIserviceDB::populateByItemsId($supplier_customfields, $supplier->getID())) {
                        $supplier_customfields->getEmpty();
                    }
                }

                // Contract data.
                $contract_item = new Contract_Item();
                if (!PluginIserviceDB::populateByItemsId($contract_item, $printer_id, 'Printer')) {
                    $contract->getEmpty();
                    $contract_customfields->getEmpty();
                } else {
                    if (!$contract->getFromDB($contract_item->fields['contracts_id']) || !PluginIserviceDB::populateByItemsId($contract_customfields, $contract->getID())) {
                        $contract_customfields->getEmpty();
                    }
                }
            }
        } else {
            $printer->getEmpty();
            $supplier->getEmpty();
            $supplier_customfields->getEmpty();
            $contract->getEmpty();
            $contract_customfields->getEmpty();
        }

        if (!$printer->isNewItem()) {
            $this->showButtons($printer_id, $printer, $printer_customfields, $supplier);
        }

        echo '<script>
		function adjustPrinterChangeButtons(same_value, selector1, selector2) {
			if (same_value) {
				$(selector1).hide();
				$(selector2).show();
			} else {
				$(selector1).show();
				$(selector2).hide();
			}
		}
		function redirect_to(redirect_type) {
			$input = $("input[name=\'" + redirect_type + "[id]\']");
			if ($input.val() !== undefined) {
				document.location="?" + redirect_type + "_id=" + $input.val();
				return;
			}
			$select = $("select[name=\'" + redirect_type + "[id]\']");
			document.location="?" + redirect_type + "_id=" + $select.val();
		}
		</script>';

        echo "<form class='iservice-form printer' method='post' name='form_printer' enctype='multipart/form-data'>";

        echo "<table class='tab_cadre_fixe wide iservice_printer_table' id='mainformtable'>";

        echo "<tr class='headerRow'>";
        echo "<th class=text-center '>" . __('Printer', 'iservice') . "</th>";
        echo "<th class='bg-white text-center'>" . __('Supplier') . "</th>";
        if (Session::haveRight('plugin_iservice_printer_full', UPDATE)) {
            echo "<th  class='text-center'>" . __('Contract') . "</th>";
        }

        echo "</tr>";

        echo "<tr>";
        echo "<td>" . $this->generatePrinterData($printer, $accessible_printer_ids, !Session::haveRight('plugin_iservice_printer', UPDATE)) . "</td>";
        echo "<td class='bg-white'>" . $this->generateSupplierData($printer, $supplier, $supplier_customfields, !Session::haveRight('plugin_iservice_printer_full', UPDATE)) . "</td>";
        if (Session::haveRight('plugin_iservice_printer_full', UPDATE)) {
            echo "<td>" . $this->generateContractData($printer, $contract, $contract_customfields, false) . "</td>";
        }

        echo "</tr>";

        echo "<tr class='buttons'>";
        echo "<td class='text-center'>" . $this->generatePrinterButtons($printer, !Session::haveRight('plugin_iservice_printer', UPDATE)) . "</td>";
        echo "<td class='bg-white text-center'>" . $this->generateSupplierButtons($printer, $supplier, $supplier_customfields, !Session::haveRight('plugin_iservice_printer_full', UPDATE)) . "</td>";
        if (Session::haveRight('plugin_iservice_printer_full', UPDATE)) {
            echo "<td class='text-center'>" . $this->generateContractButtons($printer, $contract, $contract_customfields, !Session::haveRight('plugin_iservice_contract', UPDATE)) . "</td>";
        }

        echo "</tr>";

        echo "</table>";

        Html::closeForm();

        return true;
    }

    private function showButtons($printer_id, $printer, $printer_customfields, $supplier)
    {
        global $CFG_GLPI;

        echo "<div style='text-align: left;'>";
        $buttons = [];
        if (Session::haveRight('plugin_iservice_ticket_' . PluginIserviceTicket::MODE_CREATENORMAL, CREATE)) {
            $buttons[] = "<a class='vsubmit' href='ticket.form.php?mode=" . PluginIserviceTicket::MODE_CREATENORMAL . "&items_id[Printer][0]=$printer_id&_users_id_assign={$printer->fields['users_id_tech']}'>" . __('New ticket') . "</a>";
        }

        if (Session::haveRight('plugin_iservice_ticket_' . PluginIserviceTicket::MODE_CREATEQUICK, CREATE)) {
            $buttons[] = "<a class='vsubmit' href='ticket.form.php?mode=" . PluginIserviceTicket::MODE_CREATEQUICK . "&items_id[Printer][0]=$printer_id&_users_id_assign={$printer->fields['users_id_tech']}'>" . __('New quick ticket', 'iservice') . "</a>";
        }

        if (Session::haveRight('plugin_iservice_hmarfa', READ)) {
            $buttons[] = "<a class='vsubmit' href='" . $CFG_GLPI['root_doc'] . "/plugins/iservice/front/hmarfaexport.form.php?item[printer][$printer_id]=1&mode=3&kcsrft=1'>" . __('hMarfa export', 'iservice') . "</a>";
        }

        $supplierName       = $supplier->fields['name'] ?? '';
        if (Session::haveRight('plugin_iservice_view_operations', READ)) {
            $usageAddress       = $printer_customfields->fields['usage_address_field'] ?? '';
            $filter_description = urlencode("{$printer->fields['name']} ({$printer->fields['serial']}) - {$usageAddress} - {$supplierName}");
            $buttons[]          = "<a class='vsubmit' href='views.php?view=Operations&operations0[printer_id]=$printer_id&operations0[filter_description]=$filter_description'>" . __('Operations list', 'iservice') . "</a>";
        }

        if (Session::haveRight('plugin_iservice_movement', CREATE)) {
            $buttons[] = "<a class='vsubmit' href='movement.form.php?itemtype=Printer&items_id=$printer_id'>" . __('Move', 'iservice') . " " . __("Printer", "iservice") . "</a>";
        }

        if (Session::haveRight('plugin_iservice_view_printers', READ) && !empty($supplierName)) {
            $buttons[] = "<a class='vsubmit' href='views.php?view=Printers&printers0[supplier_name]=$supplierName' target='_blank' title=\"" . __('Printers of the client', 'iservice') . "\">" . __('Client printers', 'iservice') . "</a>";
        }

        echo implode('&nbsp;&nbsp;', $buttons);
        echo "</div>";
        echo "<br>";
    }

    public function generatePrinterData($printer, $accessible_printer_ids, $readonly)
    {
        if ($printer === null) {
            $readonly = true;
            $printer  = new Printer();
            $printer->getEmpty();
        }

        $has_full_rights = Session::haveRight('plugin_iservice_printer_full', UPDATE);

        $output = "<table class='two-column' style='width:100%;'>";

        $printer_customfields = new PluginFieldsPrinterprintercustomfield();
        if (PluginIserviceDB::populateByItemsId($printer_customfields, $printer->getID()) === false) {
            $printer_customfields->getEmpty();
        }

        $form            = new PluginIserviceHtml();
        $no_wrap_options = ['field_class' => 'nowrap'];

        $post_data = filter_input_array(INPUT_POST);
        if (isset($post_data['printer'])) {
            foreach ($post_data['printer'] as $field_name => $field_value) {
                $printer->fields[$field_name] = $field_value;
            }
        }

        if (isset($post_data['_customfields']['printer'])) {
            foreach ($post_data['_customfields']['printer'] as $field_name => $field_value) {
                $printer_customfields->fields[$field_name] = $field_value;
            }
        }

        // Selector.
        $selector_options['type']                 = 'PluginIservicePrinter';
        $selector_options['options']['on_change'] = "document.location='?id=' + $(\"[name='printer[id]']\").val();";
        if ($accessible_printer_ids !== null) {
            $selector_options['options']['condition'] = ["id IN (" . join(',', $accessible_printer_ids) . ")"];
        }

        $output .= $form->generateFieldTableRow(__('Printer', 'iservice'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'printer[id]', $printer->getID(), $readonly, $selector_options), $no_wrap_options);

        // Name.
        $output .= $form->generateFieldTableRow(__('Name'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'printer[name]', $printer->fields['name'], $readonly || !$has_full_rights, ['required' => true]));

        // Location.
        $output .= $form->generateFieldTableRow(__('Location'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'printer[locations_id]', $printer->fields['locations_id'], $readonly || !$has_full_rights, ['type' => 'Location']), $no_wrap_options);

        // Usage address.
        $output .= $form->generateFieldTableRow('Adresa de exploatare', $form->generateField(PluginIserviceHtml::FIELDTYPE_MEMO, '_customfields[printer][usage_address_field]', $printer_customfields->fields['usage_address_field'], $readonly));

        // Cost center.
        $output .= $form->generateFieldTableRow('Centru de cost', $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_customfields[printer][cost_center_field]', $printer_customfields->fields['cost_center_field'], $readonly));

        // Coordonate GPS.
        $label = 'Coordonate GPS';
        if (!empty($printer_customfields->fields['contact_gps_field'])) {
            $label = "<a href='https://www.google.com/maps/search/?api=1&query=" . $printer_customfields->fields['contact_gps_field'] . "' target='_blank'>$label</a>";
        }

        $output .= $form->generateFieldTableRow($label, $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_customfields[printer][contact_gps_field]', $printer_customfields->fields['contact_gps_field'], $readonly));

        // Alternate username number.
        $output .= $form->generateFieldTableRow(__('Alternate username number'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'printer[contact_num]', $printer->fields['contact_num'], $readonly));

        // Alternate username.
        $output .= $form->generateFieldTableRow(__('Alternate username'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'printer[contact]', $printer->fields['contact'], $readonly));

        // Tech user.
        if ($has_full_rights) {
            $tech_dropdown_options['type']   = 'Dropdown';
            $tech_dropdown_options['values'] = IserviceToolBox::getUsersByProfiles(['tehnician']);
                $output                     .= $form->generateFieldTableRow(__('Technician in charge of the hardware'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'printer[users_id_tech]', $printer->fields['users_id_tech'], $readonly || !$has_full_rights, $tech_dropdown_options), $no_wrap_options);
        }

        // State.
        $output .= $form->generateFieldTableRow(
            __('Status'),
            $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'printer[states_id]', $printer->fields['states_id'], $readonly || !$has_full_rights, ['type' => 'State', 'class' => 'width-auto'])
            . '<div class="checkbox-after-input">' . $form->generateField(
                PluginIserviceHtml::FIELDTYPE_CHECKBOX, 'global_contract_field', $printer_customfields->fields['global_contract_field'],
                $readonly || !$has_full_rights, ['postfix' => __('Global contract', 'iservice'), 'title' => __('Select if the printer is not billed individually, but together with other printers', 'iservice')]
            ) . '</div>',
            $no_wrap_options
        );

        // Type.
        $output .= $form->generateFieldTableRow(__('Type'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'printer[printertypes_id]', $printer->fields['printertypes_id'], $readonly || !$has_full_rights, ['type' => 'PrinterType']), $no_wrap_options);

        // Manufacturer.
        $output .= $form->generateFieldTableRow(__('Manufacturer'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'printer[manufacturers_id]', $printer->fields['manufacturers_id'], $readonly || !$has_full_rights, ['type' => 'Manufacturer']), $no_wrap_options);

        // Model.
        $output .= $form->generateFieldTableRow(__('Model'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'printer[printermodels_id]', $printer->fields['printermodels_id'], $readonly || !$has_full_rights, ['type' => 'PrinterModel']), $no_wrap_options);

        // Serial number.
        $output .= $form->generateFieldTableRow(__('Serial number'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'printer[serial]', $printer->fields['serial'], $readonly || !$has_full_rights, ['required' => true]));

        // Inventory number.
        $output .= $form->generateFieldTableRow(__('Inventory number'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'printer[otherserial]', $printer->fields['otherserial'], $readonly || !$has_full_rights));

        // External user.
        if ($has_full_rights) {
            $user_dropdown_options['type']             = 'User';
            $user_dropdown_options['options']['right'] = 'all';
            $output                                   .= $form->generateFieldTableRow(__('External user'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'printer[users_id]', $printer->fields['users_id'], $readonly, $user_dropdown_options), $no_wrap_options);
        }

        // Supergroup.
        if ($has_full_rights) {
            $groups_id_options = ['type' => 'Group', 'options' => ['condition' => ['is_usergroup']]];
            $output           .= $form->generateFieldTableRow(__('Supergroup'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'printer[groups_id]', $printer->fields['groups_id'], $readonly, $groups_id_options));
        }

        // Comments.
        if ($has_full_rights) {
            $output .= $form->generateFieldTableRow(__('Comments'), $form->generateField(PluginIserviceHtml::FIELDTYPE_MEMO, 'printer[comment]', $printer->fields['comment'], $readonly));
        }

        // Planlunar week.
        $output .= $form->generateFieldTableRow('Număr săptămână', $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_customfields[printer][week_nr_field]', $printer_customfields->fields['week_nr_field'], $readonly || !$has_full_rights));

        // Planlunar comments.
        $output .= $form->generateFieldTableRow('Observații plan lunar', $form->generateField(PluginIserviceHtml::FIELDTYPE_MEMO, '_customfields[printer][plan_observations_field]', $printer_customfields->fields['plan_observations_field'], $readonly || !$has_full_rights));

        $output .= "</table>";

        return $output;
    }

    /*
     *
     * @param  Printer $printer
     * @param  Supplier $supplier
     * @param  PluginFieldsSuppliersuppliercustomfield $supplier_customfields
     * @param  boolean $readonly
     *
     * @return string
     */

    public function generateSupplierData($printer, $supplier, $supplier_customfields, $readonly): string
    {
        if ($supplier === null) {
            $readonly = true;
            $supplier = new Supplier();
            $supplier->getEmpty();
            $supplier_customfields = new PluginFieldsSuppliersuppliercustomfield();
            $supplier_customfields->getEmpty();
        }

        $has_full_rights = Session::haveRight('plugin_iservice_printer_full', UPDATE);

        $output          = "<table class='two-column' style='width:100%;'>";
        $form            = new PluginIserviceHtml();
        $no_wrap_options = ['field_class' => 'nowrap'];

        $post_data = filter_input_array(INPUT_POST);
        if (!IserviceToolBox::getInputVariable('modify_supplier')) {
            if (isset($post_data['supplier'])) {
                foreach ($post_data['supplier'] as $field_name => $field_value) {
                    $supplier->fields[$field_name] = $field_value;
                }
            }

            if (isset($post_data['_customfields']['supplier'])) {
                foreach ($post_data['_customfields']['supplier'] as $field_name => $field_value) {
                    $supplier_customfields->fields[$field_name] = $field_value;
                }
            }
        }

        // Selector.
        $selector_options['type'] = 'Supplier';
        $supplier_id              = empty($supplier->fields['id']) ? 0 : $supplier->fields['id'];
        if ($printer !== null && !$printer->isNewItem()) {
            $selector_options['options']['on_change'] = "adjustPrinterChangeButtons($(this).val() == $supplier_id, 'input[name=\"modify_supplier\"]', 'input[name=\"update_supplier\"]');";
        } else {
            $selector_options['options']['on_change'] = "redirect_to('supplier');";
        }

        $selector_options['prefix'] = $form->generateSubmit('modify_supplier', '&nbsp;', ['class' => 'grear-icon modify-supplier', 'title' => __('Modify', 'iservice') . ' ' . __('Supplier'), 'style' => 'display:none;']);
        $output                    .= $form->generateFieldTableRow(__('Partener'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'supplier[id]', $supplier->getID(), $readonly, $selector_options), ['field_class' => 'nowrap'], $no_wrap_options);

        // Name.
        if (!$readonly) {
            $output .= $form->generateFieldTableRow(__('Name'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'supplier[name]', $supplier->fields['name'] ?? ''));
        }

        // Phone number.
        $output .= $form->generateFieldTableRow(__('Phone'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'supplier[phonenumber]', $supplier->fields['phonenumber'] ?? '', $readonly));

        // Fax number.
        $output .= $form->generateFieldTableRow(__('Fax'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'supplier[fax]', $supplier->fields['fax'] ?? '', $readonly));

        // Address.
        $output .= $form->generateFieldTableRow(__('Address'), $form->generateField(PluginIserviceHtml::FIELDTYPE_MEMO, 'supplier[address]', $supplier->fields['address'] ?? '', $readonly));

        // Postal code.
        $output .= $form->generateFieldTableRow(__('Postal code'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'supplier[postcode]', $supplier->fields['postcode'] ?? '', $readonly));

        // City.
        $output .= $form->generateFieldTableRow(__('City'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'supplier[town]', $supplier->fields['town'] ?? '', $readonly));

        // State.
        $output .= $form->generateFieldTableRow(_x('location', 'State'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'supplier[state]', $supplier->fields['state'] ?? '', $readonly));

        // Country.
        $output .= $form->generateFieldTableRow(__('Country'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'supplier[country]', $supplier->fields['country'] ?? '', $readonly));

        // Third party type.
        $output .= $form->generateFieldTableRow(__('Third party type'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'supplier[suppliertypes_id]', $supplier->fields['suppliertypes_id'] ?? '', $readonly, ['type' => 'Suppliertype']), $no_wrap_options);

        // Comments.
        if ($has_full_rights) {
            $output .= $form->generateFieldTableRow(__('Comments'), $form->generateField(PluginIserviceHtml::FIELDTYPE_MEMO, 'supplier[comment]', $supplier->fields['comment'] ?? '', $readonly));
        }

        // Cod Fiscal.
        $output .= $form->generateFieldTableRow('Cod Fiscal', $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_customfields[supplier][uic_field]', $supplier_customfields->fields['uic_field'], $readonly));

        // Numar Registru Comert.
        $output .= $form->generateFieldTableRow('Număr Registru Comerț', $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_customfields[supplier][crn_field]', $supplier_customfields->fields['crn_field'], $readonly));

        // Model fisa de interventie.
        $output .= $form->generateFieldTableRow('Model fișă de intervenție', $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_customfields[supplier][intervention_sheet_model_field]', $supplier_customfields->fields['intervention_sheet_model_field'], $readonly));

        // HMarfa code.
        $output .= $form->generateFieldTableRow('Cod Partener hMarfa', $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_customfields[supplier][hmarfa_code_field]', $supplier_customfields->fields['hmarfa_code_field'], $readonly));

        // Email pentru trimis facturi.
        if ($has_full_rights) {
            $output .= $form->generateFieldTableRow('Email pentru trimis facturi', $form->generateField(PluginIserviceHtml::FIELDTYPE_MEMO, '_customfields[supplier][email_for_invoices_field]', $supplier_customfields->fields['email_for_invoices_field'], $readonly));
        }

        $output .= "</table>";

        return $output;
    }

    public function generateContractData($printer, $contract, $contract_customfields, $readonly): string
    {
        if ($contract === null) {
            $readonly = true;
            $contract = new PluginIserviceContract();
            $contract->getEmpty();
            $contract_customfields = new PluginFieldsContractcontractcustomfield();
            $contract_customfields->getEmpty();
        }

        $output          = "<table class='two-column' style='width:100%;'>";
        $form            = new PluginIserviceHtml();
        $no_wrap_options = ['field_class' => 'nowrap'];

        $post_data = filter_input_array(INPUT_POST);
        if (!IserviceToolBox::getInputVariable('modify_contract')) {
            if (isset($post_data['contract'])) {
                foreach ($post_data['contract'] as $field_name => $field_value) {
                    $contract->fields[$field_name] = $field_value;
                }
            }

            if (isset($post_data['_customfields']['contract'])) {
                foreach ($post_data['_customfields']['contract'] as $field_name => $field_value) {
                    $contract_customfields->fields[$field_name] = $field_value;
                }
            }
        }

        // Selector.
        $selector_options['type'] = 'Contract';
        $contract_id              = empty($contract->fields['id']) ? 0 : $contract->fields['id'];
        if ($printer !== null && !$printer->isNewItem()) {
            $selector_options['options']['on_change'] = "adjustPrinterChangeButtons($(this).val() == $contract_id, \"input[name=\\\"modify_contract\\\"]\", \"input[name=\\\"update_contract\\\"]\");";
        } else {
            $selector_options['options']['on_change'] = "window.location.href=\"//\" + window.location.host + window.location.pathname + \"?contract_id=\" + $(this).val();";
        }

        $selector_options['options']['nochecklimit'] = true;
        $selector_options['options']['expired']      = true;
        $selector_options['prefix']                  = $form->generateSubmit('modify_contract', '&nbsp;', ['class' => 'grear-icon modify-contract', 'title' => __('Modify', 'iservice') . ' ' . __('Contract'), 'style' => 'display:none;']);
        $output                                     .= $form->generateFieldTableRow(__('Contract'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'contract[id]', $contract_id, $readonly, $selector_options));

        // Name.
        if (!$readonly) {
            $output .= $form->generateFieldTableRow(__('Name'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'contract[name]', $contract->fields['name']));
        }

        // Number.
        $output .= $form->generateFieldTableRow(_x('phone', 'Number'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'contract[num]', $contract->fields['num'], $readonly));

        // Begin date.
        $output .= $form->generateFieldTableRow(__('Start date'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DATE, 'contract[begin_date]', $contract->fields['begin_date']), $readonly, $no_wrap_options);

        // Periodicity.
        $periodicity_options['method']  = 'showNumber';
        $periodicity_options['options'] = [
            'min' => 12,
            'max' => 60,
            'step' => 12,
            'toadd' => [0 => Dropdown::EMPTY_VALUE,
                1 => sprintf(_n('%d month', '%d months', 1), 1),
                2 => sprintf(_n('%d month', '%d months', 2), 2),
                3 => sprintf(_n('%d month', '%d months', 3), 3),
                6 => sprintf(_n('%d month', '%d months', 6), 6)
            ],
            'unit' => 'month'
        ];
        $output                        .= $form->generateFieldTableRow(__('Contract renewal period'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'contract[periodicity]', $contract->fields['periodicity'], $readonly, $periodicity_options));

        // Renewal.
        $renewal_options['type']            = 'Contract';
        $renewal_options['method']          = 'dropdownContractRenewal';
        $renewal_options['readonly_method'] = 'getContractRenewalName';
        $renewal_options['arguments']       = ['contract[renewal]', $contract->fields['renewal'], false];
        $output                            .= $form->generateFieldTableRow(__('Renewal'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'contract[renewal]', $contract->fields['renewal'], $readonly, $renewal_options));

        // Contract type.
        $contracttype_options['type'] = 'ContractType';
        $output                      .= $form->generateFieldTableRow(__('Contract type'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'contract[contracttypes_id]', $contract->fields['contracttypes_id'], $readonly, $contracttype_options), $no_wrap_options);

        // Comments.
        $output .= $form->generateFieldTableRow(__('Comments'), $form->generateField(PluginIserviceHtml::FIELDTYPE_MEMO, 'contract[comment]', $contract->fields['comment'], $readonly));

        // Included black copies.
        $output .= $form->generateFieldTableRow('Copii black incluse', $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_customfields[contract][included_copies_bk_field]', $contract_customfields->fields['included_copies_bk_field'], $readonly));

        // Included color copies.
        $output .= $form->generateFieldTableRow('Copii color incluse', $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_customfields[contract][included_copies_col_field]', $contract_customfields->fields['included_copies_col_field'], $readonly));

        // Black copy price.
        $output .= $form->generateFieldTableRow('Tarif copie black', $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_customfields[contract][copy_price_bk_field]', $contract_customfields->fields['copy_price_bk_field'], $readonly));

        // Color copy price.
        $output .= $form->generateFieldTableRow('Tarif copie color', $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_customfields[contract][copy_price_col_field]', $contract_customfields->fields['copy_price_col_field'], $readonly));

        // Included copies value.
        $output .= $form->generateFieldTableRow('Valoare copii incluse', $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_customfields[contract][included_copy_value_field]', $contract_customfields->fields['included_copy_value_field'], $readonly));

        // Monthly price.
        $output .= $form->generateFieldTableRow('Tarif lunar', $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_customfields[contract][monthly_fee_field]', $contract_customfields->fields['monthly_fee_field'], $readonly));

        // Rate.
        $output .= $form->generateFieldTableRow('Curs de calcul', $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_customfields[contract][currency_field]', $contract_customfields->fields['currency_field'], $readonly));

        // Unit price divider.
        $output .= $form->generateFieldTableRow('Divizor PU copie', $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_customfields[contract][copy_price_divider_field]', $contract_customfields->fields['copy_price_divider_field'], $readonly));

        $output .= "</table>";

        return $output;
    }

    public function generatePrinterButtons($printer, $readonly): string
    {
        if ($readonly || $printer === null) {
            return "&nbsp;";
        }

        $form = new PluginIserviceHtml();
        if ($printer->isNewItem()) {
            if (Session::haveRight('plugin_iservice_printer_full', CREATE)) {
                return $form->generateSubmit('add', __('Add') . ' ' . __('Printer', 'iservice'));
            } else {
                return "&nbsp;";
            }
        } else {
            return $form->generateSubmit('update', __('Save') . ' ' . __('Printer', 'iservice'));
        }
    }

    public function generateSupplierButtons($printer, $supplier, $supplier_customfields, $readonly): string
    {
        if ($readonly || $supplier === null) {
            return "&nbsp;";
        }

        $form = new PluginIserviceHtml();
        if ($printer !== null && $printer->isNewItem()) {
            if (Session::haveRight('contact_enterprise', CREATE)) {
                return $form->generateSubmit('add_supplier', __('Add') . ' ' . __('Supplier'), ['class' => 'submit']);
            } else {
                return "&nbsp;";
            }
        } elseif (!$supplier->isNewItem()) {
            return $form->generateSubmit('update_supplier', __('Save') . ' ' . __('Supplier'), ['class' => 'submit']);
        }

        return "&nbsp;";
    }

    public function generateContractButtons($printer, $contract, $contract_customfields, $readonly): string
    {
        if ($readonly || $contract === null) {
            return "&nbsp;";
        }

        $form = new PluginIserviceHtml();

        if ($printer !== null && $printer->isNewItem()) {
            if (Session::haveRight('contract', CREATE)) {
                return $form->generateSubmit('add', __('Add') . ' ' . __('Contract'), ['class' => 'submit']);
            } else {
                return "&nbsp;";
            }
        } elseif (!$contract->isNewItem()) {
            return $form->generateSubmit('update_contract', __('Save') . ' ' . __('Contract'), ['class' => 'submit']);
        }

        return '';
    }

    public function getFromDBByEMSerial($serial, $use_cm_condition = false): bool
    {
        $printer_table    = PluginIservicePrinter::getTable();
        $spaceless_serial = str_replace(" ", "", $serial);

        if ($use_cm_condition) {
            $join         = "JOIN glpi_infocoms i ON i.items_id = p.id and i.itemtype = 'Printer' JOIN glpi_plugin_fields_suppliersuppliercustomfields cfs ON cfs.items_id = i.suppliers_id and cfs.itemtype = 'Supplier'";
            $cm_condition = "AND " . self::getCMCondition('cfs.cm_field', "p.printertypes_id", "p.states_id");
        } else {
            $join         = "";
            $cm_condition = "";
        }

        $result = PluginIserviceDB::getQueryResult("SELECT `p`.`id` from $printer_table p $join WHERE " . self::getSerialFieldForEM('p') . " = '$spaceless_serial' AND is_deleted = 0 $cm_condition LIMIT 1");

        if (!$result) {
            return false;
        }

        return $this->getFromDB(array_pop($result)['id']);
    }

    public function getSpacelessSerial(): string
    {
        if (empty($this->fields['serial'])) {
            return '';
        }

        return str_replace(' ', '', substr($this->fields['serial'], strpos($this->fields['serial'], '_')));
    }

    public static function getSerialFieldForEM($table_prefix = ''): string
    {
        if (!empty($table_prefix) && substr($table_prefix, -1) !== '.') {
            $table_prefix .= '.';
        } else {
            $table_prefix = '';
        }

        return "REPLACE(SUBSTR({$table_prefix}serial, POSITION('_' IN serial) + 1), ' ', '')";
    }

    public static function getCMCondition($cartridge_management_field, $type_field, $state_field): string
    {
        $blackWhitePrinterType = IserviceToolBox::getIdentifierByAttribute('PrinterType', 'alb-negru');
        $colorPrinterType      = IserviceToolBox::getIdentifierByAttribute('PrinterType', 'color');
        return "
            (    $cartridge_management_field = 1
             AND $type_field in ($blackWhitePrinterType, $colorPrinterType)
             AND $state_field in (SELECT id FROM glpi_states WHERE name like 'CO%' OR name like 'Gar%' OR name like 'Pro%')
            )";
    }

    public static function getCMConditionForDisplay(): string
    {
        return "sunt în CM, au cel puțin un cartuș instalat și status-ul începe cu CO, GAR sau PRO";
    }

    public static function getInstalledCartridges($printer_id, $additional_condition = ''): array|bool
    {
        if (empty($printer_id)) {
            return false;
        }

        return PluginIserviceDB::getQueryResult(
            "
                select c.*, ci.mercury_code_field mercury_code, ci.compatible_mercury_codes_field compatible_mercury_codes, ci.atc_field atc, ci.name, c.plugin_fields_cartridgeitemtypedropdowns_id type_id, tfd.completename type_name
                from glpi_plugin_iservice_cartridges c
                join glpi_plugin_iservice_cartridge_items ci on ci.id = c.cartridgeitems_id
                left join glpi_plugin_fields_cartridgeitemtypedropdowns tfd on tfd.id = c.plugin_fields_cartridgeitemtypedropdowns_id
                where printers_id = $printer_id AND NOT date_use IS null AND date_out IS null $additional_condition
                "
        );
    }

}

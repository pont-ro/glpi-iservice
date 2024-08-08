<?php

// Imported from iService2, needs refactoring. Original file: "movement.class.php".
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

class PluginIserviceMovement extends CommonDBTM
{

    const TYPE_IN   = 'in';
    const TYPE_OUT  = 'out';
    const TYPE_MOVE = 'move';

    public static $rightname = 'plugin_iservice_movement';

    public static function dummy(): array
    {
        // This function is to declare translations.
        return [
            _t('Printer'),
        ];
    }

    public function __construct($itemtype = '')
    {
        parent::__construct();
        $this->fields['itemtype'] = $itemtype;
    }

    public static function existsFor($itemtype, $item_id, $only_open = true): ?bool
    {
        if ($only_open) {
            return self::getOpenFor($itemtype, $item_id);
        } else {
            throw new Exception("existsFor not implemented for only_open = false");
        }
    }

    public static function getOpenFor($itemtype, $item_id)
    {
        if (empty($itemtype) || empty($item_id)) {
            return false;
        }

        $movement = new PluginIserviceMovement($itemtype);
        if (PluginIserviceDB::populateByQuery($movement, "WHERE itemtype='$itemtype' and items_id = $item_id AND NOT moved = 1 LIMIT 1")) {
            return $movement->getID();
        }

        global $DB;
        $moved_result = $DB->queryOrDie("SELECT * FROM glpi_plugin_iservice_movements WHERE itemtype='$itemtype' AND items_id = $item_id AND moved = 1", "Internal error #1");
        while ($moved = $moved_result->fetch_array()) {
            if (self::getTypeFromSuppliers($moved['suppliers_id_old'], $moved['suppliers_id']) == self::TYPE_IN) {
                continue;
            }

            $ticket_out              = new Ticket();
            $ticket_out_customfields = new PluginFieldsTicketticketcustomfield();
            if ((PluginIserviceDB::populateByQuery($ticket_out_customfields, "WHERE movement2_id_field = $moved[id] LIMIT 1") === false) || ($ticket_out->getFromDB($ticket_out_customfields->fields['items_id']) && $ticket_out->fields['status'] != Ticket::CLOSED)) {
                return $moved['id'];
            }
        }

        return false;
    }

    public function ShowForm($ID, array $options = []): void
    {
        global $CFG_GLPI;
        $id      = 0;
        $buttons = [];
        $request = filter_input_array(INPUT_GET);

        if (isset($request['id'])) {
            if (!$this->getFromDB($id = $request['id'])) {
                Html::displayNotFoundError();
            }
        }

        foreach ($request as $variable_name => $variable_value) {
            $this->fields[$variable_name] = $variable_value;
        }

        $itemtype = $this->fields['itemtype'];

        $item              = new $itemtype;
        $customfieldclass  = "PluginFields$itemtype" . strtolower($itemtype) . "customfield";
        $item_customfields = new $customfieldclass();
        $partner_old       = new Supplier();
        if (isset($this->fields['items_id'])) {
            $item->getFromDB($this->fields['items_id']);
            PluginIserviceDB::populateByItemsId($item_customfields, $this->fields['items_id'], $itemtype);
            if (empty($id) && ($movement = PluginIserviceMovement::existsFor($itemtype, $this->fields['items_id'])) !== false) {
                PluginIserviceHtml::displayErrorAndDie("<a href='movement.form.php?id=$movement' target='_blank'>O mutare nefinalizată există pentru acest aparat, vă rugăm finalizați mutarea $movement întâi!</a>");
            }

            if (empty($id) && ($last_ticket_id = PluginIserviceTicket::getLastIdForPrinterOrSupplier(0, $this->fields['items_id'], true)) > 0) {
                PluginIserviceHtml::displayErrorAndDie("<a href='ticket.form.php?id=$last_ticket_id' target='_blank'>Există ticket deschis pentru acest aparat, vă rugăm închideți tichetul mai întâi!</a>");
            }

            if (empty($this->fields['suppliers_id_old'])) {
                $infocom = new Infocom();
                if (!$infocom->getFromDBforDevice($this->fields['itemtype'], $this->fields['items_id']) || !$partner_old->getFromDB($infocom->fields['suppliers_id'])) {
                    $partner_old->getEmpty();
                }

                $this->fields['suppliers_id_old'] = $partner_old->getID();
            }
        }

        $form = new PluginIserviceHtml();

        $title        = "<h1>" . _t('Move') . " " . (isset($item->fields['name']) ? $item->fields['name'] : __($itemtype, 'iservice')) . "</h1>";
        $table_rows[] = new PluginIserviceHtml_table_row('', new PluginIserviceHtml_table_cell($title, '', '', 2));

        if (isset($this->fields['ticket_id']) && !empty($this->fields['ticket_id'])) {
            $itilcategory = new ITILCategory();
            if (!$itilcategory->getFromDB($this->fields['itilcategories_id'])) {
                Html::displayErrorAndDie('Invalid ticket category');
            }

            $ticket = new Ticket();
            if ($this->fields['ticket_id'] > 0) {
                if (!$ticket->getFromDB($this->fields['ticket_id'])) {
                    Html::displayNotFoundError();
                }

                $text_to_display = "<h3>Creați o mutare din thichetul cu categoria '{$itilcategory->fields['name']}'!</h3>";
            } else {
                $text_to_display = "<h3>În loc de a crea un ticket cu categoria '{$itilcategory->fields['name']}', creați o mutare!</h3>";
            }

            $table_rows[] = "<tr><td colspan=2><input type='hidden' name='ticket_id' value='{$this->fields['ticket_id']}'/>$text_to_display</td></tr>";
            if (stripos($itilcategory->fields['name'], 'preluare') === 0) {
                $this->fields['suppliers_id'] = IserviceToolBox::getExpertLineId();
            }
        }

        $table_rows[] = $form->generateFieldTableRow(__($itemtype, 'iservice'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'items_id', $this->fields['items_id'], true, ['type' => "PluginIservice$itemtype"]));
        $table_rows[] = $form->generateFieldTableRow(_t('Old partner'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'suppliers_id_old', $this->fields['suppliers_id_old'], true, ['type' => "Supplier"]));
        $table_rows[] = $form->generateFieldTableRow(_t('New partner'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'suppliers_id', empty($this->fields['suppliers_id']) ? '' : $this->fields['suppliers_id'], $id > 0, ['type' => "Supplier", 'class' => 'full']));

        $moved   = false;
        $printer = new PluginIservicePrinter();
        $printer->getFromDB($this->fields['items_id']);

        if ($id > 0) {
            $total2_black               = $this->fields['total2_black_field'] ?? $printer->lastTicket()->customfields->fields['total2_black_field'] ?? '';
            $total2_color               = $this->fields['total2_color_field'] ?? $printer->lastTicket()->customfields->fields['total2_color_field'] ?? '';
            $invoiced_total_black_field = $this->fields['invoiced_total_black_field'] ?? $item_customfields->fields['invoiced_total_black_field'] ?? '';
            $invoiced_total_color_field = $this->fields['invoiced_total_color_field'] ?? $item_customfields->fields['invoiced_total_color_field'] ?? '';
            $invoice_date_field         = $this->fields['invoice_date'] ?? $item_customfields->fields['invoice_date_field'] ?? '';
            $invoice_expiry_date_field  = $this->fields['invoice_expiry_date_field'] ?? $item_customfields->fields['invoice_expiry_date_field'] ?? '';
            $dba                        = $this->fields['dba'] ?? $item_customfields->fields['daily_bk_average_field'] ?? '';
            $dca                        = $this->fields['dca'] ?? $item_customfields->fields['daily_color_average_field'] ?? '';
            $disable_em                 = $this->fields['disableem'] ?? 0;
            $snooze_read_check          = $this->fields['snoozereadcheck'] ?? date('Y-m-d', strtotime('yesterday'));
            $table_rows[]               = $form->generateFieldTableRow(
                'Date facturare și contoare', '<div id="invoice-data" style="width:82%">'
                    . '<div style="display:inline-block;width:50%">ultima factură: <b>' . $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, 'invoice_date', date('Y-m-d', strtotime($invoice_date_field))) . '</b></div>'
                    . '<div style="display:inline-block;width:50%">expirare factură: <b>' . $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, 'invoice_expiry_date_field', date('Y-m-d', strtotime($invoice_expiry_date_field))) . '</b></div>'
                    . '<div style="display:inline-block;width:50%">Contor black ultima factură: <b>' . $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, 'invoiced_total_black_field', $invoiced_total_black_field) . '</b></div>'
                    . '<div style="display:inline-block;width:50%">Contor black ultima intervenție: <b>' . $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, 'total2_black_field', $total2_black) . '</b></div>'
                    . '<div style="display:inline-block;width:50%">Contor color ultima factură: <b>' . $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, 'invoiced_total_color_field', $invoiced_total_color_field) . '</b></div>'
                    . '<div style="display:inline-block;width:50%">Contor color ultima intervenție: <b>' . $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, 'total2_color_field', $total2_color) . '</b></div>'
                . '</div>'
            );

            // Update button.
            $buttons[] = $form->generateSubmit('update', __('Update'), ['data-required' => 'items_id,suppliers_id,states_id,users_id_tech']);

            // Determine the type.
            $type = self::getTypeFromSuppliers($this->fields['suppliers_id_old'], $this->fields['suppliers_id']);

            // In Ticket.
            $ticket_in              = new Ticket();
            $ticket_in_customfields = new PluginFieldsTicketticketcustomfield();
            if (PluginIserviceDB::populateByQuery($ticket_in_customfields, "WHERE movement_id_field = $id LIMIT 1") && $ticket_in->getFromDB($ticket_in_customfields->fields['items_id'])) {
                $ticket_in_exists = true;
                $ticket_in_closed = $ticket_in->fields['status'] == Ticket::CLOSED;
                $ticket_actions   = "<a href='ticket.form.php?id={$ticket_in->getID()}&_close_on_success=1' class='vsubmit' target='_blank'>" . ($ticket_in_closed ? _t('View') : (_t('Modify') . " / " . _t('Close'))) . "</a>";
            } else {
                $ticket_in_exists                         = false;
                $ticket_in_closed                         = false;
                $params                                   = "items_id[Printer][0]=" . $this->fields['items_id'];
                $params                                  .= "&suppliers_id_old=" . $this->fields['suppliers_id_old'];
                $params                                  .= "&_movement_id=$id";
                $params                                  .= "&itilcategories_id=" . PluginIserviceTicket::getItilCategoryId('preluare echipament');
                $params                                  .= "&name=preluare echipament";
                $params                                  .= "&content=preluare echipament";
                $params                                  .= "&_users_id_assign=" . ($printer->fields['users_id_tech'] ?? '');
                $params                                  .= "&_export_type=" . PluginIserviceTicket::EXPORT_TYPE_NOTICE_ID;
                $params                                  .= "&_close_on_success=1";
                $params                                  .= "&add_cartridges_as_negative_consumables=1";
                $ticket                                   = new PluginIserviceTicket();
                $ticket->fields['items_id']['Printer'][0] = $this->fields['items_id'];
                $ticket->fields['locations_id']           = $printer->fields['locations_id'] ?? '';
                $ticket->fields['_suppliers_id_assign']   = $this->fields['suppliers_id_old'];
                $ticket_actions                           = PluginIserviceCartridgeItem::tableChangeablesForTicket($ticket);
                $ticket_actions                          .= "<br><span id='ticket-actions'>";
                $ticket_actions                          .= "<a href='movement.form.php?delete=$id' class='vsubmit'>" . _t('Abort movement') . "</a>";
                $ticket_actions                          .= "&nbsp;<span id='ticket-creation-allowed'>";
                $ticket_actions                          .= "<a href='ticket.form.php?$params' class='vsubmit' target='_blank' onclick='$(\"#ticket-actions\").hide();addHrefParams(this, document.getElementById(\"printer-changeable-cartridges\"));'>" . __("Create ticket") . "</a>";
                $ticket_actions                          .= "<i class='fa fa-exclamation-triangle' style='color:orange'></i>Creând tichetul, nu mai puteți renunța la mutare!";
                $ticket_actions                          .= "</span>";
                $ticket_actions                          .= "<span id='ticket-creation-prevented' style='display:none;'>";
                $ticket_actions                          .= "<i class='fa fa-exclamation-circle' style='color:red'></i>Nu puteți muta aparatul, deoarece nu sunt selectate toate cartușele necesare!";
                $ticket_actions                          .= "</span>";
                $ticket_actions                          .= "</span>";
                $ticket_actions                          .= "<script>";
                $ticket_actions                          .= "function adjustTicketCreation() { if ($('.prevent-ticket-creation.visible').length > 0) { $('#ticket-creation-allowed').hide();$('#ticket-creation-prevented').show(); } else { $('#ticket-creation-allowed').show();$('#ticket-creation-prevented').hide(); }}";
                $ticket_actions                          .= "$('.toggler-checkbox').change(function() { setTimeout(adjustTicketCreation, 100); });";
                $ticket_actions                          .= "</script>";
            }

            // Type.
            switch ($type) {
            case self::TYPE_OUT:
                $ticket_in_closed = true;
                $invoice_exists   = true;
                break;
            case self::TYPE_IN:
            case self::TYPE_MOVE:
            default:
                $table_rows[] = $form->generateFieldTableRow(_n("Ticket", "Tickets", 1) . " preluare echipament", $ticket_actions);
                break;
            }

            // Invoice.
            if ($ticket_in_exists) {
                $invoice_exists = $this->fields['invoice'];
            } elseif (!$ticket_in_closed) {
                $table_rows[]   = "<tr><td colspan=2><h3>" . _t('Create the ticket to continue') . "</h3></td></tr>";
                $invoice_exists = false;
            }

            if ($ticket_in_closed && $invoice_exists) {
                // Check counters.
                $move_button_disabled = false;
                $checkbox_onclick     = 'if ($(".movement_prohibitor:checked").length === $(".movement_prohibitor").length) {$("#btn_move").attr("disabled", false).removeClass("disabled");} else {$("#btn_move").attr("disabled", true).addClass("disabled");}';
                $checkbox             = $form->generateField(PluginIserviceHtml::FIELDTYPE_CHECKBOX, '', 0, false, ['class' => 'movement_prohibitor', 'onclick' => $checkbox_onclick]);
                if ($total2_black > $invoiced_total_black_field) {
                    $last_invoice_black_counter_color = 'red';
                    $last_intervention_checkbox_black = "$checkbox Confirm că am actualizat contorul black (contor ultima intervenție > ultima factura)";
                    $move_button_disabled             = 'disabled';
                } else {
                    $last_invoice_black_counter_color = 'black';
                    $last_intervention_checkbox_black = '';
                }

                if ($total2_color > $invoiced_total_color_field) {
                    $last_invoice_color_counter_color = 'red';
                    $last_intervention_checkbox_color = "$checkbox Confirm că am actualizat contorul color (contor ultima intervenție > ultima factura)";
                    $move_button_disabled             = 'disabled';
                } else {
                    $last_invoice_color_counter_color = 'black';
                    $last_intervention_checkbox_color = '';
                }

                // Update item button.
                $buttons[] = $form->generateSubmit('move', _t('Move') . " " . __($this->fields['itemtype'], 'iservice'), ['data-required' => 'items_id,suppliers_id,states_id,users_id_tech', 'class' => "submit $move_button_disabled", 'disabled' => $move_button_disabled]);

                // Item properties to update.
                $table_rows[] = "<tr><td colspan=2><h3>Date noi pentru " . __($itemtype, 'iservice') . "</h3></td></tr>";

                // Status.
                $states_id         = empty($this->fields['states_id']) ? $item->fields['states_id'] : $this->fields['states_id'];
                $states_id_options = ['type' => 'State', 'class' => 'full', 'options' => ['condition' => ['`is_visible_printer`']]];
                $table_rows[]      = $form->generateFieldTableRow(__('Status'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'states_id', $states_id, false, $states_id_options));

                // Location.
                $locations_id = empty($this->fields['locations_id']) ? $item->fields['locations_id'] : $this->fields['locations_id'];
                $table_rows[] = $form->generateFieldTableRow(__('Location'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'locations_id', $locations_id, false, ['type' => 'Location', 'class' => 'full']));

                // Usage address.
                $usage_address = empty($this->fields['usage_address']) ? $item_customfields->fields['usage_address_field'] : $this->fields['usage_address'];
                $table_rows[]  = $form->generateFieldTableRow(_t('Usage address'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'usage_address', $usage_address));

                // Week number.
                $week_number  = empty($this->fields['week_number']) ? $item_customfields->fields['week_nr_field'] : $this->fields['week_number'];
                $table_rows[] = $form->generateFieldTableRow('Numar saptamana', $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'week_number', $week_number));

                // User id tech.
                $users_id_tech         = empty($this->fields['users_id_tech']) ? $item->fields['users_id_tech'] : $this->fields['users_id_tech'];
                $users_id_tech_options = ['type' => 'Dropdown', 'class' => 'full',  'values' => IserviceToolBox::getUsersByProfiles(['tehnician'])];
                $table_rows[]          = $form->generateFieldTableRow(__('Technician in charge of the hardware'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'users_id_tech', $users_id_tech, false, $users_id_tech_options));

                // Contact number.
                $contact_num  = empty($this->fields['contact_num']) ? $item->fields['contact_num'] : $this->fields['contact_num'];
                $table_rows[] = $form->generateFieldTableRow(__('Alternate username number'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'contact_num', $contact_num));

                // Contact.
                $contact      = empty($this->fields['contact']) ? $item->fields['contact'] : $this->fields['contact'];
                $table_rows[] = $form->generateFieldTableRow(__('Alternate username'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'contact', $contact));

                // Contract.
                $contract_item = new Contract_Item();
                if (!PluginIserviceDB::populateByQuery($contract_item, "WHERE items_id = {$this->fields['items_id']} AND itemtype = '$itemtype' LIMIT 1")) {
                    $contract_item->getEmpty();
                }

                // $this->fields['contracts_id'] must be overwritten only if it is empty (was not manually set to some empy value by the user)
                $contracts_id = empty($this->fields['contracts_id']) && $this->fields['contracts_id'] != '0' ? $contract_item->fields['contracts_id'] : $this->fields['contracts_id'];
                $table_rows[] = $form->generateFieldTableRow(__('Contract'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'contracts_id', $contracts_id, false, ['type' => 'Contract', 'class' => 'full', 'options' => ['nochecklimit' => true]]));

                // Daily averages.
                $table_rows[] = $form->generateFieldTableRow(
                    'Număr copii pe zi', '<div style="width:82%">'
                        . '<div style="display:inline-block;width:50%">Copii bk. pe zi ' . $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'dba', $dba, false, ['style' => 'width:inherit']) . '</div>'
                        . (($printer->isColor() || $printer->isPlotter()) ? '<div style="display:inline-block;width:50%">' . ($printer->isPlotter() ? 'Suprafață printată pe zi ' : 'Copii color pe zi ') . $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'dca', $dca, !$printer->isColor() && !$printer->isPlotter(), ['style' => 'width:inherit']) . '</div>' : '')
                    . '</div>'
                );

                // Invoice data.
                $table_rows[] = $form->generateFieldTableRow(
                    'Date ultima factură', '<div style="width:82%">'
                        . '<div style="display:inline-block;width:50%">ultima factură ' . $form->generateField(PluginIserviceHtml::FIELDTYPE_DATE, 'invoice_date', $invoice_date_field) . '</div>'
                        . '<div style="display:inline-block;width:50%">expirare factură ' . $form->generateField(PluginIserviceHtml::FIELDTYPE_DATE, 'invoice_expiry_date_field', $invoice_expiry_date_field) . '</div>'
                    . '</div>'
                );

                // Invoiced counters.
                $table_rows[] = $form->generateFieldTableRow(
                    'Contor black', '<div style="width:82%">'
                        . "<div style=\"display:inline-block;width:50%;color:$last_invoice_black_counter_color\">ultima factură " . $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'invoiced_total_black_field', $invoiced_total_black_field, false, ['style' => 'width:inherit']) . '</div>'
                        . "<div style=\"display:inline-block;width:50%\">ultima intervenție: <b>" . $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, 'total2_black_field', $total2_black) . "</b>;</div>$last_intervention_checkbox_black"
                    . '</div>'
                );

                // Last counters.
                $table_rows[] = $form->generateFieldTableRow(
                    'Contor color', '<div style="width:82%">'
                        . "<div style=\"display:inline-block;width:50%;color:$last_invoice_color_counter_color\">ultima factură " . $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'invoiced_total_color_field', $invoiced_total_color_field, false, ['style' => 'width:inherit']) . '</div>'
                        . "<div style=\"display:inline-block;width:50%\">ultima intervenție: <b>" . $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, 'total2_color_field', $total2_color) . "</b></div>$last_intervention_checkbox_color"
                    . '</div>'
                );
                $table_rows[] = '<script>$("#invoice-data").closest("tr").hide();</script>';

                // E-maintenance.
                $table_rows[] = $form->generateFieldTableRow(
                    'E-maintenance', '<div style="width:82%">'
                    . '<div style="display:inline-block;width:50%">Exclus din EM ' . $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'disableem', $disable_em, false, ['style' => 'width:inherit', 'values' => ['0' => 'Nu', '1' => 'Da']]) . '</div>'
                    . '<div style="display:inline-block;width:50%">Amână verificarea citirii până: <b>' . $form->generateField(PluginIserviceHtml::FIELDTYPE_DATE, 'snoozereadcheck', $snooze_read_check) . '</b></div>'
                    . '</div>'
                );
                $table_rows[] = '<script>$("#invoice-data").closest("tr").hide();</script>';

                // Special rights.
                $table_rows[] = "<tr><td colspan=2><h3>Drepturi de acces SPECIALE</h3></td></tr>";

                // User.
                $users_id         = empty($this->fields['users_id']) ? 0 : $this->fields['users_id']; // As requested on EXL-388, users_id should be reseted on movement.
                $users_id_options = ['type' => 'User', 'class' => 'full', 'options' => ['right' => 'all']];
                $table_rows[]     = $form->generateFieldTableRow(__('External user'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'users_id', $users_id, false, $users_id_options));

                // Group.
                $groups_id         = empty($this->fields['groups_id']) ? $item->fields['groups_id'] : $this->fields['groups_id'];
                $groups_id_options = ['type' => 'Group', 'class' => 'full', 'options' => ['condition' => ['is_usergroup']]];
                $table_rows[]      = $form->generateFieldTableRow(__('Supergroup'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'groups_id', $groups_id, false, $groups_id_options));

                $moved = $this->fields['moved'];
            } else {
                if ($ticket_in_exists) {
                    $table_rows[] = "<tr><td colspan=2><h3>" . _t('Close the ticket and issue the invoice to continue') . "</h3></td></tr>";
                }
            }
        } else {
            // Add button.
            $buttons[] = $form->generateSubmit('add', _t('Start movement'), ['data-required' => 'items_id,suppliers_id,week_number']);
        }

        // Out Ticket.
        $ticket_out              = new Ticket();
        $ticket_out_customfields = new PluginFieldsTicketticketcustomfield();
        if (PluginIserviceDB::populateByQuery($ticket_out_customfields, "WHERE movement2_id_field = $id LIMIT 1") && $ticket_out->getFromDB($ticket_out_customfields->fields['items_id'])) {
            $ticket_out_exists = true;
            $ticket_out_closed = $ticket_out->fields['status'] == Ticket::CLOSED;
            $ticket_actions    = "<a href='ticket.form.php?id={$ticket_out->getID()}' class='vsubmit' target='_blank'>" . ($ticket_out_closed ? _t('View') : _t('Close')) . "</a>";
            if (!$ticket_out_closed) {
                $ticket_actions .= "&nbsp;&nbsp;<a href='ticket.form.php?id={$ticket_out->getID()}' class='vsubmit' target='_blank'>" . _t('Modify') . "</a>";
            }
        } else {
            $ticket_out_exists = false;
            $ticket_out_closed = false;
            $params            = "items_id[Printer][0]=" . $this->fields['items_id'];
            $params           .= "&_movement2_id=$id";
            $params           .= "&itilcategories_id=" . PluginIserviceTicket::getItilCategoryId('livrare echipament');
            $params           .= "&name=livrare echipament";
            $params           .= "&content=livrare echipament";
            $params           .= "&_users_id_assign=" . ($printer->fields['users_id_tech'] ?? '');
            $params           .= "&_close_on_success=1";
            $ticket_actions    = "<a href='ticket.form.php?$params' class='vsubmit' target='_blank'>" . __("Create ticket") . "</a>";
        }

        if ($moved) {
            switch ($type) {
            case self::TYPE_IN:
                $ticket_out_closed = true;
                break;
            case self::TYPE_OUT:
            case self::TYPE_MOVE:
            default:
                $table_rows[] = "<tr><td colspan=2>&nbsp;</td></tr>";
                $table_rows[] = $form->generateFieldTableRow(_n("Ticket", "Tickets", 1) . " livrare echipament", $ticket_actions);
                break;
            }

            if ($ticket_out_closed) {
                $table_rows[] = "<tr><td colspan=2><h3>" . _t('This movement is finalized') . "</h3></td></tr>";
            } else {
                $table_rows[] = "<tr><td colspan=2><h3>" . _t('Close the ticket to finalize movement') . "&nbsp;&nbsp;<a href='' class='vsubmit'>" . __("Update") . "</a></h3></td></tr>";
            }
        } else {
            $table_rows[] = $form->generateButtonsTableRow($buttons);
        }

        echo "<div id='iservice-body'>";
        $form->openForm(['method' => 'post', 'class' => 'iservice-form two-column']);

        if ($id > 0) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'id', $id);
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'type', $type);
        }

        $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'itemtype', $this->fields['itemtype']);
        $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'ticket_out_exists', $ticket_out_exists ? '1' : '0');

        $table = new PluginIserviceHtml_table('tab_cadre_fixe', null, $table_rows);
        echo $table;

        $form->closeForm();
        echo "</div>";
    }

    public static function getTypeFromSuppliers($old_supplier_id, $new_supplier_id)
    {
        if ($old_supplier_id == IserviceToolBox::getExpertLineId()) {
            return self::TYPE_OUT;
        } elseif ($new_supplier_id == IserviceToolBox::getExpertLineId()) {
            return self::TYPE_IN;
        } else {
            return self::TYPE_MOVE;
        }
    }

}

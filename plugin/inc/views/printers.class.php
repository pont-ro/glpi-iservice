<?php

// Imported from iService2, needs refactoring. Original file: "Printers.php".
namespace GlpiPlugin\Iservice\Views;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use GlpiPlugin\Iservice\Views\View;
use PluginFieldsPrinterprintercustomfield;
use PluginIserviceCartridgeItem;
use PluginIserviceDB;
use PluginIserviceEmaintenance;
use PluginIserviceMovement;
use PluginIservicePrinter;
use PluginIserviceTicket;
use Session;
use Ticket;

class Printers extends View
{
    public static $rightname                   = 'plugin_iservice_view_printers';
    public static $icon                        = 'fa-fw ti ti-printer';
    protected $enable_emaintenance_data_import = true;

    public static function getName(): string
    {
        return _t('Printer list');
    }

    public static function getMenuName(): string
    {
        return _t('Printers');
    }

    public static function getAdditionalMenuOptions()
    {
        return [
            'sortOrder' => 40,
        ];
    }

    public static function getTicketStatusDisplay($row_data): string
    {
        global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
        $export_color                  = $row_data['invoice_expiry_date_field'] < date("Y-m-d", strtotime("-14days")) ? '_red' : '_green';
        $operations_filter_description = urlencode("$row_data[printer_name] ($row_data[serial]) - $row_data[usage_address_field] - $row_data[supplier_name]");
        $createTicketParams            = "?items_id[Printer][0]=$row_data[printer_id]&_suppliers_id_assign=$row_data[supplier_id]&without_paper_field=1&no_travel_field=1";

        if (IserviceToolBox::inProfileArray(['client', 'superclient'])) {
            $createTicketParams .= "&_users_id_assign=" . IserviceToolBox::getUserIdByName('Cititor');
            $createTicketParams .= '&title=' . _t('Read counter - toner replacement');
            $createTicketParams .= '&content=' . _t('Read counter');
            $createTicketParams .= "&itilcategories_id=" . PluginIserviceTicket::getItilCategoryId('Sesizare externa');
        } else {
            $createTicketParams .= "&_users_id_assign=$row_data[tech_id]";
        }

        $actions = [
            'add' => [
                'link' => "ticket.form.php$createTicketParams",
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/app_add.png',
                'title' => __('New ticket'),
                'visible' => Session::haveRight('plugin_iservice_ticket_' . PluginIserviceTicket::MODE_CREATENORMAL, CREATE) || Session::haveRight('plugin_iservice_ticket_' . PluginIserviceTicket::MODE_READCOUNTER, CREATE),
            ],
            'move' => [],
            'hMarfa' => [
                'link' => $CFG_GLPI['root_doc'] . "/plugins/iservice/front/hmarfaexport.form.php?id=$row_data[printer_id]",
                'icon' => $CFG_GLPI['root_doc'] . "/plugins/iservice/pics/app_go$export_color.png",
                'title' => _t('hMarfa export'),
                'visible' => Session::haveRight('plugin_iservice_hmarfa', READ),
            ],
            'list_ticket' => [
                'link' => "views.php?view=Operations&operations0[printer_id]=$row_data[printer_id]&operations0[filter_description]=$operations_filter_description",
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/app_detail.png',
                'title' => _t('Operations list'),
                'visible' => Session::haveRight('plugin_iservice_view_operations', READ),
            ],
            'counters' => [
                'link' => "views.php?view=PrinterCounters&printercounters0[supplier_name]=" . urlencode($row_data['supplier_name']),
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/calculator.png',
                'title' => _t('Printer counters'),
                'visible' => Session::haveRight('plugin_iservice_view_printercounters', READ),
            ],
            'cartridges' => [
                'link' => "views.php?view=Cartridges&cartridges0[partner_name]=" . urlencode($row_data['supplier_name']) . "&cartridges0[filter_description]=" . urlencode($row_data['supplier_name']),
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/toolbox.png',
                'title' => _t('Installable cartridges'),
                'visible' => Session::haveRight('plugin_iservice_view_cartridges', READ),
                'onclick' => "ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/getPrinterCartridgesPopup.php?supplier_id=$row_data[supplier_id]&supplier_name=" . urlencode($row_data['supplier_name']) . "&printer_id=$row_data[printer_id]\", \"\", function(message) {\$(\"#popup_$row_data[printer_id]_\").html(message);});",
                'suffix' => "<div class='iservice-view-popup' id='popup_$row_data[printer_id]_'></div>",
            ],
            'invoices' => [
                'link' => "views.php?view=Partners&partners0[partener]=" . urlencode($row_data['supplier_name']) . "&partners0[nr_fac_nepla]=-1&partners0[nr_fac_nepla2]=-1&partners0[val_scad]=-1&partners0[zile_ult_pla]=-1&partners0[filter_description]=" . urlencode($row_data['supplier_name']),
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/price_alert.png',
                'title' => _t('Unpaid invoices'),
                'visible' => Session::haveRight('plugin_iservice_view_partners', READ),
            ],
            'cartridges_replaced' => [
                'link' => "views.php?view=Cartridges&cartridges0[printer_name]=" . urlencode($row_data['serial'])
                    . "&cartridges0[partner_name]=" . urlencode($row_data['supplier_name'])
                    . "&filtering=1"
                    . "&cartridges0[date_in]=" . urlencode(date('Y-m-d'))
                    . "&cartridges0[date_use]=" . urlencode(date('Y-m-d'))
                    . "&cartridges0[date_out]=" . urlencode(date('Y-m-d'))
                    . "&cartridges0[date_use_null]=0"
                    . "&cartridges0[date_out_null]=0",
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/toolbox_blue.png',
                'title' => _t('Replaced cartridges'),
                'visible' => Session::haveRight('plugin_iservice_view_cartridges', READ),
            ],
        ];

        if (($movement_id = PluginIserviceMovement::getOpenFor('Printer', $row_data['printer_id'])) !== false) {
            $actions['move'] = [
                'link' => "movement.form.php?id=$movement_id",
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/cog_red.png',
                'title' => "Mutare nefinalizată",
                'visible' => Session::haveRight('plugin_iservice_movement', READ),
            ];
        } elseif (($last_ticket_id = PluginIserviceTicket::getLastIdForPrinterOrSupplier(0, $row_data['printer_id'], true)) > 0) {
            $actions['move']                = [
                'link' => "ticket.form.php?id=$last_ticket_id",
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/cog_orange.png',
                'title' => "Există tichet deschis",
                'visible' => Session::haveRight('plugin_iservice_movement', READ),
            ];
            $actions['add']['link_onclick'] = "onclick='return confirm(\"Există deja un tichet deschis. Doriți să continuați?\");'";
        } else {
            $actions['move'] = [
                'link' => "movement.form.php?itemtype=Printer&items_id=$row_data[printer_id]",
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/cog.png',
                'title' => _t('Move') . " " . _t('Printer'),
                'visible' => Session::haveRight('plugin_iservice_movement', READ),
            ];
        }

        $out = "<div id='row_actions_$row_data[printer_id]' class='actions collapsible' style='display:none'>";

        if (IserviceToolBox::inProfileArray(['client', 'superclient'])) {
            $actionIcons      = '';
            $actionIconsCount = 0;
        }

        foreach ($actions as $action) {
            $actionOut = '';
            if (!isset($action['visible']) || $action['visible']) {
                if (isset($action['onclick'])) {
                    if ($action['onclick'] !== 'ajaxCall') {
                        $actionOut .= "<img class='noprint view_action_button' src='$action[icon]' alt='$action[title]' title='$action[title]' style='cursor: pointer;' onclick='$action[onclick]'>\r\n";
                    } else {
                        $actionOut .= "<img class='noprint view_action_button' src='$action[icon]' alt='$action[title]' title='$action[title]' style='cursor: pointer;' onclick='ajaxCall(\"$action[link]\", \"$action[confirm]\", $action[success]);'>\r\n";
                    }
                } else {
                    $onclick    = $action['link_onclick'] ?? '';
                    $actionOut .= "<a href='$action[link]' $onclick target='_blank'><img class='noprint view_action_button' src='$action[icon]' alt='$action[title]' title='$action[title]'></a>";
                }

                if (isset($action['suffix'])) {
                    $actionOut .= $action['suffix'];
                }

                if (IserviceToolBox::inProfileArray(['client', 'superclient'])) {
                    $actionIcons .= $actionOut;
                    $actionIconsCount++;
                } else {
                    $out .= $actionOut;
                }
            }
        }

        if (IserviceToolBox::inProfileArray(['client', 'superclient'])) {
            $out  = "<div class='actions' style='min-width: " . $actionIconsCount * 34 . "px;'>";
            $out .= "$actionIcons&nbsp;";
            if (!empty($row_data['ticket_status'])) {
                $out .= "&nbsp;" . Ticket::getStatusIcon($row_data['ticket_status']);
            }
        } else {
            $out .= "<br><div id='ajax_selector_$row_data[printer_id]'></div>";
        }

        $out .= "</div>";

        if (!empty($row_data['ticket_status']) && !IserviceToolBox::inProfileArray(['client', 'superclient'])) {
            $out .= "&nbsp;" . Ticket::getStatusIcon($row_data['ticket_status']);
        }

        return $out;
    }

    public static function getTicketStatusDisplayForExport($row_data): string|bool
    {
        return Ticket::getStatus($row_data['ticket_status']);
    }

    public static function getTicketPrinterDisplay($row_data, $import_data): string
    {
        global $CFG_GLPI;
        $href    = $CFG_GLPI['root_doc'] . "/front/printer.form.php?id=$row_data[printer_id]";
        $title   = "Număr contact: $row_data[contact_num]\r\nContact: $row_data[contact]\r\nObservații: $row_data[comment]\r\n\r\nLocatie: $row_data[location_complete_name]";
        $printer = $row_data['printer_name'];
        if ($row_data['emaintenance']) {
            $title   .= "\r\n\r\nE-Maintenance " . ($row_data['disableem'] ? 'dezactivat' : 'activ');
            $em_title = '';
            $em_color = 'inherit';
            if (!empty($import_data) && !isset($import_data[$row_data['spaceless_serial']])) {
                $em_color = 'red';
                $em_title = 'Aparatul nu există în fișierul de import';
            } elseif ($row_data['disableem']) {
                $em_color = 'orange';
                $em_title = 'Aparatul este exclus din EM';
            }

            $printer .= " <span style='color:$em_color' title='$em_title'>[EM]</span>";
        } elseif (isset($import_data[$row_data['spaceless_serial']])) {
            $printer .= "<span style='color:deeppink' title='Aparatul există în fișierul de import, dar nu este setat în iService'> [EM]</span>";
        }

        if ($row_data['cm_field']) {
            $printer .= " [CM]";
            $title   .= "\r\n\r\nManagement de cartușe activ";
            $style    = "color: green;";
        } else {
            $style = '';
        }

        if (stripos($row_data['external_user'], 'C_') === 0 || stripos($row_data['external_user'], 'S_') === 0 || !empty($row_data['supergroup'])) {
            // if (!empty($row_data['supergroup']) || !empty($row_data['supergroup'])) {
            $title   .= "\r\n";
            $printer .= " [";
            if (stripos($row_data['external_user'], 'C_') === 0 || stripos($row_data['external_user'], 'S_') === 0) {
                // if (!empty($row_data['supergroup'])) {
                $title   .= "\r\nUtilizator extern: $row_data[external_user]";
                $printer .= "U";
            }

            if (!empty($row_data['supergroup'])) {
                $title   .= "\r\nSupergrup: $row_data[supergroup]";
                $printer .= "G";
            }

            $printer .= "]";
            $style   .= 'font-style: italic;';
        }

        if ($row_data['qr_code'] ?? false) {
            $printer .= " [QR]";
            $title   .= "\r\n\r\nQR: " . $row_data['qr_code'];
        }

        if (!empty($style)) {
            $style = "style='$style'";
        }

        if (!Session::haveRight('plugin_iservice_interface_original', READ)) {
            return $printer;
        }

        $title = htmlentities($title, ENT_QUOTES);
        return "<a href='$href' title='$title' $style target='_blank'>$printer</a>";
    }

    public static function getSupplierDisplay($row_data, $import_data): string
    {
        $title  = "Tel: $row_data[supplier_tel]\r\nFax: $row_data[supplier_fax]\r\nObservații: $row_data[supplier_comment]\r\nEmail trimis facturi: $row_data[supplier_email_facturi]";
        $title .= self::getPartnerTitleBasedOnUnpaidInvoices((int) $row_data['numar_facturi_neplatite'], $row_data['unpaid_invoices_value'], 2);
        $style  = self::getPartnerStyleBasedOnUnpaidInvoices((int) $row_data['numar_facturi_neplatite']);

        if ($row_data['supplier_deleted']) {
            $style = "color: red";
            $title = "PARTENER ȘTERS!\r\n\r\n$title";
        }

        return "<span style='$style' title='$title'>$row_data[supplier_name]</span>";
    }

    public static function getLocationDisplay($row_data): string
    {
        if (empty($row_data['cm_field'])) {
            return $row_data['location_complete_name'] ?: '======';
        }

        $ticket                                   = new PluginIserviceTicket();
        $ticket->fields['items_id']['Printer'][0] = $row_data['printer_id'];
        $ticket->fields['_suppliers_id_assign']   = $row_data['supplier_id'];
        $changeable_cartridges                    = PluginIserviceCartridgeItem::getChangeablesForTicket($ticket);
        $title                                    = '';
        $style                                    = '';
        if (count($changeable_cartridges) > 0) {
            foreach ($changeable_cartridges as $cartridge) {
                $title .= $cartridge["name"];
                if (!empty($cartridge['location_name'])) {
                    $title .= " din locația $cartridge[location_completename]";
                }

                $title .= "\n$cartridge[cpt]\n\n";
            }
        } else {
            if (!empty($row_data['location_parent_id']) && 0 < count($compatible_cartridges = PluginIserviceCartridgeItem::getChangeablesForTicket($ticket, ['ignore_location' => true]))) {
                $title = _t('You have compatible cartridges at other locations:') . "\n";
                foreach ($compatible_cartridges as $cartridge) {
                    $title .= $cartridge["name"];
                    if (!empty($cartridge['location_name'])) {
                        $title .= " din locația $cartridge[location_completename]";
                    }

                    $title .= "\n$cartridge[cpt]\n\n";
                }

                $style = "style='color: orange'";
            } else {
                $title = _t('You have no compatible cartridges!');
                $style = "style='color: red'";
            }
        }

        return "<span title='$title' $style>" . ($row_data['location_complete_name'] ?: '======') . "</span>";
    }

    public static function getOtherSerialDisplay($row_data): string
    {
        if ($row_data['no_invoice_field']) {
            return "<span class='error' title='Aparat exclus din facturare'>" . $row_data['otherserial'] . "</span>";
        }

        return $row_data['otherserial'];
    }

    public static function getSerialDisplay($row_data): string
    {
        if (!Session::haveRight('plugin_iservice_printer', READ)) {
            return $row_data['serial'];
        }

        if (!empty($row_data['contact_gps_field'])) {
            $style = "style='color:blue;font-style:italic;'";
            $gps   = "\n(GPS: $row_data[contact_gps_field])";
        } else {
            $gps   = "";
            $style = "";
        }

        $link     = "<a href='printer.form.php?id=$row_data[printer_id]' title='" . _t('Manage printer') . "$gps' $style>$row_data[serial]</a>";
        $copyLink = IserviceToolBox::getSerialCopyButton($row_data['serial']);
        return $link . ' ' . $copyLink;
    }

    public static function getInvoiceExpiryDateFieldDisplay($row_data): string
    {
        return "<span title='Data factură: $row_data[invoice_date_field]'>" . date('Y-m-d', strtotime($row_data['invoice_expiry_date_field'])) . "</span>";
    }

    protected function getRightCondition($printer_table_alias = 'p.')
    {
        $right_condition = '';
        if (!Session::haveRight('plugin_iservice_ticket_all_printers', READ)) {
            $printer_conditions = [];
            if (Session::haveRight('plugin_iservice_ticket_own_printers', READ)) {
                $printer_conditions[] = "{$printer_table_alias}users_id = " . $_SESSION['glpiID'];
            }

            if (Session::haveRight('plugin_iservice_ticket_assigned_printers', READ)) {
                $printer_conditions[] = "{$printer_table_alias}users_id_tech = " . $_SESSION['glpiID'];
            }

            if (Session::haveRight('plugin_iservice_ticket_group_printers', READ) && !empty($_SESSION['glpigroups'])) {
                $printer_conditions[] = "{$printer_table_alias}groups_id IN (" . join(',', $_SESSION['glpigroups']) . ")";
            }

            if (count($printer_conditions) > 0) {
                $right_condition .= "AND (" . join(' OR ', $printer_conditions) . ')';
            }
        }

        return $right_condition;
    }

    protected function getSettings(): array
    {
        global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;

        $printer_counters_buttons = IserviceToolBox::inProfileArray('client') ? '' :
            "<a class='vsubmit' href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=PrinterCounters' target='_blank'>" . _t('Printer counters') . " v2</a>" .
            "<a class='vsubmit' href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=PrinterCountersV3' target='_blank'>" . _t('Printer counters') . " v3</a>";

        $import_button = self::inProfileArray('tehnician', 'admin', 'super-admin') ? PluginIserviceEmaintenance::getImportControl('Setează [EM] din CSV', IserviceToolBox::getInputVariable('import_file', '')) : '';
        if ($this->enable_emaintenance_data_import) {
            $this->import_data = PluginIserviceEmaintenance::getDataFromCsvs(PluginIserviceEmaintenance::getImportFilePaths(IserviceToolBox::getInputVariable('import_file', '')));
        }

        $import = IserviceToolBox::getArrayInputVariable('import');
        if (!empty($import)) {
            $printer_customfields = new PluginFieldsPrinterprintercustomfield();
            $emaintenance_query   = "select " . PluginIservicePrinter::getSerialFieldForEM() . " id, p.cfid customfield_id from glpi_plugin_iservice_printers p where p.em_field = 1";
            foreach (PluginIserviceDB::getQueryResult($emaintenance_query) as $id => $printer_customfield) {
                if (!array_key_exists($id, $this->import_data)) {
                    $printer_customfields->update(
                        [
                            'id' => $printer_customfield['customfield_id'],
                            'em_field' => 0
                        ]
                    );
                }
            }

            foreach ($this->import_data as $id => $import_data) {
                if (!empty($import_data['error'])) {
                    continue;
                }

                if (PluginIserviceDB::populateByQuery($printer_customfields, "JOIN glpi_printers p ON p.id = items_id and itemtype = 'Printer' AND p.is_deleted = 0 WHERE " . PluginIservicePrinter::getSerialFieldForEM() . " = '$id' LIMIT 1")) {
                    $printer_customfields->update(
                        [
                            'id' => $printer_customfields->getID(),
                            'em_field' => 1
                        ]
                    );
                }
            }
        }

        if (false !== ($cid = IserviceToolBox::getInputVariable('cid', false))) {
            $contract_title     = " pentru contractul " . IserviceToolBox::getInputVariable('contract_name');
            $contract_join      = "LEFT JOIN glpi_contracts_items ci on ci.items_id = p.id and ci.itemtype = 'Printer'";
            $contract_condition = "AND ci.contracts_id = $cid";
        } else {
            $contract_title     = '';
            $contract_join      = '';
            $contract_condition = '';
        }

        return [
            'name' => self::getName() . $contract_title,
            'prefix' => "
                        <div class='printonly'>
                            <table style='width:40%;float:right;'><tr>
                                <td style='width:20%'>Data efectiva</td>
                                <td style='border: 1px solid;width:80%;height:2em;'>&nbsp;</td>
                            </tr><tr>
                                <td style='width:20%'>Tehnician</td>
                                <td style='border: 1px solid;width:80%;height:2em;'>&nbsp;</td>
                            </tr></table>
                        </div>
                        <div style='clear:both'></div>
                        ",
            'insert_empty_rows' => [
                'count' => 1,
                'content' => "Observatii + valori contoare: ",
                'row_class' => 'printonly',
                'row_style' => 'height: 10em;',
                'cell_style' => 'border-top:1px solid #CCC;border-bottom:1px solid #CCC;',
            ],
            'query' => "
                        SELECT
                              p.id printer_id
                            , p.original_name printer_name
                            , p.otherserial
                            , p.serial
                            , " . PluginIservicePrinter::getSerialFieldForEM('p') . " spaceless_serial
                            , p.contact_num
                            , p.contact
                            , p.comment
                            , pt.name printer_type
                            , st.name printer_status
                            , plt.status ticket_status
                            , s.id supplier_id
                            , s.name supplier_name
                            , s.phonenumber supplier_tel
                            , s.fax supplier_fax
                            , s.comment supplier_comment
                            , s.is_deleted supplier_deleted
                            , l.completename location_complete_name
                            , l.locations_id location_parent_id
                            , u.id tech_id
                            , CONCAT(IFNULL(CONCAT(u.realname, ' '),''), IFNULL(u.firstname, '')) tech_name
                            , ue.name external_user
                            , g.completename supergroup
                            , plt.effective_date_field last_data_luc
                            , plt.total2_black_field last_total2_black
                            , p.invoiced_total_black_field
                            , plt.total2_color_field last_total2_color
                            , p.invoiced_total_color_field
                            , p.invoice_expiry_date_field
                            , CAST(p.invoice_date_field as DATE) invoice_date_field
                            , p.week_nr_field
                            , p.em_field emaintenance
                            , p.disable_em_field disableem
                            , p.contact_gps_field
                            , p.usage_address_field
                            , p.no_invoice_field
                            , s.email_for_invoices_field supplier_email_facturi
                            , s.cm_field
                            , t2.codbenef
                            , t2.numar_facturi_neplatite
                            , t2.unpaid_invoices_value
                            , qr.code as qr_code
                        FROM glpi_plugin_iservice_printers p
                        LEFT JOIN glpi_plugin_iservice_printers_last_tickets plt ON plt.printers_id = p.id
                        LEFT JOIN glpi_printertypes pt ON pt.id = p.printertypes_id
                        LEFT JOIN glpi_infocoms i ON i.items_id = p.id and i.itemtype = 'Printer'
                        LEFT JOIN glpi_plugin_iservice_suppliers s ON s.id = i.suppliers_id
                        LEFT JOIN glpi_locations l ON l.id = p.locations_id
                        LEFT JOIN glpi_users u ON u.id = p.users_id_tech
                        LEFT JOIN glpi_users ue ON ue.id = p.users_id
                        LEFT JOIN glpi_groups g ON g.id = p.groups_id
                        LEFT JOIN glpi_states st ON st.id = p.states_id
                        LEFT JOIN glpi_plugin_iservice_qrs qr ON qr.items_id = p.id and qr.itemtype = 'Printer'
                        LEFT JOIN (SELECT codbenef, count(codbenef) numar_facturi_neplatite,  sum(valinc-valpla) unpaid_invoices_value
                                   FROM hmarfa_facturi 
                                   WHERE (codl = 'F' OR stare like 'V%') AND tip like 'TF%'
                                   AND valinc-valpla > 0
                                   GROUP BY codbenef) t2 ON t2.codbenef = s.hmarfa_code_field
                        $contract_join
                        WHERE p.is_deleted = 0 {$this->getRightCondition()} $contract_condition
                            AND p.original_name LIKE '[printer_name]'
                            AND p.otherserial LIKE '[otherserial]'
                            AND p.serial LIKE '[serial]'
                            AND ((s.name is null AND '[supplier_name]' = '%%') OR s.name LIKE '[supplier_name]')
                            AND ((p.usage_address_field is null AND '[usage_address_field]' = '%%') OR p.usage_address_field LIKE '[usage_address_field]')
                            AND ((l.completename is null AND '[printer_location]' = '%%') OR l.completename LIKE '[printer_location]')
                            AND ((st.name is null AND '[printer_status]' = '%%') OR st.name LIKE '[printer_status]')
                            AND ((p.week_nr_field is null AND '[week_nr_field]' = '%%') OR p.week_nr_field LIKE '[week_nr_field]')
                            [tech_id]
                            [supplier_id]
                            [em_disabled]
                        GROUP BY p.id
                        ",
            'default_limit' => 30,
            'id_field' => 'printer_id',
            'itemtype' => 'printer',
            'mass_actions' => [
                'group_read' => [
                    'caption' => _t('Global read counter'),
                    'action' => 'views.php?view=GlobalReadCounter',
                ],
                'group_read_extended' => [
                    'caption' => _t('Global read counter extended'),
                    'action' => 'views.php?view=GlobalReadCounter',
                ],
                'mass_invoice' => [
                    'caption' => 'Facturează',
                    'action' => 'hmarfaexport.form.php?mode=3',
                    'visible' => !self::inProfileArray('superclient', 'client'),
                ],
                'available_cartridges' => [
                    'caption' => _t('Cartridges available'),
                    'action' => $CFG_GLPI['root_doc'] . '/plugins/iservice/front/views.php?view=Cartridges',
                    'visible' => self::inProfileArray('superclient', 'client'),
                ],
            ],
            'filters' => [
                'filter_buttons_prefix' => "$import_button $printer_counters_buttons",
                'supplier_id' => [
                    'type' => self::FILTERTYPE_HIDDEN,
                    'format' => "AND s.id = %d",
                ],
                'printer_name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'Nume',
                    'format' => '%%%s%%',
                    'header' => 'printer_name',
                ],
                'em_disabled' => [
                    'type' => self::FILTERTYPE_CHECKBOX,
                    'format' => 'AND p.disable_em_field = 1',
                    'header' => 'printer_name',
                    'header_caption' => 'Doar cele excluse din EM ',
                    'visible' => !self::inProfileArray('subtehnician', 'superclient', 'client'),
                ],
                'supplier_name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'Partener',
                    'format' => '%%%s%%',
                    'header' => 'supplier_name',
                ],
                'tech_id' => [
                    'type' => self::FILTERTYPE_SELECT,
                    'caption' => 'Responsabil',
                    'format' => 'AND u.id = %d',
                    'header' => 'tech_name',
                    'visible' => !in_array($_SESSION["glpiactiveprofile"]["name"] ?? '', ['subtehnician', 'superclient', 'client']),
                    'options' => IserviceToolBox::getUsersByProfiles(['tehnician']),
                ],
                'otherserial' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'Număr inventar',
                    'format' => '%%%s%%',
                    'header' => 'otherserial',
                ],
                'serial' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'Număr serie',
                    'format' => '%%%s%%',
                    'header' => 'serial',
                ],
                'printer_status' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'Status',
                    'format' => '%%%s%%',
                    'header' => 'printer_status',
                ],
                'usage_address_field' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => _t('Usage address'),
                    'format' => '%%%s%%',
                    'header' => 'usage_address_field',
                ],
                'week_nr_field' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'week_nr_field',
                ],
                'printer_location' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'Locație',
                    'format' => '%%%s%%',
                    'header' => 'location_complete_name',
                ],
            ],
            'columns' => [
                'ticket_status' => [
                    'title' => '',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\Printers::getTicketStatusDisplay($row);',
                    'export_format' => 'function:\GlpiPlugin\Iservice\Views\Printers::getTicketStatusDisplayForExport($row);',
                    'align' => 'center',
                    'class' => 'noprint no-wrap',
                    'style' => 'min-width: 4em;',
                ],
                'printer_name' => [
                    'title' => 'Nume',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\Printers::getTicketPrinterDisplay($row, $this->import_data);',
                ],
                'supplier_name' => [
                    'title' => 'Partener',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\Printers::getSupplierDisplay($row, $this->import_data);',
                    'link' => [
                        'href' => $CFG_GLPI['root_doc'] . '/front/supplier.form.php?id=[supplier_id]',
                        'target' => '_blank',
                        'visible' => Session::haveRight('plugin_iservice_interface_original', READ),
                    ]
                ],
                'location_complete_name' => [
                    'title' => 'Locație',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\Printers::getLocationDisplay($row);',
                    'visible' => !self::inProfileArray('client'),
                ],
                'usage_address_field' => [
                    'title' => _t('Usage address'),
                    'editable' => true,
                    'edit_settings' => [
                        'callback' => 'managePrinter',
                        'operation' => 'set_usage_address_field'
                    ]
                ],
                'tech_name' => [
                    'title' => 'Responsabil',
                    'link' => [
                        'href' => $CFG_GLPI['root_doc'] . '/front/user.form.php?id=[tech_id]',
                        'visible' => Session::haveRight('plugin_iservice_interface_original', READ),
                    ],
                    'visible' => !self::inProfileArray('client'),
                ],
                'otherserial' => [
                    'title' => 'Număr inventar',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\Printers::getOtherSerialDisplay($row);',
                    'visible' => self::inProfileArray('tehnician', 'admin', 'super-admin'),
                ],
                'serial' => [
                    'title' => 'Număr serie',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\Printers::getSerialDisplay($row);',
                ],
                'last_data_luc' => [
                    'title' => 'Data lucrare u.i.',
                    'default_sort' => 'DESC',
                    'style' => 'white-space: nowrap;',
                ],
                'last_total2_black' => [
                    'title' => 'Black2 u.i.',
                ],
                'invoiced_total_black_field' => [
                    'title' => 'Black2 facturat',
                    'visible' => !self::inProfileArray('client'),
                ],
                'last_total2_color' => [
                    'title' => 'Color2 u.i.',
                ],
                'invoiced_total_color_field' => [
                    'title' => 'Color2 facturat',
                    'visible' => !self::inProfileArray('client'),
                ],
                'invoice_expiry_date_field' => [
                    'title' => 'Data exp.<br>factură',
                    'style' => 'white-space: nowrap;',
                    'format' => 'function:default',  // This will call \GlpiPlugin\Iservice\Views\Printers::getInvoiceExpiryDateFieldDisplay($row).
                    'visible' => !self::inProfileArray('client'),
                ],
                'week_nr_field' => [
                    'title' => 'Nr. săpt.',
                    'class' => 'noprint',
                    'align' => 'center',
                    'visible' => !self::inProfileArray('client'),
                ],
                'printer_status' => [
                    'title' => 'Status',
                    'visible' => !self::inProfileArray('client'),
                ],
                'ticket_number' => [
                    'title' => 'Numar tichet',
                    'value' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
                    'class' => 'printonly',
                    'style' => 'border: 1px solid;min-height:2em;',
                    'header_style' => '',
                    'footer_style' => '',
                ],
            ],
        ];
    }

}

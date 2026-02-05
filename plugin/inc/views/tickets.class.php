<?php

// Imported from iService2, needs refactoring. Original file: "Tickets.php".
namespace GlpiPlugin\Iservice\Views;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use PluginIserviceHmarfa;
use PluginIserviceMovement;
use Ticket;
use \CommonITILActor;
use \PluginIserviceDB;
use \PluginIserviceOrderStatus;
use \PluginIserviceTicket;
use \Session;

class Tickets extends View
{
    public static $rightname = 'plugin_iservice_view_tickets';

    public static $icon = 'fa-fw ti ti-alert-circle';

    public static function getName(): string
    {
        return _t('Ticket list');
    }

    public static function getMenuName(): string
    {
        return _n('Ticket', 'Tickets', Session::getPluralNumber());
    }

    public static function getAdditionalMenuOptions()
    {
        return [
            'sortOrder' => 20,
        ];
    }

    public static function getMenuContent(): array
    {
        if (!Session::haveRight(static::$rightname, READ)) {
            return [];
        }

        global $CFG_PLUGIN_ISERVICE;

        return [
            'title' => static::getMenuName(),
            'page' => "$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=" . substr(strrchr(static::class, "\\"), 1) . "&v=2",
            'icon'  => static::$icon,
            'options' => static::getAdditionalMenuOptions() ?: [],
        ];
    }

    public static function getTicketStatusDisplay($row_data, ?string $version = null): string
    {
        global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
        switch ($row_data['ticket_export_type']) {
        case 'factura':
        case 'aviz':
        case 'comanda':
            $externally_ordered = intval($row_data['externally_ordered_consumables']);
            $ordered            = intval($row_data['ordered_consumables']);
            $received           = intval($row_data['received_consumables']);
            if ($row_data['status'] == Ticket::EVALUATION) {
                if ($received === $ordered) {
                    $export_color   = '_yellow';
                    $export_comment = 'Piese recepționate';
                } elseif ($externally_ordered < $ordered) {
                    if ($externally_ordered === 0) {
                        $export_color   = '_red';
                        $export_comment = 'Piesele nu sunt comandate încă';
                    } else {
                        $export_color   = '_orange';
                        $export_comment = 'Nu sunt comandate încă toate piesele';
                    }
                } elseif ($received === 0) {
                    $export_color   = '_blue';
                    $export_comment = 'Piese comandate';
                } elseif ($received < $ordered) {
                    $export_color   = '_cyan';
                    $export_comment = 'Nu sunt recepționate încă toate piesele';
                }
            } elseif ($row_data['ticket_exported']) {
                $export_color   = '_green';
                $export_comment = 'Export efectuat';
            } elseif ($received === $ordered && $ordered != 0) {
                $export_color   = '_yellow';
                $export_comment = 'Piese recepționate';
            } else {
                $export_color   = '_red';
                $export_comment = 'Export neefectuat';
            }

            $export_link = $CFG_GLPI['root_doc'] . "/plugins/iservice/front/hmarfaexport.form.php?id=$row_data[ticket_id]&mode=" . PluginIserviceHmarfa::EXPORT_MODE_TICKET;
            break;
        default:
            $export_color   = '_gray';
            $export_comment = '';
            $export_link    = '#';
            break;
        }

        $list_ticket_description = urlencode(empty($row_data['printer_id']) ? "Toate" : "$row_data[printer_name] ($row_data[printer_serial]) - $row_data[usage_address_field] - $row_data[supplier_name]");
        $actions                 = [
            'printers' => [
                'link' => "views.php?view=Printers&printers0[supplier_id]=$row_data[supplier_id]&printers0[filter_description]=" . urlencode($row_data['supplier_name']),
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/printer.png',
                'title' => "Lista aparate " . htmlentities($row_data['supplier_name'], ENT_QUOTES),
                'visible' => Session::haveRight('plugin_iservice_view_printers', READ),
            ],
            'close' => [
                'link' => "ticket.form.php?id=$row_data[ticket_id]",
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/app_check.png',
                'title' => _t('Close ticket'),
                'visible' => Session::haveRight('plugin_iservice_ticket_' . PluginIserviceTicket::MODE_CLOSE, UPDATE) || $row_data['users_id_recipient'] === $_SESSION['glpiID'],
            ],
            'ticketreport' => [
                'link' => $CFG_GLPI['root_doc'] . "/plugins/iservice/front/ticket.report.php?id=$row_data[ticket_id]",
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/app_exp.png',
                'title' => __('Generate', 'iservive') . ' ' . _t('intervention report'),
                'visible' => Session::haveRight('plugin_iservice_docgenerator', READ),
            ],
            'list_ticket' => [
                'link' => $row_data['printer_id'] ? "views.php?view=Operations&operations0[printer_id]=$row_data[printer_id]&operations0[filter_description]=$list_ticket_description" : 'javascript:void(0);',
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/app_detail' . ($row_data['printer_id'] ? '' : '_disabled') . '.png',
                'title' => $row_data['printer_id'] ? _t('Operations list') : 'Tichetul nu are aparat', // Lista lucrari.
                'visible' => Session::haveRight('plugin_iservice_view_operations', READ),
            ],
            'counters' => [
                'link' => "views.php?view=PrinterCounters" . ($row_data['supplier_id'] ? "&printercounters0[supplier_name]=" . urlencode($row_data['supplier_name']) : '' ),
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/calculator.png',
                'title' => _t('Printer counters'),
                'visible' => Session::haveRight('plugin_iservice_view_printercounters', READ),
            ],
            'cartridges' => [
                'link' => "views.php?view=Cartridges&cartridges0[partner_name]=" . urlencode($row_data['supplier_name']) . "&cartridges0[filter_description]=" . urlencode($row_data['supplier_name']),
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/toolbox.png',
                'title' => _t('Installable cartridges'),
                'visible' => Session::haveRight('plugin_iservice_view_cartridges', READ),
                'onclick' => "ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/getPrinterCartridgesPopup.php?supplier_id=$row_data[supplier_id]&supplier_name=" . urlencode($row_data['supplier_name']) . "&printer_id=$row_data[printer_id]&ticket_id=$row_data[ticket_id]\", \"\", function(message) {\$(\"#popup_$row_data[printer_id]_$row_data[ticket_id]\").html(message);});",
                'suffix' => "<div class='iservice-view-popup' id='popup_$row_data[printer_id]_$row_data[ticket_id]'></div>",
            ],
            'invoices' => [
                'link' => "views.php?view=Partners" . ($row_data['supplier_id'] ? "&partners0[partener]=" . urlencode($row_data['supplier_name']) : '') . "&partners0[nr_fac_nepla]=-1&partners0[nr_fac_nepla2]=-1&partners0[val_scad]=-1&partners0[zile_ult_pla]=-1" . ($row_data['supplier_id'] ? "&partners0[filter_description]=" . urlencode($row_data['supplier_name']) : ''),
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/price_alert.png',
                'title' => _t('Unpaid invoices'),
                'visible' => Session::haveRight('plugin_iservice_view_partners', READ),
            ],
            'cartridges_replaced' => [
                'link' => "views.php?view=Cartridges&cartridges0[printer_name]=" . urlencode($row_data['printer_serial'])
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
            $actions['movement'] = [
                'link' => "movement.form.php?id=$movement_id",
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/cog_red.png',
                'title' => "Mutare nefinalizată",
                'visible' => Session::haveRight('plugin_iservice_movement', READ),
            ];
        }

        $out = '';
        if ($version != 'mobile') {
            $out .= "<div class='actions collapsible' style='display:none;'>";
            foreach ($actions as $action) {
                if (!isset($action['visible']) || $action['visible']) {
                    if (isset($action['onclick'])) {
                        if ($action['onclick'] !== 'ajaxCall') {
                            $out .= "<img class='noprint view_action_button' src='$action[icon]' alt='$action[title]' title='$action[title]' style='cursor: pointer;' onclick='$action[onclick]'>\r\n";
                        } else {
                            $out .= "<img class='noprint view_action_button' src='$action[icon]' alt='$action[title]' title='$action[title]' style='cursor: pointer;' onclick='ajaxCall(\"$action[link]\", \"$action[confirm]\", $action[success])'>\r\n";
                        }
                    } else {
                        $out .= "<a href='$action[link]' target='_blank'><img class='noprint view_action_button' src='$action[icon]' alt='$action[title]' title='$action[title]'></a>";
                    }

                    if (isset($action['suffix'])) {
                        $out .= $action['suffix'];
                    }
                }
            }

            $out .= "</div>";
        }

        $out .= "&nbsp;" . Ticket::getStatusIcon($row_data['status']);

        $out .= "<a href='" . $actions['close']['link'] . "' class='btn btn-sm ms-1'>" . _t('Details') . "</a>";

        return $out;
    }

    public static function getTicketIdDisplay($row_data): string
    {
        $title   = '';
        $href    = '';
        $class   = '';
        $display = $row_data['ticket_id'];

        switch ($row_data['ticket_export_type']) {
        case 'factura':
        case 'aviz':
        case 'comanda':
            if ($row_data['ordered_consumables']) {
                $title = _t('See internal orders');
                $href  = "views.php?view=Intorders&intorders0[order_status]=1,2,3,4,5&intorders0[ticket_id]=$row_data[ticket_id]";
            }
        }

        if (!empty($row_data['document_id'])) {
            $title   .= !empty($title) ? "\n" : '';
            $title   .= _t('Ticket has attached document(s)');
            $class   .= 'fw-bold';
            $display .= ' <i class="fa fa-paperclip" aria-hidden="true"></i>';
        }

        return !empty($href) ? "<a href='$href' title='$title' class='$class'>$display</a>" : "<span title='$title' class='$class'>$display</span>";
    }

    public static function getTicketNameDisplay($rowData): string
    {
        global $CFG_GLPI;
        $tooltip  = !empty($rowData['ticket_content']) ? IserviceToolBox::cleanHtml($rowData['ticket_content']) : '';
        $tooltip .= !empty($rowData['ticket_content']) && !empty($rowData['ticket_followups']) ? "\n\n" : '';
        $tooltip .= !empty($rowData['ticket_followups']) ? "Descriere followup-uri:\n" . IserviceToolBox::cleanHtml($rowData['ticket_followups']) : '';
        $href     = $CFG_GLPI['root_doc'] . '/front/ticket.form.php?id=[ticket_id]';

        $extraClass = '';
        if (!empty($rowData['ticket_content']) || !empty($rowData['ticket_followups'])) {
            $extraClass = "text-red'";
        }

        if (Session::haveRight('plugin_iservice_interface_original', READ)) {
            return "<a href='$href' title='$tooltip' class='text-decoration-none $extraClass'>$rowData[ticket_name]</a>";
        }

        return "<span title='$tooltip' class='text-decoration-none $extraClass'>[ticket_name]</span>";
    }

    public static function getDateOpenDisplay($rowData): string
    {
        if (!empty($rowData['no_travel_field'])) {
            return "<span class='text-danger' title='" . _t('No travel') . "'>$rowData[date_open]</span>";
        }

        return $rowData['date_open'];
    }

    public static function getTicketAssignTechDisplay($row_data): string
    {
        global $CFG_GLPI;
        if ($row_data['tech_assign_name'] != $row_data['tech_park_name']) {
            $color = 'color:blue;';
        } else {
            $color = '';
        }

        $row_data['tech_assign_name'] = empty($row_data['tech_assign_name']) ? '&nbsp;' : $row_data['tech_assign_name'];

        $title = !empty($row_data['tech_assign_name']) ? "Tehnician alocat: $row_data[tech_assign_name]" : '';
        if (!empty($row_data['tech_park_name'])) {
            $title .= "\nTehnician park: $row_data[tech_park_name]";
        }

        if (!empty($row_data['observer_name'])) {
            $title .= "\nObservator: $row_data[observer_name]";
            $style  = 'font-weight:bold;font-style:italic;';
        } else {
            $style = '';
        }

        if (Session::haveRight('plugin_iservice_interface_original', READ)) {
            return "<a href='$CFG_GLPI[root_doc]/front/user.form.php?id=$row_data[tech_assign_id]' style='$style$color' title='$title'>$row_data[tech_assign_name]</a>";
        } else {
            return "<span style='$style$color' title='$title'>$row_data[tech_assign_name]</span>";
        }
    }

    public static function getSerialDisplay($row_data): ?string
    {
        if (!Session::haveRight('plugin_iservice_printer', READ)) {
            return $row_data['printer_serial'];
        }

        $link = "<a href='printer.form.php?id=$row_data[printer_id]' title='" . _t('Manage printer') . "'>$row_data[printer_serial]</a>";
        if (isset($row_data['printer_gps']) && !empty($row_data['printer_gps'])) {
            $link = "<span style='color:blue;'><i>$link</i></span>";
        }

        $copyLink = IserviceToolBox::getSerialCopyButton($row_data['printer_serial']);
        return $link . ' ' . $copyLink;
    }

    public static function getSupplierDisplay($row_data)
    {
        $title = self::getPartnerTitleBasedOnUnpaidInvoices((int) $row_data['numar_facturi_neplatite'], $row_data['unpaid_invoices_value']);
        $style = self::getPartnerStyleBasedOnUnpaidInvoices((int) $row_data['numar_facturi_neplatite']);

        return "<span style='$style' title='$title'>$row_data[supplier_name]</span>";
    }

    public static function getPrinterNameDisplay($row_data)
    {
        $label = $row_data['printer_name'];
        $title = '';
        if ($row_data['qr_code']) {
            $label .= " [QR]";
            $title .= "QR: " . $row_data['qr_code'];
        }

        if (Session::haveRight('plugin_iservice_interface_original', READ)) {
            return "<a href='printer.form.php?id=$row_data[printer_id]' title='$title'>$label</a>";
        } else {
            return "<span title='$title'>$label</span>";
        }
    }

    public static function getTicketConsumablesDisplay($row_data): string
    {
        $row_data['plugin_fields_ticketexporttypedropdowns_id'] = $row_data['ticket_export_type'] ?? '';
        return Operations::getTicketConsumablesDisplay($row_data);
    }

    protected function getSettings(): array
    {
        global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;

        $ticket_status_options = [
            '1,2,3,4,5,6,9' => '---',
            '1,2,3,4,5,9' => 'Neinchise',
        ];
        foreach (PluginIserviceTicket::getAllStatusArray() as $status_id => $status_name) {
            $ticket_status_options[$status_id] = $status_name;
        }

        $effective_date_start_pre_widget =
            "<input type='button' class='submit' onclick='$(\"[name=\\\"tickets0[effective_date_start]\\\"]\").val(\"" . date("Y-m-d", strtotime("-6 months")) . "\")' value='ultimele 6 luni'>" .
            "<input type='button' class='submit ms-1' onclick='$(\"[name=\\\"tickets0[effective_date_start]\\\"]\").val(\"" . date("Y-m-d", strtotime("-30 days")) . "\")' value='ultimele 30 zile'>" .
            "<input type='button' class='submit ms-1' onclick='$(\"[name=\\\"tickets0[effective_date_start]\\\"]\").val(\"" . date("Y-m-d", strtotime("-7 days")) . "\")' value='ultimele 7 zile'>";

        $right_condition = '';
        if (!Session::haveRight('plugin_iservice_ticket_all_printers', READ)) {
            $printer_conditions = [];
            if (Session::haveRight('plugin_iservice_ticket_own_printers', READ)) {
                $printer_conditions[] = "p.users_id = " . $_SESSION['glpiID'];
            }

            if (Session::haveRight('plugin_iservice_ticket_assigned_printers', READ)) {
                $printer_conditions[] = "p.users_id_tech = " . $_SESSION['glpiID'];
            }

            if (Session::haveRight('plugin_iservice_ticket_group_printers', READ) && !empty($_SESSION['glpigroups']) && is_array($_SESSION['glpigroups'])) {
                $printer_conditions[] = "p.groups_id IN (" . join(',', $_SESSION['glpigroups']) . ")";
            }

            if (count($printer_conditions) > 0) {
                $right_condition .= "AND (" . join(' OR ', $printer_conditions) . ')';
            }
        }

        $prefix = '';
        if (Session::haveRight('plugin_iservice_view_emaintenance', READ)) {
            $em_count_data = PluginIserviceDB::getQueryResult('SELECT count(1) count FROM glpi_plugin_iservice_ememails where `read` = 0');
            $em_count      = empty($em_count_data[0]['count']) ? '0' : "<span style='color:red;'>{$em_count_data[0]['count']}</span>";
            $prefix       .= "<a href='views.php?view=Emaintenance' target='_blank'>Număr emailuri E-maintenance necitite: $em_count</a>";
        }

        if (Session::haveRight('plugin_iservice_interface_original', READ)) {
            $nm_count_data = PluginIserviceDB::getQueryResult('SELECT count(1) count FROM glpi_notimportedemails');
            $nm_count      = empty($nm_count_data[0]['count']) ? '0' : "<span style='color:red;'>{$nm_count_data[0]['count']}</span>";
            $prefix       .= "&nbsp;|&nbsp;<a href='$CFG_GLPI[root_doc]/front/notimportedemail.php' target='_blank'>Număr emailuri neimportate: $nm_count</a>";
        }

        if (Session::haveRight('plugin_iservice_view_movements', READ)) {
            $mo_count_data = PluginIserviceDB::getQueryResult('SELECT count(1) count FROM glpi_plugin_iservice_movements m where m.moved = 0');
            $mo_count      = empty($mo_count_data[0]['count']) ? '0' : "<span style='color:red;'>{$mo_count_data[0]['count']}</span>";
            $prefix       .= "&nbsp;|&nbsp;<a href='views.php?view=Movements&movements0[finalized]=0' target='_blank'>Număr mutări nefinalizate: $mo_count</a>";
        }

        $querySelect = "
                        SELECT
                              t.status
                            , t.id ticket_id
                            , t.name ticket_name
                            , t.content ticket_content
                            , coalesce(t.total2_black_field, 0) + coalesce(t.total2_color_field, 0) ticket_counter_total
                            , t.total2_black_field ticket_counter_black
                            , t.total2_color_field ticket_counter_color
                            , t.plugin_fields_ticketexporttypedropdowns_id ticket_export_type
                            , t.exported_field ticket_exported
                            , p.id printer_id
                            , CONCAT(p.name_and_location, CASE WHEN p.em_field = 1 THEN ' [EM]' ELSE '' END, CASE WHEN s.cm_field = 1 THEN ' [CM]' ELSE '' END) printer_name
                            , p.usage_address_field
                            , l.completename printer_location
                            , s.id supplier_id
                            , s.name supplier_name
                            , t.date date_open
                            , CASE t.effective_date_field WHEN '0000-00-00 00:00:00' THEN NULL WHEN '0000-00-00' THEN NULL ELSE t.effective_date_field END effective_date_field
                            , u.id tech_park_id
                            , CONCAT(IFNULL(CONCAT(u.realname, ' '),''), IFNULL(u.firstname, '')) tech_park_name
                            , a.id tech_assign_id
                            , CONCAT(IFNULL(CONCAT(a.realname, ' '),''), IFNULL(a.firstname, '')) tech_assign_name
                            , o.id observer_id
                            , CASE WHEN o.name IS NULL THEN NULL ELSE CONCAT(IFNULL(CONCAT(o.realname, ' '),''), IFNULL(o.firstname, '')) END observer_name
                            , p.serial printer_serial
                            , oc.ordered_consumables
                            , rc.received_consumables
                            , eoc.externally_ordered_consumables
                            , GROUP_CONCAT(CONCAT(CAST(tf.date AS CHAR), '\n', tf.content) SEPARATOR '\n') ticket_followups
                            , t2.numar_facturi_neplatite
                            , t2.unpaid_invoices_value
                            , t.users_id_recipient
                            , qr.code as qr_code
                            , di.id document_id
                            , p.status_name printer_status
                            , t.no_travel_field";
        $queryFrom   = "
                        FROM glpi_plugin_iservice_tickets t
                        LEFT JOIN glpi_documents_items di ON di.items_id = t.id AND di.itemtype = 'Ticket'
                        LEFT JOIN glpi_itilcategories i ON i.id = t.itilcategories_id
                        LEFT JOIN glpi_items_tickets it ON it.tickets_id = t.id AND it.itemtype = 'Printer'
                        LEFT JOIN glpi_plugin_iservice_printers p ON p.id = it.items_id
                        LEFT JOIN glpi_plugin_iservice_qrs qr ON qr.items_id = p.id and qr.itemtype = 'Printer'
                        LEFT JOIN glpi_locations l ON l.id = t.locations_id
                        LEFT JOIN glpi_suppliers_tickets st ON st.tickets_id = t.id AND st.type = " . CommonITILActor::ASSIGN . "
                        LEFT JOIN glpi_plugin_iservice_suppliers s ON s.id = st.suppliers_id
                        LEFT JOIN glpi_users u ON u.id = p.users_id_tech
                        LEFT JOIN glpi_tickets_users tu ON tu.tickets_id = t.id AND tu.type = " . CommonITILActor::ASSIGN . "
                        LEFT JOIN glpi_users a ON a.id = tu.users_id
                        LEFT JOIN glpi_tickets_users tob ON tob.tickets_id = t.id AND tob.type = " . CommonITILActor::OBSERVER . "
                        LEFT JOIN glpi_users o ON o.id = tob.users_id
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
                        LEFT JOIN ( SELECT COUNT(io.id) externally_ordered_consumables, tickets_id
                                    FROM glpi_plugin_iservice_intorders io
                                                JOIN glpi_plugin_iservice_intorders_extorders ieo ON ieo.plugin_iservice_intorders_id = io.id
                                                WHERE NOT io.tickets_id IS NULL AND not ieo.plugin_iservice_extorders_id IS NULL
                                                GROUP BY tickets_id
                                  ) eoc ON eoc.tickets_id = t.id
                        LEFT JOIN glpi_itilfollowups tf ON tf.items_id = t.id and tf.itemtype = 'Ticket'" . (self::inProfileArray('tehnician', 'admin', 'super-admin') ? '' : " AND (tf.is_private = 0 OR tf.users_id = " . ($_SESSION['glpiID'] ?? 0) . ")") . "
                        LEFT JOIN (SELECT codbenef, count(codbenef) numar_facturi_neplatite,  sum(valinc-valpla) unpaid_invoices_value
                                   FROM hmarfa_facturi 
                                   WHERE (codl = 'F' OR stare like 'V%') AND tip like 'TF%'
                                   AND valinc-valpla > 0
                                   GROUP BY codbenef) t2 ON t2.codbenef = s.hmarfa_code_field
        ";

        $version      = str_replace('v', '',  preg_replace('/.*\s/', '', IserviceToolBox::getInputVariable('v', 1)));
        $otherVersion = $version == 1 ? 2 : 1;

        $settingsV1 = [
            'name' => self::getName() . ($version === 'mobile' ? '' : " v$version"),
            'prefix' => $prefix,
            'query' => $querySelect . $queryFrom . "
                        WHERE t.is_deleted = 0 $right_condition
                            AND t.status in ([ticket_status])
                            AND CAST(t.id AS CHAR) LIKE '[ticket_id]'
                            AND t.name LIKE '[ticket_name]'
                            AND ((p.name_and_location IS NULL AND '[printer_name]' = '%%') OR p.name_and_location LIKE '[printer_name]')
                            AND ((p.usage_address_field is null AND '[usage_address_field]' = '%%') OR p.usage_address_field LIKE '[usage_address_field]')
                            AND ((s.name IS NULL AND '[supplier_name]' = '%%') OR s.name LIKE '[supplier_name]')
                            AND ((p.serial IS NULL AND '[printer_serial]' = '%%') OR p.serial LIKE '[printer_serial]')
                            AND t.date <= '[date_open]'
                            AND (t.effective_date_field IS NULL OR t.effective_date_field <= '[effective_date_field]')
                            AND ((p.status_name is null AND '[printer_status]' = '%%') OR p.status_name LIKE '[printer_status]')
                            [effective_date_start]
                            [unlinked]
                            [tech_id]
                            [assigned_only]
                            [observer_id]
                            [printer_id]
                            [no_travel]
                            [without_paper]
                        GROUP BY t.id
                        ",
            'default_limit' => self::inProfileArray('subtehnician', 'superclient', 'client') ? 20 : 50,
            'filters' => [
                'prefix' => "<input type='submit' class='submit noprint me-1' name='v' value='" . _t('Tickets') . " v$otherVersion' onclick='this.form.action=\"views.php?view=Tickets&v=$otherVersion\"'>" ,
                'printer_id' => [
                    'type' => self::FILTERTYPE_HIDDEN,
                    'format' => 'AND p.id = %d'
                ],
                'effective_date_start' => [
                    'type' => self::FILTERTYPE_LABEL,
                    'caption' => '',
                    'class' => 'mx-1',
                    'format' => "AND ((t.effective_date_field IS NULL AND '%1\$s' = '') OR t.effective_date_field >= '%1\$s 00:00:00')",
                    'style' => 'width: 6em;',
                    'pre_widget' => $effective_date_start_pre_widget,
                    'visible' => !self::inProfileArray('subtehnician', 'superclient', 'client'),
                ],
                'effective_date_field' => [
                    'type' => self::FILTERTYPE_DATE,
                    'caption' => '< Data efectivă <',
                    'class' => 'mx-1',
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => date('Y-m-d'),
                    'visible' => !self::inProfileArray('subtehnician', 'superclient', 'client'),
                ],
                'observer_id' => [
                    'type' => self::FILTERTYPE_USER,
                    'caption' => 'Observator',
                    'class' => 'mx-1',
                    'format' => 'AND o.id = %d',
                    'visible' => !self::inProfileArray('subtehnician', 'superclient', 'client'),
                ],
                'unlinked' => [
                    'type' => self::FILTERTYPE_CHECKBOX,
                    'caption' => 'Fără partener sau aparat',
                    'class' => 'mx-1',
                    'format' => 'AND (p.name_and_location is null OR s.name is null)',
                    'visible' => !self::inProfilearray('subtehnician', 'superclient', 'client'),
                ],
                'date_open' => [
                    'type' => self::FILTERTYPE_DATE,
                    'caption' => 'Data deschiderii <',
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => date('Y-m-d'),
                    'header' => 'date_open',
                    'header_caption' => '< ',
                ],
                'ticket_status' => [
                    'type' => self::FILTERTYPE_SELECT,
                    'caption' => 'Stare tichet',
                    'options' => $ticket_status_options,
                    'empty_value' => '1,2,3,4,5,' . (self::inProfileArray('subtehnician', 'superclient', 'client') ? '6,9' : '9'),
                    'header' => 'status',
                ],
                'ticket_id' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'Număr',
                    'format' => '%%%s%%',
                    'header' => 'ticket_id',
                ],
                'ticket_name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'Titlu',
                    'format' => '%%%s%%',
                    'header' => 'ticket_name',
                ],
                'printer_name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'Nume aparat',
                    'format' => '%%%s%%',
                    'header' => 'printer_name',
                ],
                'supplier_name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'Partener',
                    'format' => '%%%s%%',
                    'header' => 'supplier_name',
                ],
                'usage_address_field' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => _t('Usage address'),
                    'format' => '%%%s%%',
                    'header' => 'usage_address_field',
                ],
                'tech_id' => [
                    'type' => self::FILTERTYPE_SELECT,
                    'caption' => 'Tehnician alocat',
                    'format' => 'AND (a.id = %d OR a.id IS NULL)',
                    'header' => 'tech_assign_name',
                    'visible' => !self::inProfileArray('subtehnician', 'superclient', 'client'),
                    'options' => IserviceToolBox::getUsersByProfiles(['tehnician']),
                ],
                'no_travel' => [
                    'type' => self::FILTERTYPE_CHECKBOX,
                    'format' => 'AND NOT t.no_travel_field = 1',
                    'class' => 'mx-1',
                    'caption' => _t('Exclude no travel'),
                    'visible' => !self::inProfileArray('subtehnician', 'superclient', 'client'),
                ],
                'without_paper' => [
                    'type' => self::FILTERTYPE_CHECKBOX,
                    'format' => 'AND NOT t.without_paper_field = 1',
                    'class' => 'mx-1',
                    'caption' => _t('Exclude without papers'),
                    'visible' => !self::inProfileArray('subtehnician', 'superclient', 'client'),
                ],
                'assigned_only' => [
                    'type' => self::FILTERTYPE_CHECKBOX,
                    'format' => 'AND NOT a.id IS NULL',
                    'header' => 'tech_assign_name',
                    'header_caption' => 'Doar alocate ',
                    'visible' => !self::inProfileArray('subtehnician', 'superclient', 'client'),
                ],
                'printer_serial' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'Număr serie',
                    'format' => '%%%s%%',
                    'header' => 'printer_serial',
                ],
                'printer_status' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'Status',
                    'format' => '%%%s%%',
                    'header' => 'printer_status',
                ],
            ],
            'columns' => [
                'status' => [
                    'title' => 'Stare tichet',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\Tickets::getTicketStatusDisplay($row);',
                    'align' => 'center',
                ],
                'ticket_id' => [
                    'title' => 'Număr',
                    'align' => 'center',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\Tickets::getTicketIdDisplay($row);',
                ],
                'ticket_name' => [
                    'title' => 'Titlu',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\Tickets::getTicketNameDisplay($row);',
                ],
                'printer_name' => [
                    'title' => 'Nume aparat',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\Tickets::getPrinterNameDisplay($row);',
                    'empty' => [
                        'value' => '---',
                        'link' => [
                            'href' => 'ticket.form.php?id=[ticket_id]&mode=' . PluginIserviceTicket::MODE_MODIFY,
                            'visible' => Session::haveRight('plugin_iservice_ticket_' . PluginIserviceTicket::MODE_MODIFY, READ),
                        ],
                    ],
                ],
                'supplier_name' => [
                    'title' => 'Partener',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\Tickets::getSupplierDisplay($row);',
                    'link' => [
                        'href' => $CFG_GLPI['root_doc'] . '/front/supplier.form.php?id=[supplier_id]',
                        'visible' => Session::haveRight('plugin_iservice_interface_original', READ),
                    ],
                    'empty' => [
                        'value' => '---',
                        'link' => [
                            'href' => 'ticket.form.php?id=[ticket_id]&mode=' . PluginIserviceTicket::MODE_MODIFY,
                            'visible' => Session::haveRight('plugin_iservice_ticket_' . PluginIserviceTicket::MODE_MODIFY, READ),
                        ],
                    ],
                    'visible' => self::inProfileArray('tehnician', 'admin', 'super-admin'),
                ],
                'usage_address_field' => [
                    'title' => _t('Usage address'),
                ],
                'printer_serial' => [
                    'title' => 'Număr serie',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\Tickets::getSerialDisplay($row);',
                ],
                'date_open' => [
                    'title' => 'Data deschiderii',
                    'default_sort' => 'DESC',
                    'align' => 'center',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\Tickets::getDateOpenDisplay($row);',
                ],
                'effective_date_field' => [
                    'title' => 'Data efectivă',
                    'sort_default_dir' => 'DESC',
                    'align' => 'center',
                ],
                'tech_assign_name' => [
                    'title' => 'Tehnician alocat',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\Tickets::getTicketAssignTechDisplay($row);',
                ],
                'printer_status' => [
                    'title' => _t('Printer status'),
                    'visible' => !self::inProfileArray('client'),
                ],
                'ticket_counter_black' => [
                    'title' => 'Contor bk',
                ],
                'ticket_counter_color' => [
                    'title' => 'Contor color',
                ],
                'ticket_counter_total' => [
                    'title' => 'Contor total',
                ],
            ],
        ];

        if ($version == 2) {
            return $this->getSettingsV2($settingsV1, $querySelect, $queryFrom, $right_condition);
        } elseif ($version == 'mobile') {
            return $this->getSettingsVMobile($settingsV1, $querySelect, $queryFrom, $right_condition);
        }

        return $settingsV1;
    }

    private function getSettingsV2($settingsV1, $querySelect, $queryFrom, $right_condition)
    {
        $settingsV2 = $settingsV1;
        unset(
            $settingsV2['columns']['tech_assign_name'],
            $settingsV2['filters']['tech_id'],
            $settingsV2['filters']['assigned_only'],
            $settingsV2['columns']['effective_date_field'],
            $settingsV2['filters']['effective_date_field'],
            $settingsV2['columns']['printer_status'],
            $settingsV2['filters']['printer_status'],
            $settingsV2['columns']['ticket_counter_black'],
            $settingsV2['filters']['ticket_counter_black'],
            $settingsV2['columns']['ticket_counter_color'],
            $settingsV2['filters']['ticket_counter_color'],
            $settingsV2['columns']['ticket_counter_total'],
            $settingsV2['filters']['ticket_counter_total']
        );

        $settingsV2['filters']['filter_group_1']['tech_id'] = [
            'type' => self::FILTERTYPE_SELECT,
            'caption' => 'Tehnician alocat',
            'format' => 'AND (a.id = %d OR a.id IS NULL)',
            'visible' => !self::inProfileArray('subtehnician', 'superclient', 'client'),
            'options' => IserviceToolBox::getUsersByProfiles(['tehnician']),
            'class' => 'mx-1',
        ];

        $settingsV2['filters']['filter_group_1']['assigned_only'] = [
            'type' => self::FILTERTYPE_CHECKBOX,
            'format' => 'AND NOT a.id IS NULL',
            'caption' => 'Doar alocate ',
            'visible' => !self::inProfileArray('subtehnician', 'superclient', 'client'),
            'class' => 'mx-1',
        ];

        $settingsV2['filters']['ticket_content'] = [
            'type' => self::FILTERTYPE_TEXT,
            'caption' => 'Descriere',
            'format' => '%%%s%%',
            'header' => 'ticket_content',
        ];

        $settingsV2['filters']['ticket_followups']   = [
            'type' => self::FILTERTYPE_TEXT,
            'format' => '%%%s%%',
            'header' => 'ticket_followups',
        ];
        $settingsV2['filters']['ticket_consumables'] = [
            'type' => self::FILTERTYPE_TEXT,
            'format' => '%%%s%%',
            'header' => 'ticket_consumables',
        ];
        $settingsV2['filters']['ticket_cartridges']  = [
            'type' => self::FILTERTYPE_TEXT,
            'format' => '%%%s%%',
            'header' => 'ticket_cartridges',
        ];

        $settingsV2['columns'] = IserviceToolBox::insertArrayValuesAndKeysAfterKey(
            'date_open',
            $settingsV2['columns'],
            [
                'ticket_content' => [
                    'title' => 'Descriere',
                ],
                'ticket_followups' => [
                    'title' => 'Followupuri',
                ],
                'ticket_consumables' => [
                    'title' => 'Cartușe livrate',
                    'class' => 'no-wrap',
                    'align' => 'center',
                    'format' => 'function:default',  // This will call PluginIserviceView_Operations::getTicketConsumablesDisplay($row).
                ],
                'ticket_cartridges' => [
                    'title' => 'Cartușe instalate',
                    'align' => 'center'
                ],
            ]
        );

        $settingsV2['query'] = "SELECT * FROM ($querySelect" . "
                        , (SELECT GROUP_CONCAT(CONCAT(ct.plugin_iservice_consumables_id, '<br>(', TRIM(ct.amount) + 0, COALESCE(CONCAT(': ', REPLACE(ct.new_cartridge_ids, '|', '')), ''), ')') SEPARATOR '<br>') ticket_consumables
                           FROM glpi_plugin_iservice_consumables_tickets ct
                           WHERE ct.tickets_id = t.id) ticket_consumables
                        , ct.cartridges ticket_cartridges
                        $queryFrom
                        LEFT JOIN ( SELECT cat.tickets_id, GROUP_CONCAT(CONCAT(ci.ref, '&nbsp;<span title=\"', IF(ci.plugin_fields_cartridgeitemtypedropdowns_id IN (2,3,4), t.total2_color_field, coalesce(t.total2_black_field, 0) + coalesce(t.total2_color_field, 0)), ' + (', ci.atc_field, ' * ', ci.life_coefficient_field, ')', '\">(pana&nbsp;', IF(ci.plugin_fields_cartridgeitemtypedropdowns_id IN (2,3,4), t.total2_color_field, t.total2_black_field + coalesce(t.total2_color_field, 0)) + ROUND(ci.atc_field * ci.life_coefficient_field), ')</span><br>[', c.id,COALESCE(CONCAT(' -> ',cat.cartridges_id_emptied),' -> <span style=\"color:red;\" title=\"nu golește nimic\">!!!</span>'),'] (', cid.completename, ')') SEPARATOR '<br>') cartridges
                                FROM glpi_plugin_iservice_cartridges_tickets cat
                                LEFT JOIN glpi_plugin_iservice_tickets t ON t.id = cat.tickets_id
                                LEFT JOIN glpi_cartridges c ON c.id = cat.cartridges_id
                                LEFT JOIN glpi_plugin_iservice_cartridge_items ci ON ci.id = c.cartridgeitems_id
                                LEFT JOIN glpi_plugin_fields_cartridgeitemtypedropdowns cid on cid.id = cat.plugin_fields_cartridgeitemtypedropdowns_id
                                GROUP BY cat.tickets_id
                              ) ct ON ct.tickets_id = t.id
                        WHERE t.is_deleted = 0 $right_condition
                            AND t.status in ([ticket_status])
                            AND CAST(t.id AS CHAR) LIKE '[ticket_id]'
                            AND t.name LIKE '[ticket_name]'
                            AND ((p.name_and_location IS NULL AND '[printer_name]' = '%%') OR p.name_and_location LIKE '[printer_name]')
                            AND ((p.usage_address_field is null AND '[usage_address_field]' = '%%') OR p.usage_address_field LIKE '[usage_address_field]')
                            AND ((s.name IS NULL AND '[supplier_name]' = '%%') OR s.name LIKE '[supplier_name]')
                            AND ((p.serial IS NULL AND '[printer_serial]' = '%%') OR p.serial LIKE '[printer_serial]')
                            AND t.date <= '[date_open]'
                            AND t.content LIKE '[ticket_content]'
                            
                            [effective_date_start]
                            [unlinked]
                            [tech_id]
                            [assigned_only]
                            [observer_id]
                            [printer_id]
                            [no_travel]
                            [without_paper]
                        GROUP BY t.id
                        ) t
                WHERE 1=1
                  AND ((t.ticket_followups IS null AND '[ticket_followups]' = '%%') OR t.ticket_followups LIKE '[ticket_followups]')
                  AND ((t.ticket_consumables IS null AND '[ticket_consumables]' = '%%') OR t.ticket_consumables LIKE '[ticket_consumables]')
                  AND ((t.ticket_cartridges IS null AND '[ticket_cartridges]' = '%%') OR t.ticket_cartridges LIKE '[ticket_cartridges]')
                ";

        return $settingsV2;
    }

    private function getSettingsVMobile($settingsV1, $querySelect, $queryFrom, $right_condition)
    {
        $settingsVMobile = $settingsV1;

        unset(
            $settingsVMobile['columns']['printer_name'],
            $settingsVMobile['columns']['effective_date_field'],
            $settingsVMobile['columns']['ticket_counter_black'],
            $settingsVMobile['columns']['ticket_counter_color'],
            $settingsVMobile['columns']['ticket_counter_total'],
            $settingsVMobile['columns']['date_open'],
            $settingsVMobile['filters']['prefix'],
            $settingsVMobile['filters']['printer_id'],
            $settingsVMobile['filters']['effective_date_start'],
            $settingsVMobile['filters']['effective_date_field'],
            $settingsVMobile['filters']['observer_id'],
            $settingsVMobile['filters']['unlinked'],
            $settingsVMobile['filters']['date_open'],
            $settingsVMobile['filters']['ticket_status']['header'],
            $settingsVMobile['filters']['ticket_name'],
            $settingsVMobile['filters']['printer_name'],
            $settingsVMobile['filters']['supplier_name']['header'],
            $settingsVMobile['filters']['usage_address_field'],
            $settingsVMobile['filters']['tech_id']['header'],
            $settingsVMobile['filters']['no_travel'],
            $settingsVMobile['filters']['without_paper'],
            $settingsVMobile['filters']['assigned_only'],
            $settingsVMobile['filters']['printer_serial']['header'],
            $settingsVMobile['filters']['printer_status'],
        );

        $settingsVMobile['filters']['ticket_status']['class']  = 'mx-1';
        $settingsVMobile['filters']['supplier_name']['class']  = 'mx-1';
        $settingsVMobile['filters']['printer_serial']['class'] = 'mx-1';
        $settingsVMobile['columns']['ticket_id']['format']     = 'function:\GlpiPlugin\Iservice\Views\Tickets::getTicketIdDisplay($row) . " / " . \GlpiPlugin\Iservice\Views\Tickets::getDateOpenDisplay($row);';
        $settingsVMobile['columns']['status']['format']        = 'function:\GlpiPlugin\Iservice\Views\Tickets::getTicketStatusDisplay($row, "mobile");';

        $settingsVMobile['columns'] = IserviceToolBox::insertArrayValuesAndKeysAfterKey(
            'ticket_name',
            $settingsVMobile['columns'],
            [
                'ticket_content' => [
                    'title' => 'Descriere',
                ],
                'ticket_followups' => [
                    'title' => 'Followupuri',
                ],
            ]
        );

        $settingsVMobile['columns'] = IserviceToolBox::insertArrayValuesAndKeysAfterKey(
            'printer_serial',
            $settingsVMobile['columns'],
            [
                'printer_model' => [
                    'title' => _t('Model'),
                ],
                'estimate_percentages' => [
                    'title' => _t('Available cartridges'),
                    'align' => 'center',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\Tickets::getCartridgesDisplay($row);',
                ],
            ]
        );

        $cacheTableExists = PluginIserviceDB::doesTableExists("glpi_plugin_iservice_cachetable_printercounters");
        $cacheTableSelect = $cacheTableExists ? ", pc.estimate_percentages, pc.stocks, pc.consumable_codes" : "";

        $settingsVMobile['query'] = $querySelect
            . ", p.original_name printer_model "
            . $cacheTableSelect
            . $queryFrom
            . ($cacheTableExists ? "LEFT JOIN glpi_plugin_iservice_cachetable_printercounters pc on p.id = pc.printer_id and pc.consumable_type = 'cartridge' " : "")
            . "
                        WHERE t.is_deleted = 0 $right_condition
                            AND t.status in ([ticket_status])
                            AND ((s.name IS NULL AND '[supplier_name]' = '%%') OR s.name LIKE '[supplier_name]')
                            AND ((p.serial IS NULL AND '[printer_serial]' = '%%') OR p.serial LIKE '[printer_serial]')
                            [tech_id]
                        GROUP BY t.id
                        ";

        return $settingsVMobile;
    }

    public static function getCartridgesDisplay($row)
    {
        if (empty($row['estimate_percentages']) && empty($row['stocks']) && empty($row['consumable_codes'])) {
            return _t('Cache table does not exist, open PrinterCounters V3 to create it.');
        }

        $html  = '<div>';
        $html .= '<table><tr><th style="text-align:left;">' . _t('hMarfa Code')
            . '</th><th style="text-align:right;">' . _t('Installed')
            . '</th><th style="text-align:right;">' . _t('Stock') . '</th></tr>';
        $html .= "<tr><td>" . $row['consumable_codes'] ?? '' . "</td>";
        $html .= "<td style='text-align:right;'>" . $row['estimate_percentages'] ?? '' . "</td>";
        $html .= "<td style='text-align:right;'>" . $row['stocks'] ?? '' . "</td></tr>";
        $html .= '</table>';
        $html .= '</div>';
        return $html;
    }

}

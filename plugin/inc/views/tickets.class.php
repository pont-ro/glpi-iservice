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

    public static function getTicketStatusDisplay($row_data): string
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
        ];

        if (($movement_id = PluginIserviceMovement::getOpenFor('Printer', $row_data['printer_id'])) !== false) {
            $actions['movement'] = [
                'link' => "movement.form.php?id=$movement_id",
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/cog_red.png',
                'title' => "Mutare nefinalizată",
                'visible' => Session::haveRight('plugin_iservice_movement', READ),
            ];
        }

        $out = "<div class='actions collapsible' style='display:none;'>";
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
        $out .= "&nbsp;" . Ticket::getStatusIcon($row_data['status']);

        return $out;
    }

    public static function getTicketIdDisplay($row_data): string
    {
        switch ($row_data['ticket_export_type']) {
        case 'factura':
        case 'aviz':
        case 'comanda':
            if ($row_data['ordered_consumables']) {
                return "<a title='Vezi comenzi interne' href='views.php?view=Intorders&intorders0[order_status]=1,2,3,4,5&intorders0[ticket_id]=$row_data[ticket_id]'>$row_data[ticket_id]</a>";
            }

            // Here is an intentioned fallthrough to default!
        default:
            return $row_data['ticket_id'];
        }
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

        return $link;
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

    protected function getSettings(): array
    {
        global $CFG_GLPI;

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

        return [
            'name' => self::getName(),
            'prefix' => $prefix,
            'query' => "
                        SELECT
                              t.status
                            , t.id ticket_id
                            , t.name ticket_name
                            , t.content ticket_content
                            , coalesce(t.total2_black_field, 0) + coalesce(t.total2_color_field, 0) ticket_counter_total
                            , t.total2_black_field ticket_counter_black
                            , t.total2_color_field ticket_counter_color
                            , i.name ticket_category
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
                        FROM glpi_plugin_iservice_tickets t
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
                            [effective_date_start]
                            [unlinked]
                            [tech_id]
                            [assigned_only]
                            [observer_id]
                            [printer_id]
                            [ticket_category]
                            [no_travel]
                            [without_paper]
                        GROUP BY t.id
                        ",
            'default_limit' => self::inProfileArray('subtehnician', 'superclient', 'client') ? 20 : 50,
            'filters' => [
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
                'ticket_category' => [
                    'type' => self::FILTERTYPE_SELECT,
                    'glpi_class' => 'ITILCategory',
                    'format' => 'AND i.id = %d',
                    'header' => 'ticket_category',
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
                    'tooltip' => "[ticket_content]\n\nDescriere followup-uri:\n[ticket_followups]",
                    'link' => [
                        'href' => $CFG_GLPI['root_doc'] . '/front/ticket.form.php?id=[ticket_id]',
                        'visible' => Session::haveRight('plugin_iservice_interface_original', READ),
                    ]
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
                'ticket_category' => [
                    'title' => 'Categorie'
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
    }

}

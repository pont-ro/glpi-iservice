<?php

// Imported from iService2, needs refactoring. Original file: "Cartridges.php".
namespace GlpiPlugin\Iservice\Specialviews;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use GlpiPlugin\Iservice\Views\View;
use \CommonITILActor;
use \PluginIserviceTicket;
use \Session;

class Cartridges extends View
{
    public static $rightname = 'plugin_iservice_view_cartridges';

    public static function getRowBackgroundClass($row_data): string
    {
        if (!empty($row_data['date_out'])) {
            return empty($row_data['date_use']) ? 'bg_cartridge_revoked' : 'bg_cartridge_emptied';
        } else {
            return empty($row_data['date_use']) ? 'bg_cartridge_available' : 'bg_cartridge_installed';
        }
    }

    public static function getIdDisplay($row_data): string
    {
        global $CFG_GLPI;
        $ajax_link = $CFG_GLPI['root_doc'] . "/plugins/iservice/ajax/manageCartridge.php?id=$row_data[id]";
        $actions   = [
            /**
            'remove_from_partner' => array(
                'link' => "$ajax_link&operation=remove_from_partner",
                'success' => 'function(message) {if(message !== "' . IserviceToolBox::RESPONSE_OK . '") {alert(message);} else {alert("' . __("Cartridge deleted from evidence", "iservice") . '");$("#row_actions_' . $row_data['id'] . '").closest("tr").remove();}}',
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/bin_closed.png',
                'title' => __('Delete from partner', 'iservice'),
                'confirm' => "Sigur vreți să ștergeți cartușul $row_data[id]? Toate date legate de acest cartuș se vor pierde!",
                'visible' => self::inProfileArray('admin', 'super-admin'),
                'onclick' => 'ajaxCall',
            ),
            /**/
            'change_location' => [
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/app_down.png',
                'title' => __('Change location', 'iservice'),
                'visible' => self::inProfileArray('client', 'superclient', 'tehnician', 'admin', 'super-admin'),
                'onclick' => "ajaxCall(\"$CFG_GLPI[root_doc]/plugins/iservice/ajax/getLocationDropdown.php?supplier_id=$row_data[partner_id]&cartridge_id=$row_data[id]&location_id=$row_data[location_id]\", \"\", function(message) {\$(\"#popup_$row_data[id]_\").html(message);});",
                'suffix' => "<div class='iservice-view-popup' id='popup_$row_data[id]_'></div>",
            ],
            'add_to_printer' => [
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/app_add.png',
                'title' => __('Add to printer', 'iservice'),
                'onclick' => ($row_data['printer_name']) ? "alert(\"Cartuș instalat deja\");" : "ajaxCall(\"$CFG_GLPI[root_doc]/plugins/iservice/ajax/getPrinterDropdown.php?supplier_id=$row_data[partner_id]&cartridge_id=$row_data[id]\", \"\", function(message) {\$(\"#ajax_selector_$row_data[id]\").html(message);});",
            ],
            'delete_cartridge' => [
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/bin_closed.png',
                'title' => __('Delete cartridge', 'iservice'),
                'visible' => $_SESSION['glpiID'] == 8 && $row_data['ticket_id'] == null,
                'onclick' => "ajaxCall(\"$CFG_GLPI[root_doc]/plugins/iservice/ajax/manageCartridge.php?id=$row_data[id]&operation=delete_cartridge\", \"Sigur vreți să ștergeți cartușul $row_data[id]?\", function(message) {if(message !== \"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {alert(\"" . __("Cartridge deleted from database", "iservice") . "\");\$(\"#row_actions_$row_data[id]\").closest(\"tr\").remove();}});",
            ],
            /**
            'remove_from_printer' => array(
                'link' => "$ajax_link&operation=remove_from_printer",
                'success' => 'function(message) {if(message !== "' . IserviceToolBox::RESPONSE_OK . '") {alert(message);} else {$("form").submit();}}',
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/app_delete.png',
                'title' => __('Uninstall from printer', 'iservice'),
                'confirm' => "Cartușul va fi șters de pe tichetul de instalare!\\n\\nSigur vreți să dezinstalați acest cartuș de pe imprimanta $row_data[printer_name]?",
                'visible' => self::inProfileArray('tehnician', 'admin', 'super-admin'),
                'onclick' => 'ajaxCall',
            ),
            'use' => array(
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/app_check.png',
                'title' => "Marchează golit",
                'visible' => self::inProfileArray('tehnician', 'admin', 'super-admin'),
                'onclick' => ($row_data['printer_name']) ? "ajaxCall(\"$CFG_GLPI[root_doc]/plugins/iservice/ajax/getCounters.php?cartridge_id=$row_data[id]&pages_use_field=$row_data[pages_use_field]&pages_color_use_field=$row_data[pages_color_use_field]\", \"\", function(message) {\$(\"#ajax_selector_$row_data[id]\").html(message);});" : "alert(\"" . sprintf(__("Cartridge %d is not installed on a printer", "iservice"), $row_data['id']) . "\");",
            ),
            /**/
        ];

        $out = "<div id='row_actions_$row_data[id]' class='actions'>$row_data[id] ";
        foreach ($actions as $action) {
            if (!isset($action['visible']) || $action['visible']) {
                if (isset($action['onclick']) && $action['onclick'] !== 'ajaxCall') {
                    $out .= "<img class='noprint view_action_button' src='$action[icon]' alt='$action[title]' title='$action[title]' style='cursor: pointer;' onclick='$action[onclick]'>\r\n";
                } else {
                    $out .= "<img class='noprint view_action_button' src='$action[icon]' alt='$action[title]' title='$action[title]' style='cursor: pointer;' onclick='ajaxCall(\"$action[link]\", \"$action[confirm]\", $action[success]);'>\r\n";
                }

                if (isset($action['suffix'])) {
                    $out .= $action['suffix'];
                }
            }
        }

        $out .= "<br><div id='ajax_selector_$row_data[id]'></div>";
        $out .= "<input type='hidden' name='item[cartridge_partners][$row_data[id]]' value='$row_data[partner_id]' />";
        $out .= "</div>";
        return $out;
    }

    public static function getRefDisplay($row_data): string
    {
        $result = "$row_data[ref]" . (empty($row_data['cartridge_type']) ? '' : "<br>$row_data[cartridge_type]");
        if (self::isRestrictedMode() || !Session::haveRight('plugin_iservice_interface_original', READ)) {
            return $result;
        }

        global $CFG_GLPI;
        $link = $CFG_GLPI['root_doc'] . "/front/cartridgeitem.form.php?id=$row_data[cartridgeitem_id]";
        if ($row_data['ref'] != $row_data['consumable_id']) {
            $style = "style='color:red'";
            $title = "$row_data[ref] != $row_data[consumable_id]";
        } elseif (!empty($row_data['type_id']) && !in_array($row_data['type_id'], explode(',', $row_data['supported_types']))) {
            $style = "style='color:red'";
            $title = "Tipul cartușului ($row_data[type_id] - $row_data[cartridge_type]) este invalid.\nTipuri suportate: $row_data[supported_types]\n";
        } else {
            $style = "";
            $title = "$row_data[consumable_id]";
        }

        $title .= "\nMercury code (type): $row_data[mercury_code] (" . (empty($row_data['cartridge_type']) ? 'nedeterminat' : $row_data['cartridge_type']) . ")";
        $title .= "\nLifetime (real lifetime): $row_data[atc_field] (" . $row_data['atc_field'] * $row_data['life_coefficient_field'] . ")";
        return "<a href='$link' $style title='$title'>$result</a>";
    }

    public static function getCompatiblePrintersDisplay($row_data): string
    {
        $class = $row_data['compatible_printers'] ? "" : "class='error'";
        return "<span $class title='$row_data[compatible_printer_names]'>$row_data[compatible_printers]</span>";
    }

    public static function getPrinterNameDisplay($row_data): string
    {
        if ($row_data['printer_deleted']) {
            return "<span style='color:red' title='Aparat șters'>$row_data[printer_name]</span>";
        } else {
            return $row_data['printer_name'];
        }
    }

    public static function getInstallerTicketIdDisplay($row_data): string
    {
        if (self::isRestrictedMode()) {
            return $row_data['installer_ticket_id'];
        }

        global $CFG_PLUGIN_ISERVICE;
        if ($row_data['saved_installer_ticket_id'] !== $row_data['installer_ticket_id']) {
            $style = "style='color:red;'";
            $title = "$row_data[saved_installer_ticket_id] != $row_data[installer_ticket_id]";
        } else {
            $style = '';
            $title = '';
        }

        $ticket_id = empty($row_data['installer_ticket_id']) ? $row_data['saved_installer_ticket_id'] : $row_data['installer_ticket_id'];
        return "<a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=$ticket_id&mode=" . PluginIserviceTicket::MODE_CLOSE . "' $style title='$title'>$ticket_id</a>";
    }

    public static function getTicketIdDisplay($row_data): string
    {
        if (self::isRestrictedMode()) {
            return $row_data['ticket_id'];
        }

        global $CFG_GLPI;
        $link  = $CFG_GLPI['root_doc'] . "/plugins/iservice/front/ticket.form.php?id=$row_data[ticket_id]&mode=" . PluginIserviceTicket::MODE_CLOSE;
        $style = ($row_data['id_partener_livrare'] != $row_data['id_partener_instalare'] && !empty($row_data['date_use'])) ? "style='color:red'" : "";
        $title = "Partener livrare: " . htmlspecialchars($row_data['partener_livrare']) . "($row_data[id_partener_livrare])";
        if (!empty($row_data['id_partener_instalare'])) {
            $title .= "\r\nPartener instalare: " . htmlspecialchars($row_data['partener_instalare']) . " ($row_data[id_partener_instalare])";
        } else {
        }

        return "<a href='$link' $style title='$title' target='_blank'>$row_data[ticket_id]</a>";
    }

    public static function getPrintedPagesDisplay($row_data): string
    {
        if (strtolower($row_data['printer_type']) == 'alb-negru') {
            $value = $row_data['printed_pages_field'];
        } elseif ($row_data['ref'][0] === 'C') {
            $value = in_array($row_data['type_id'], [2, 3, 4]) ? $row_data['printed_pages_color_field'] : $row_data['total_printed_pages'];
        } else {
            $value = $row_data['total_printed_pages'];
        }

        return sprintf("<span title='Copii bk: %s\r\nCopii color: %s\r\nTotal  copii: %s'>%s</span>", $row_data['printed_pages_field'], $row_data['printed_pages_color_field'], $row_data['total_printed_pages'], $value);
    }

    public static function getDateOutDisplay($row_data): ?string
    {
        if (empty($row_data['date_out'])) {
            return '';
        }

        if (empty($row_data['saved_out_ticket_id']) || self::isRestrictedMode()) {
            return $row_data['date_out'];
        }

        $cartridges = IserviceToolBox::getQueryResult(
            "
            select c.id
            from glpi_cartridges c
            join glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cfci on cfci.items_id = c.cartridgeitems_id and cfci.itemtype = 'CartridgeItem'
            join glpi_plugin_iservice_cartridges_tickets ct on ct.cartridges_id = c.id
            where cfci.mercury_code_field in ($row_data[compatible_mercury_codes])
              and cfci.plugin_fields_cartridgeitemtypedropdowns_id = $row_data[type_id]
              and ct.tickets_id = $row_data[saved_out_ticket_id]
            "
        );
        $cartridge  = array_shift($cartridges);
        return "$row_data[date_out]<br>" . ($row_data['date_use'] ? "<span title='Id cartuș golire'>$cartridge[id]</span>" : "");
    }

    public static function isRestrictedMode()
    {
        return !self::inProfileArray('tehnician', 'admin', 'super-admin');
    }

    protected function getSettings(): array
    {
        global $CFG_GLPI;

        if (null !== ($printer_model_id = IserviceToolBox::getInputVariable('pmi'))) {
            $printer_model_join = "INNER JOIN glpi_cartridgeitems_printermodels cip ON cip.cartridgeitems_id = ci.id AND cip.printermodels_id = $printer_model_id";
        } else {
            $printer_model_join = '';
        }

        if (null !== ($accessible_printer_ids = \PluginIservicePrinter::getAccessibleIds())) {
            if ('' == ($accessible_printer_ids_list = implode(',', $accessible_printer_ids))) {
                $accessible_printer_ids_list = "0";
            }

            $partnter_filter    = "
                s.id IN (SELECT DISTINCT s1.id
                         FROM glpi_suppliers s1
                         INNER JOIN glpi_infocoms ic1 ON ic1.suppliers_id = s1.id AND ic1.itemtype = 'Printer'
                         INNER JOIN glpi_printers p1 ON p1.id = ic1.items_id
                         WHERE p1.id IN ($accessible_printer_ids_list)
                           AND ((s.name IS null AND '[partner_name]' = '%%') OR s.name LIKE '[partner_name]')
                        )
                ";
            $restriction_filter = "
                AND c.id IN (SELECT c1.id
                         FROM glpi_plugin_iservice_cartridges c1
                         LEFT JOIN glpi_locations l1 ON l1.id = c1.locations_id_field
                         LEFT JOIN glpi_cartridgeitems_printermodels cp1 ON cp1.cartridgeitems_id = c1.cartridgeitems_id
                         LEFT JOIN glpi_printers p1 ON p1.printermodels_id = cp1.printermodels_id
                         LEFT JOIN glpi_locations pl1 ON pl1.id = p1.locations_id
                         WHERE p1.id IN ($accessible_printer_ids_list)
                           AND (   (l1.locations_id = pl1.locations_id)
                                OR ((l1.id = 0 OR l1.id is null) AND (pl1.id = 0 OR pl1.id is null))
                               )
                        )
                ";
        } else {
            $partnter_filter    = "((s.name IS null AND '[partner_name]' = '%%') OR s.name LIKE '[partner_name]')";
            $restriction_filter = "";
        }

        $settings = [
            'name' => _n('Cartridge', 'Cartridges', Session::getPluralNumber()),
            'instant_display' => self::isRestrictedMode(),
            'query' => "
                        SELECT
                            c.id 
                          , ci.plugin_fields_cartridgeitemtypedropdowns_id type_id
                          , c.date_in
                          , c.date_use
                          , c.tickets_id_use_field saved_installer_ticket_id
                          , c.date_out
                          , c.tickets_id_out_field saved_out_ticket_id
                          , c.pages_use_field
                          , c.pages_color_use_field
                          , c.printed_pages_field
                          , c.printed_pages_field_color
                          , c.printed_pages_field + c.printed_pages_field_color total_printed_pages
                          , ci.id cartridgeitem_id
                          , ci.name
                          , ci.ref
                          , ci.mercury_code_field mercury_code
                          , ci.compatible_mercury_codes_field compatible_mercury_codes
                          , ci.supported_types_field supported_types
                          , ci.atc_field
                          , ci.life_coefficient_field
                          , ctd.name cartridge_type
                          , s.id partner_id
                          , s.name partner_name
                          , l.id location_id
                          , l.name location_name
                          , ll.id location_parent_id
                          , ll.name location_parent_name
                          , p.is_deleted printer_deleted
                          , CONCAT (p.serial, ' (', p.name, ')') printer_name
                          , pt.name printer_type
                          , ct.plugin_iservice_consumables_id consumable_id
                          , ct.tickets_id ticket_id
                          , sl.id id_partener_livrare
                          , sl.name partener_livrare
                          , crt.tickets_id installer_ticket_id
                          , sc.id id_partener_instalare
                          , sc.name partener_instalare
                          , (SELECT `count` FROM glpi_plugin_iservice_consumable_compatible_printers_counts WHERE id = c.id) compatible_printers
                          , (SELECT pids  FROM glpi_plugin_iservice_consumable_compatible_printers_counts WHERE id = c.id) compatible_printer_names
                        FROM glpi_plugin_iservice_cartridges c
                        INNER JOIN glpi_plugin_iservice_cartridge_items ci ON ci.id = c.cartridgeitems_id
                        $printer_model_join
                        LEFT JOIN glpi_plugin_fields_cartridgeitemtypedropdowns ctd ON ctd.id = c.plugin_fields_cartridgeitemtypedropdowns_id
                        LEFT JOIN glpi_suppliers s ON s.id = c.suppliers_id_field
                        LEFT JOIN glpi_locations l ON l.id = c.locations_id_field
                        LEFT JOIN glpi_locations ll on ll.id = l.locations_id
                        LEFT JOIN glpi_printers p ON p.id = c.printers_id
                        LEFT JOIN glpi_printertypes pt ON pt.id = p.printertypes_id
                        LEFT JOIN glpi_plugin_iservice_consumables_tickets ct ON ct.amount > 0 AND ct.new_cartridge_ids LIKE CONCAT('%|', c.id, '|%')
                        LEFT JOIN glpi_suppliers_tickets stl ON stl.tickets_id = ct.tickets_id AND stl.type = " . CommonITILActor::ASSIGN . " 
                        LEFT JOIN glpi_suppliers sl ON sl.id = stl.suppliers_id
                        LEFT JOIN glpi_plugin_iservice_cartridges_tickets crt ON crt.cartridges_id = c.id
                        LEFT JOIN glpi_suppliers_tickets stc ON stc.tickets_id = crt.tickets_id AND stc.type = " . CommonITILActor::ASSIGN . "
                        LEFT JOIN glpi_suppliers sc on sc.id = stc.suppliers_id
                        WHERE c.id LIKE '[id]'
                          AND ci.ref LIKE '[ref]'
                          AND ci.name LIKE '[name]'
                          AND $partnter_filter
                          AND ((l.name IS null AND '[location_name]' = '%%') OR l.name LIKE '[location_name]')
                          AND ((ll.name IS null AND '[location_parent_name]' = '%%') OR ll.name LIKE '[location_parent_name]')
                          AND ((p.name IS null AND '[printer_name]' = '%%') OR p.name LIKE '[printer_name]' OR p.serial LIKE '[printer_name]')
                          AND ((ct.tickets_id IS null AND '[ticket_id]' = '%%') OR ct.tickets_id LIKE '[ticket_id]')
                          AND ((crt.tickets_id IS null AND '[installer_ticket_id]' = '%%') OR crt.tickets_id LIKE '[installer_ticket_id]')
                          AND ((c.tickets_id_out_field IS null AND '[saved_out_ticket_id]' = '%%') OR c.tickets_id_out_field LIKE '[saved_out_ticket_id]')
                          AND (c.date_in IS null OR c.date_in <= '[date_in]')
                          AND ([date_use_null] (c.date_use IS null and '[date_use]' = '1980-01-01 23:59:59') OR c.date_use <= '[date_use]')
                          AND ([date_out_null] (c.date_out IS null and '[date_out]' = '1980-01-01 23:59:59') OR c.date_out <= '[date_out]')
                          $restriction_filter
                        GROUP BY c.id
                        ",
            'default_limit' => 50,
            // 'show_limit' => !self::isRestrictedMode(),
            // 'show_filter_buttons' => !self::isRestrictedMode(),
            'use_cache' => false,
            'cache_timeout' => 43200, // 12 hours
            'cache_query' => "SELECT *  FROM {table_name}
                    WHERE id LIKE '[id]'
                      AND ref LIKE '[ref]'
                      AND name LIKE '[name]'
                      AND $partnter_filter
                      AND ((location_name IS null AND '[location_name]' = '%%') OR location_name LIKE '[location_name]')
                      AND ((location_parent_name IS null AND '[location_parent_name]' = '%%') OR location_parent_name LIKE '[location_parent_name]')
                      AND ((printer_name IS null AND '[printer_name]' = '%%') OR printer_name LIKE '[printer_name]')
                      AND ((ticket_id IS null AND '[ticket_id]' = '%%') OR ticket_id LIKE '[ticket_id]')
                      AND ((installer_ticket_id IS null AND '[installer_ticket_id]' = '%%') OR installer_ticket_id LIKE '[installer_ticket_id]')
                      AND ((saved_out_ticket_id IS null AND '[saved_out_ticket_id]' = '%%') OR saved_out_ticket_id LIKE '[saved_out_ticket_id]')
                      AND (date_in IS null OR date_in <= '[date_in]')
                      AND ([date_use_null] (date_use IS null and '[date_use]' = '1980-01-01 23:59:59') OR date_use <= '[date_use]')
                      AND ([date_out_null] (date_out IS null and '[date_out]' = '1980-01-01 23:59:59') OR date_out <= '[date_out]')
                      $restriction_filter
                ",
            'row_class' => 'function:PluginIserviceView_Cartridges::getRowBackgroundClass($row_data);',
            'filters' => [
                'filter_buttons_prefix' => // self::isRestrictedMode() ? '' :
                        " <input type='submit' class='submit noprint' name='filter' value='Toate' onclick='changeValByName(\"cartridges0[date_in]\", \"" . date('Y-m-d') . "\");changeValByName(\"cartridges0[date_use]\", \"" . date('Y-m-d') . "\");changeValByName(\"cartridges0[date_out]\", \"" . date('Y-m-d') . "\");changeValByName(\"cartridges0[date_use_null]\", 1);changeValByName(\"cartridges0[date_out_null]\", 1);'/>"
                        . " <input type='submit' class='submit noprint' name='filter' value='Neinstalate' onclick='changeValByName(\"cartridges0[date_in]\", \"" . date('Y-m-d') . "\");changeValByName(\"cartridges0[date_use]\", \"1980-01-01\");changeValByName(\"cartridges0[date_out]\", \"1980-01-01\");changeValByName(\"cartridges0[date_use_null]\", 1);changeValByName(\"cartridges0[date_out_null]\", 1);'/>"
                        . " <input type='submit' class='submit noprint' name='filter' value='Instalate' onclick='changeValByName(\"cartridges0[date_in]\", \"" . date('Y-m-d') . "\");changeValByName(\"cartridges0[date_use]\", \"" . date('Y-m-d') . "\");changeValByName(\"cartridges0[date_out]\", \"1980-01-01\");changeValByName(\"cartridges0[date_use_null]\", 0);changeValByName(\"cartridges0[date_out_null]\", 1);'/>"
                        . " <input type='submit' class='submit noprint' name='filter' value='Golite' onclick='changeValByName(\"cartridges0[date_in]\", \"" . date('Y-m-d') . "\");changeValByName(\"cartridges0[date_use]\", \"" . date('Y-m-d') . "\");changeValByName(\"cartridges0[date_out]\", \"" . date('Y-m-d') . "\");changeValByName(\"cartridges0[date_use_null]\", 0);changeValByName(\"cartridges0[date_out_null]\", 0);'/>"
                        . " <a class='vsubmit' onclick='$(\"#cartridges tr.result-row\").each(function() {if ($(this).find(\".error\").length === 0) {\$(this).hide();}});'>Cartușe neinstalabile</a>",
                'id' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'empty_format' => '%%%s%%',
                    'header' => 'id',
                    // 'visible' => !self::isRestrictedMode(),
                ],
                'ref' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'empty_format' => '%%%s%%',
                    'header' => 'ref',
                        // 'visible' => !self::isRestrictedMode(),
                ],
                'name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'empty_format' => '%%%s%%',
                    'header' => 'name',
                        // 'visible' => !self::isRestrictedMode(),
                ],
                'partner_name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'empty_format' => '%%%s%%',
                    'header' => 'partner_name',
                    // 'visible' => !self::isRestrictedMode(),
                ],
                'location_parent_name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'empty_format' => '%%%s%%',
                    'header' => 'location_parent_name',
                        // 'visible' => !self::isRestrictedMode(),
                ],
                'location_name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'empty_format' => '%%%s%%',
                    'header' => 'location_name',
                        // 'visible' => !self::isRestrictedMode(),
                ],
                'printer_name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'empty_format' => '%%%s%%',
                    'header' => 'printer_name',
                    // 'visible' => !self::isRestrictedMode(),
                ],
                'ticket_id' => [
                    'type' => self::FILTERTYPE_INT,
                    'format' => '%%%s%%',
                    'empty_format' => '%%%s%%',
                    'header' => 'ticket_id',
                    'visible' => !self::isRestrictedMode(),
                ],
                'date_in' => [
                    'type' => self::FILTERTYPE_DATE,
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => date('Y-m-d'),
                    'header' => 'date_in',
                    'header_caption' => '<= ',
                        // 'visible' => !self::isRestrictedMode(),
                ],
                'installer_ticket_id' => [
                    'type' => self::FILTERTYPE_INT,
                    'format' => '%%%s%%',
                    'empty_format' => '%%%s%%',
                    'header' => 'installer_ticket_id',
                    'visible' => !self::isRestrictedMode(),
                ],
                'date_use' => [
                    'type' => self::FILTERTYPE_DATE,
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => '1980-01-01',
                    'header' => 'date_use',
                    'header_caption' => '<= ',
                        // 'visible' => !self::isRestrictedMode(),
                ],
                'date_use_null' => [
                    'type' => self::FILTERTYPE_CHECKBOX,
                    'format' => 'c.date_use IS null OR',
                    'header_caption' => 'fără valoare ',
                    'header' => 'date_use',
                        // 'visible' => !self::isRestrictedMode(),
                    'cache_override' => [
                        'format' => 'date_use IS null OR',
                    ]
                ],
                'saved_out_ticket_id' => [
                    'type' => self::FILTERTYPE_INT,
                    'format' => '%%%s%%',
                    'empty_format' => '%%%s%%',
                    'header' => 'saved_out_ticket_id',
                    // 'visible' => !self::isRestrictedMode(),
                ],
                'date_out' => [
                    'type' => self::FILTERTYPE_DATE,
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => '1980-01-01',
                    'header' => 'date_out',
                    'header_caption' => '<= ',
                        // 'visible' => !self::isRestrictedMode(),
                ],
                'date_out_null' => [
                    'type' => self::FILTERTYPE_CHECKBOX,
                    'format' => 'c.date_out IS null OR',
                    'header_caption' => 'fără valoare ',
                    'header' => 'date_out',
                        // 'visible' => !self::isRestrictedMode(),
                    'cache_override' => [
                        'format' => 'date_out IS null OR',
                    ]
                ],
            ],
            'columns' => [
                'id' => [
                    'title' => 'ID',
                    'format' => 'function:default', // this will call PluginIserviceView_Cartridges::getIdDisplay($row);
                ],
                'ref' => [
                    'title' => 'Cod produs',
                    'format' => 'function:default', // this will call PluginIserviceView_Cartridges::getRefDisplay($row);
                    'align' => 'center',
                ],
                'compatible_printers' => [
                    'title' => 'Ap. comp.',
                    'visible' => !self::isRestrictedMode(),
                    'format' => 'function:default', // this will call PluginIserviceView_Cartridges::getCompatiblePrintersDisplay($row);
                    'align' => 'center',
                ],
                'name' => [
                    'title' => 'Nume',
                ],
                'partner_name' => [
                    'title' => 'Partener',
                ],
                'location_parent_name' => [
                    'title' => 'Locație părinte',
                ],
                'location_name' => [
                    'title' => 'Locație',
                ],
                'printer_name' => [
                    'title' => 'Instalat in aparat',
                    // 'visible' => !self::isRestrictedMode(),
                    'format' => 'function:default', // this will call PluginIserviceView_Cartridges::getPrinterNameDisplay($row);
                ],
                'ticket_id' => [
                    'title' => 'Nr.&nbsp;tichet livrare',
                    'align' => 'center',
                    'visible' => !self::isRestrictedMode(),
                    'format' => 'function:PluginIserviceView_Cartridges::getTicketIdDisplay($row);',
                ],
                'date_in' => [
                    'title' => 'Data livrării',
                    'align' => 'center',
                ],
                'installer_ticket_id' => [
                    'title' => 'Nr.&nbsp;tichet instalare',
                    'visible' => !self::isRestrictedMode(),
                    'align' => 'center',
                    'format' => 'function:default', // this will call PluginIserviceView_Cartridges::getInstallerTicketIdDisplay($row);
                ],
                'date_use' => [
                    'title' => 'Data instalării',
                    'align' => 'center',
                ],
                'saved_out_ticket_id' => [
                    'title' => 'Nr.&nbsp;tichet golire',
                    'align' => 'center',
                    'visible' => !self::isRestrictedMode(),
                    'link' => [
                        'href' => $CFG_GLPI['root_doc'] . "/plugins/iservice/front/ticket.form.php?id=[saved_out_ticket_id]&mode=" . PluginIserviceTicket::MODE_CLOSE,
                        'target' => '_blank',
                    ],
                ],
                'date_out' => [
                    'title' => 'Data golirii',
                    'align' => 'center',
                    'format' => 'function:default', // this will call PluginIserviceView_Cartridges::getDateOutDisplay($row);
                ],
                'calculated_printed_pages' => [
                    'title' => 'Copii',
                    'align' => 'center',
                    'format' => 'function:PluginIserviceView_Cartridges::getPrintedPagesDisplay($row);',
                    'visible' => !self::isRestrictedMode(),
                ],
            ],
        ];

        if (self::isRestrictedMode()) {
            $settings['columns']['location_name']['default_sort'] = 'ASC';
        }

        return $settings;
    }

}

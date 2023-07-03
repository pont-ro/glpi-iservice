<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Printers.php';

class PluginIserviceView_PrinterCounters extends PluginIserviceView_Printers
{

    static function getMinPercentageDisplay($column, $row_data)
    {
        if (empty($row_data["min_{$column}_percentage"])) {
            return '';
        }

        $single         = stripos($column, 'single') === 0 ? 1 : 0;
        $items          = PluginIserviceCommon::getQueryResult(
            "
            SELECT
                c.id
              , c.date_use
              , ci.ref
              , getCartridgePercentageEstimate(c.id, $single) percentage
              , getCartridgeDaysToEmptyEstimate(c.id, $single) days_to_empty" . ($single ? "" : "
              , getCartridgeChangeableCartridgeCount(c.id) changeable_cartridge_count
              , getCartridgeCompatiblePrinterCount(c.id) compatible_printers_count") . "
              , cfc.atcfield
              , CASE c.plugin_fields_typefielddropdowns_id
                    WHEN 2 THEN getPrinterCounterEstimate(c.printers_id, 1)
                    WHEN 3 THEN getPrinterCounterEstimate(c.printers_id, 1)
                    WHEN 4 THEN getPrinterCounterEstimate(c.printers_id, 1)
                    ELSE getPrinterCounterEstimate(c.printers_id, 0)
                END estimated_counter
              , CASE c.plugin_fields_typefielddropdowns_id
                    WHEN 2 THEN t.total2_color
                    WHEN 3 THEN t.total2_color
                    WHEN 4 THEN t.total2_color
                    ELSE t.total2_black
                END installed_counter
              , plct.data_luc last_data_luc
              , CASE c.plugin_fields_typefielddropdowns_id
                    WHEN 2 THEN plct.total2_color
                    WHEN 3 THEN plct.total2_color
                    WHEN 4 THEN plct.total2_color
                    ELSE plct.total2_black
                END last_counter
              , CASE c.plugin_fields_typefielddropdowns_id
                    WHEN 2 THEN cfp.dailycoloraveragefield
                    WHEN 3 THEN cfp.dailycoloraveragefield
                    WHEN 4 THEN cfp.dailycoloraveragefield
                    ELSE cfp.dailybkaveragefield
                END da
              , DATEDIFF(NOW(), plct.data_luc) passed_days
            FROM glpi_cartridges c
            JOIN glpi_cartridgeitems ci ON ci.id = c.cartridgeitems_id
            JOIN glpi_plugin_iservice_printers p ON p.id = c.printers_id
            LEFT JOIN glpi_plugin_iservice_printers_last_closed_tickets plct ON plct.printers_id = p.id
            JOIN glpi_plugin_fields_cartridgeitemcartridgecustomfields cfc ON cfc.items_id = c.cartridgeitems_id and cfc.itemtype = 'CartridgeItem'
            JOIN glpi_plugin_fields_printercustomfields cfp on cfp.items_id = p.id and cfp.itemtype = 'Printer'
            JOIN glpi_tickets t on t.id = c.tickets_id_use
            WHERE c.id in ({$row_data[$column . '_ids']})
            "
        );
        $title          = "";
        $min_percentage = 999999;
        $explanation    = "";
        foreach ($items as $item) {
            $title_text = "Cartuș $item[id] ($item[ref]) cu $item[atcfield] copii medii\nDisponibil $item[percentage]%, golire în $item[days_to_empty] zile\n";
            if ($min_percentage > $item['percentage']) {
                $min_percentage = $item['percentage'];
                $explanation    = "<br>($item[ref])";
                $min_text       = "Min: $title_text";
            }

            $title_text .= "&nbsp;- instalat pe $item[date_use], contor $item[installed_counter]\n";
            $title_text .= "&nbsp;- ultima intervenție pe $item[last_data_luc], contor $item[last_counter].\n";
            $title_text .= "&nbsp;- au trecut $item[passed_days] zile cu mediu zilnic $item[da]:\n";
            $title_text .= "&nbsp;&nbsp;&nbsp;&nbsp;- contor curent estimat: $item[estimated_counter]\n";
            $title_text .= "&nbsp;&nbsp;&nbsp;&nbsp;- copii efectuate estimat: " . ($item['estimated_counter'] - $item['installed_counter']) . "\n";
            $title_text .= "&nbsp;&nbsp;&nbsp;&nbsp;- în $item[days_to_empty] zile copii estimate: +" . $item['days_to_empty'] * $item['da'] . "\n";

            if (!$single) {
                $title_text .= "&nbsp;- cartușe instalabile: $item[changeable_cartridge_count]\n";
                $title_text .= "&nbsp;- imprimante cu același cartuș: $item[compatible_printers_count]\n";
            }

            $title .= $title_text;
        }

        if (count($items) > 1) {
            $title = "$min_text\n\n$title";
        }

        if ($row_data["min_{$column}_percentage"] < -25) {
            $style = "style='color:red;'";
        } else {
            $style = '';
        }

        return "<span class='has-bootstrap-tooltip clickable' title='" . str_replace("\n", '<br>', $title) . "' $style>" . round($row_data["min_{$column}_percentage"], 2) . "%$explanation</span>";
    }

    static function getDailyAverageDisplay($column, $row_data)
    {
        if ($column === 'dca' && $row_data['printer_types_id'] != PluginIservicePrinter::ID_COLOR_TYPE) {
            return '';
        }

        global $DB;
        $call = $DB->prepare("CALL getPrinterDailyAverageCalculation($row_data[printer_id], " . ($column === 'dca' ? 1 : 0) . ", @dailyAverage, @ticketCount, @minCounter, @maxCounter, @minDataLuc, @maxDataLuc)");
        $call->execute();
        $values = PluginIserviceCommon::getQueryResult("SELECT @dailyAverage, @ticketCount, @minCounter, @maxCounter, @minDataLuc, @maxDataLuc");
        foreach (array_shift($values) as $var_name => $var_value) {
            $data[substr($var_name, 1)] = $var_value;
        }

        $title  = "Media zilnică $data[dailyAverage] calculată din $data[ticketCount] tichete: ";
        $title .= ($data['maxCounter'] - $data['minCounter']) . " / " . round((strtotime($data['maxDataLuc']) - strtotime($data['minDataLuc'])) / 86400);
        $title .= "\n($data[maxCounter] - $data[minCounter]) / ($data[maxDataLuc] - $data[minDataLuc])";

        if ($row_data["c$column"] != $data['dailyAverage']) {
            return "<span style='color:red'>Daily avg. from query != calculated</span>";
        }

        $class = 'clickable';
        if (empty($data['dailyAverage'])) {
            $title                = "Nu există tickete suficiente pentru calcul";
            $data['dailyAverage'] = 100;
            $row_data["c$column"] = '?';
            $color                = 'blue';
        } elseif (abs($row_data[$column] - $data['dailyAverage']) > $row_data[$column] * 0.25) {
            $color  = "red";
            $class .= " average-alert";
        } elseif (abs($row_data[$column] - $data['dailyAverage']) > $row_data[$column] * 0.12) {
            $color  = "orange";
            $class .= " average-alert";
        } else {
            $color = "green";
        }

        global $CFG_PLUGIN_ISERVICE;
        if (self::inProfileArray('tehnician', 'admin', 'super-admin')) {
            $result  = "<a id='{$column}_link_$row_data[printer_id]' class='$class' onclick='$(\"#{$column}_span_$row_data[printer_id]\").show();$(this).hide();' style='color:$color;' title='$title'>{$row_data[$column]}</a>";
            $result .= "<span id='{$column}_span_$row_data[printer_id]' style='display:none; white-space: nowrap;'>";
            $result .= "<input id='{$column}_edit_$row_data[printer_id]' style='width:2em;' type='text' value='$data[dailyAverage]' />&nbsp;";
            $result .= "<i class='fa fa-check-circle' onclick='setDailyAverage(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/managePrinter.php?operation=set_$column\", $row_data[printer_id], $(\"#{$column}_edit_$row_data[printer_id]\").val(), \"{$column}\");' style='color:green'></i>&nbsp;";
            $result .= "<i class='fa fa-times' onclick='$(\"#{$column}_link_$row_data[printer_id]\").show();$(\"#{$column}_span_$row_data[printer_id]\").hide();'></i>";
            $result .= "</span><br/>";
            $result .= "({$row_data["c$column"]})";
        } else {
            $result = "<a id='{$column}_link_$row_data[printer_id]' class='$class' onclick='return false;' style='color:$color;' title='$title'>{$row_data[$column]}</a>";
        }

        return $result;
    }

    static function getUsageCoefficientDisplay($column, $row_data)
    {
        if ($column !== 'bk' && $row_data['printer_types_id'] != PluginIservicePrinter::ID_COLOR_TYPE) {
            return '';
        }

        $printed_pages_field = ($column === 'bk' ? '(c.printed_pages + c.printed_pages_color)' : 'c.printed_pages_color');
        $type_conditions     = [
            'bk' => 'not in (2, 3, 4)',
            'c' => '= 2',
            'm' => '= 3',
            'y' => '= 4',
        ];
        $data                = PluginIserviceCommon::getQueryResult(
            "
            select
                c.id cid
              , c.cartridgeitems_id ciid
              , c.plugin_fields_typefielddropdowns_id tid
              , c.date_use
              , c.date_out
              , $printed_pages_field printed_pages
              , ci.ref
              , cfc.atcfield atc
            from glpi_cartridges c
            join glpi_cartridgeitems ci on ci.id = c.cartridgeitems_id and (ci.ref like '%cton%' or ci.ref like '%ccat%' or ci.ref like '%ccai%')
            join glpi_plugin_fields_cartridgeitemcartridgecustomfields cfc on cfc.items_id = c.cartridgeitems_id and cfc.itemtype = 'CartridgeItem'
            where c.date_out is not null
              and $printed_pages_field > 0
              and c.plugin_fields_typefielddropdowns_id {$type_conditions[$column]}
              and c.printers_id = $row_data[printer_id]
            order by c.cartridgeitems_id, c.plugin_fields_typefielddropdowns_id, c.date_out
            "
        );

        $cartridge_items = [];
        foreach ($data as $row) {
            $cartridge_items[$row['ciid']]['atc'] = $row['atc'];
            $cartridge_items[$row['ciid']]['ref'] = $row['ref'];
            if (empty($cartridge_items[$row['ciid']]['types'][$row['tid']]['total_printed'])) {
                $cartridge_items[$row['ciid']]['types'][$row['tid']]['total_printed'] = 0;
                $cartridge_items[$row['ciid']]['types'][$row['tid']]['count']         = 0;
            }

            $cartridge_items[$row['ciid']]['types'][$row['tid']]['total_printed'] += $row['printed_pages'];
            $cartridge_items[$row['ciid']]['types'][$row['tid']]['count']++;
            $cartridge_items[$row['ciid']]['types'][$row['tid']]['printed_average']                     = round($cartridge_items[$row['ciid']]['types'][$row['tid']]['total_printed'] / $cartridge_items[$row['ciid']]['types'][$row['tid']]['count'], 2);
            $cartridge_items[$row['ciid']]['types'][$row['tid']]['calculated_uc']                       = round($cartridge_items[$row['ciid']]['types'][$row['tid']]['printed_average'] / $cartridge_items[$row['ciid']]['atc'], 2);
            $cartridge_items[$row['ciid']]['types'][$row['tid']]['cartridges'][$row['cid']]['printed']  = $row['printed_pages'];
            $cartridge_items[$row['ciid']]['types'][$row['tid']]['cartridges'][$row['cid']]['date_use'] = $row['date_use'];
            $cartridge_items[$row['ciid']]['types'][$row['tid']]['cartridges'][$row['cid']]['date_out'] = $row['date_out'];
        }

        $title   = '';
        $warning = '';
        foreach ($cartridge_items as $cartridge_item) {
            foreach ($cartridge_item['types'] as $tid => $type_data) {
                if (!empty($title)) {
                    $warning = "<i class='fa fa-exclamation-triangle' style='color:orange'></i>";
                }

                $title .= "{$type_data['count']} x $cartridge_item[ref] (tip $tid, ATC $cartridge_item[atc]) a printat $type_data[total_printed], in medie $type_data[printed_average] pagini => $type_data[calculated_uc]\n";
                foreach ($type_data['cartridges'] as $cid => $cartridge) {
                    $title .= "&nbsp;&nbsp;&nbsp;* între $cartridge[date_use] și $cartridge[date_out] au fost printate $cartridge[printed]\n";
                }
            }
        }

        $class = 'clickable';
        if (empty($row_data["calc_uc$column"])) {
            $title                      = 'Nu există cartușe golite pentru calcul';
            $row_data["calc_uc$column"] = '?';
            $color                      = 'blue';
        } elseif (abs($row_data["uc{$column}field"] - $row_data["calc_uc$column"]) > $row_data["uc{$column}field"] * 0.25) {
            $color  = "red";
            $class .= " average-alert";
        } elseif (abs($row_data["uc{$column}field"] - $row_data["calc_uc$column"]) > $row_data["uc{$column}field"] * 0.12) {
            $color  = "orange";
            $class .= " average-alert";
        } else {
            $color = "green";
        }

        global $CFG_PLUGIN_ISERVICE;
        if (self::inProfileArray('tehnician', 'admin', 'super-admin')) {
            $result  = "<a id='uc{$column}_link_$row_data[printer_id]' class='$class' onclick='$(\"#uc{$column}_span_$row_data[printer_id]\").show();$(this).hide();' style='color:$color;'>{$row_data["uc{$column}field"]}</a>";
            $result .= "<span id='uc{$column}_span_$row_data[printer_id]' style='display:none; white-space: nowrap;'>";
            $result .= "<input id='uc{$column}_edit_$row_data[printer_id]' name='item_values[printer][$row_data[printer_id]][uc{$column}field]' style='width:2em;' type='text' value='{$row_data["calc_uc$column"]}' />&nbsp;";
            $result .= "<i class='fa fa-check-circle' onclick='setDailyAverage(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/managePrinter.php?operation=set_uc$column\", $row_data[printer_id], $(\"#uc{$column}_edit_$row_data[printer_id]\").val(), \"uc{$column}\");' style='color:green'></i>&nbsp;";
            $result .= "<i class='fa fa-times' onclick='$(\"#uc{$column}_link_$row_data[printer_id]\").show();$(\"#uc{$column}_span_$row_data[printer_id]\").hide();'></i>";
            $result .= "</span><br/>";
            $result .= "<span title='$title' style='white-space:nowrap'>({$row_data["calc_uc$column"]}$warning)</span>";
        } else {
            $result = "<a id='{$column}_link_$row_data[printer_id]' class='$class' onclick='return false;' style='color:$color;' title='$title'>{$row_data["uc{$column}field"]}</a>";
        }

        return $result;
    }

    protected function getSettings()
    {
        if (PluginIserviceCommon::getInputVariable('mass_action_update_daily_averages')) {
            $printer_customfields = new PluginFieldsPrintercustomfield();
            foreach (PluginIserviceCommon::getArrayInputVariable('item')['printer'] as $printer_id => $update) {
                if (!$update || !$printer_customfields->getFromDBByItemsId($printer_id)) {
                    continue;
                }

                $dba = PluginIserviceCommon::getQueryResult("SELECT getPrinterDailyAverage($printer_id, 0) dba")[0]['dba'];
                $dca = PluginIserviceCommon::getQueryResult("SELECT getPrinterDailyAverage($printer_id, 1) dca")[0]['dca'];
                $printer_customfields->update(['id' => $printer_customfields->getID(), 'dailybkaveragefield' => $dba, 'dailycoloraveragefield' => $dca]);
            }

            $this->force_refresh = true;
        }

        if (PluginIserviceCommon::getInputVariable('mass_action_update_usage_coefficients')) {
            $printer_customfields = new PluginFieldsPrintercustomfield();
            $item_values          = PluginIserviceCommon::getArrayInputVariable('item_values')['printer'];
            foreach (PluginIserviceCommon::getArrayInputVariable('item')['printer'] as $printer_id => $update) {
                if (!$update || !$printer_customfields->getFromDBByItemsId($printer_id)) {
                    continue;
                }

                $printer_customfields->update(array_merge(['id' => $printer_customfields->getID()], $item_values[$printer_id]));
            }

            $this->force_refresh = true;
        }

        $this->enable_emaintenance_data_import = false;
        $settings                              = parent::getSettings();
        $settings['name']                      = __('Printer counters', 'iservice') . " care " . PluginIservicePrinter::getCMConditionForDisplay();
        $settings['mass_actions']              = [
            'update_daily_averages' => [
                'caption' => 'Modifică mediile zilnice + reîmprospătează',
                'action' => 'view.php?view=printerCounters',
            ],
            'update_usage_coefficients' => [
                'caption' => 'Modifică coeficienții de folosire + reîmprospătează',
                'action' => 'view.php?view=printerCounters',
            ],
        ];
        unset($settings['filters']['filter_buttons_prefix']);
        foreach (array_keys($settings['filters']) as $filter_id) {
            if (!in_array($filter_id, ['supplier_id', 'printer_name', 'supplier_name', 'printer_location', 'tech_id', 'serial', 'otherserial'])) {
                unset($settings['filters'][$filter_id]);
            }
        }

        foreach (array_keys($settings['columns']) as $column_id) {
            if (!in_array($column_id, ['ticket_status', 'printer_name', 'supplier_name', 'location_name', 'tech_name', 'serial', 'otherserial'])) {
                unset($settings['columns'][$column_id]);
            }
        }

        $settings['query']         = "
                        SELECT
                              p.id printer_id
                            , p.states_id printer_states_id
                            , p.original_name printer_name
                            , p.otherserial
                            , p.serial
                            , " . PluginIservicePrinter::getSerialFieldForEM('p') . " spaceless_serial
                            , p.printertypes_id printer_types_id
                            , p.contact_num
                            , p.contact
                            , p.comment
                            , p.users_id printer_users_id
                            , p.users_id_tech printer_users_id_tech
                            , p.groups_id printer_groups_id
                            , pt.name printer_type
                            , st.name printer_status
                            , s.id supplier_id
                            , s.name supplier_name
                            , s.phonenumber supplier_tel
                            , s.fax supplier_fax
                            , s.comment supplier_comment
                            , s.is_deleted supplier_deleted
                            , l.name location_name
                            , l.completename location_complete_name
                            , u.id tech_id
                            , CONCAT(IFNULL(CONCAT(u.realname, ' '),''), IFNULL(u.firstname, '')) tech_name
                            , ue.name external_user
                            , g.completename supergroup
                            , plct.data_luc last_data_luc
                            , plct.total2_black last_total2_black
                            , cfp.total2_black_fact
                            , plct.total2_color last_total2_color
                            , cfp.total2_color_fact
                            , cfp.data_exp_fact
                            , CAST(cfp.data_fact as DATE) data_fact
                            , cfp.week_nr
                            , cfp.emaintenancefield emaintenance
                            , cfp.gps
                            , cfp.usageaddressfield
                            , cfp.dailybkaveragefield dba
                            , cfp.dailycoloraveragefield dca
                            , cfp.ucbkfield
                            , cb.calc_ucbk
                            , cfp.uccfield
                            , cc.calc_ucc
                            , cfp.ucmfield
                            , cm.calc_ucm
                            , cfp.ucyfield
                            , cy.calc_ucy
                            , cfs.atlfield
                            , cfs.part_email_f1 supplier_email_facturi
                            , cfs.cartridge_management
                            , c0.min_single_cartridge_percentage
                            , c0.single_cartridge_ids
                            , c1.min_cartridge_percentage
                            , c1.next_visit_days_cartridge
                            , c1.cartridge_ids
                            , c2.min_consumable_percentage
                            , c2.next_visit_days_consumable
                            , c2.consumable_ids
                            , IF(c1.min_cartridge_percentage < cfs.atlfield * 100, 'da', 'nu') cartridge_limit_reached
                            , getPrinterDailyAverage(p.id, 0) cdba
                            , getPrinterDailyAverage(p.id, 1) cdca
                        FROM glpi_plugin_iservice_printers p
                        LEFT JOIN glpi_plugin_iservice_printers_last_closed_tickets plct ON plct.printers_id = p.id
                        LEFT JOIN glpi_printertypes pt ON pt.id = p.printertypes_id
                        LEFT JOIN glpi_infocoms i ON i.items_id = p.id and i.itemtype = 'Printer'
                        LEFT JOIN glpi_suppliers s ON s.id = i.suppliers_id
                        LEFT JOIN glpi_locations l ON l.id = p.locations_id
                        LEFT JOIN glpi_users u ON u.id = p.users_id_tech
                        LEFT JOIN glpi_users ue ON ue.id = p.users_id
                        LEFT JOIN glpi_groups g ON g.id = p.groups_id
                        LEFT JOIN glpi_plugin_fields_printercustomfields cfp ON cfp.items_id = p.id and cfp.itemtype = 'Printer'
                        LEFT JOIN glpi_plugin_fields_suppliercustomfields cfs ON cfs.items_id = s.id and cfs.itemtype = 'Supplier'
                        LEFT JOIN glpi_states st ON st.id = p.states_id
                        LEFT JOIN (SELECT
                                       c.printers_id
                                     , MIN(getCartridgePercentageEstimate(c.id, 1)) min_single_cartridge_percentage
                                     , GROUP_CONCAT(c.id SEPARATOR ',') single_cartridge_ids
                                   FROM glpi_cartridges c
                                   JOIN glpi_cartridgeitems ci ON ci.id = c.cartridgeitems_id
                                   WHERE c.date_use is not null and c.date_out is null and (ci.ref like 'CTON%' or ci.ref like 'CCA%')
                                   GROUP BY c.printers_id) c0 ON c0.printers_id = p.id
                        LEFT JOIN (SELECT
                                       c.printers_id
                                     , MIN(getCartridgePercentageEstimate(c.id, 0)) min_cartridge_percentage
                                     , MIN(getCartridgeDaysToEmptyEstimate(c.id, 0)) next_visit_days_cartridge
                                     , GROUP_CONCAT(c.id SEPARATOR ',') cartridge_ids
                                   FROM glpi_cartridges c
                                   JOIN glpi_cartridgeitems ci ON ci.id = c.cartridgeitems_id
                                   WHERE c.date_use is not null and c.date_out is null and (ci.ref like 'CTON%' or ci.ref like 'CCA%')
                                   GROUP BY c.printers_id) c1 ON c1.printers_id = p.id
                        LEFT JOIN (SELECT
                                       c.printers_id
                                     , MIN(getCartridgePercentageEstimate(c.id, 0)) min_consumable_percentage
                                     , MIN(getCartridgeDaysToEmptyEstimate(c.id, 0)) next_visit_days_consumable
                                     , GROUP_CONCAT(c.id SEPARATOR ',') consumable_ids
                                   FROM glpi_cartridges c
                                   JOIN glpi_cartridgeitems ci ON ci.id = c.cartridgeitems_id
                                   WHERE c.date_use is not null and c.date_out is null and not ci.ref like 'CTON%' and not ci.ref like 'CCA%'
                                   GROUP BY c.printers_id) c2 ON c2.printers_id = p.id
                        LEFT JOIN (SELECT c.printers_id pid, ROUND(avg(c.printed_pages + c.printed_pages_color) / avg(cfc.atcfield), 2) calc_ucbk
                                   FROM glpi_cartridges c
                                   JOIN glpi_cartridgeitems ci ON ci.id = c.cartridgeitems_id AND (ci.ref like '%cton%' OR ci.ref like '%ccat%' OR ci.ref like '%ccai%')
                                   JOIN glpi_plugin_fields_cartridgeitemcartridgecustomfields cfc ON cfc.items_id = c.cartridgeitems_id AND cfc.itemtype = 'CartridgeItem'
                                   WHERE c.date_out IS NOT NULL AND (c.printed_pages + c.printed_pages_color) > 0 AND c.plugin_fields_typefielddropdowns_id NOT IN (2, 3, 4)
                                   GROUP BY c.printers_id) cb ON cb.pid = p.id
                        LEFT JOIN (SELECT c.printers_id pid, ROUND(avg(c.printed_pages_color) / avg(cfc.atcfield), 2) calc_ucc
                                   FROM glpi_cartridges c
                                   JOIN glpi_cartridgeitems ci ON ci.id = c.cartridgeitems_id AND (ci.ref like '%cton%' OR ci.ref like '%ccat%' OR ci.ref like '%ccai%')
                                   JOIN glpi_plugin_fields_cartridgeitemcartridgecustomfields cfc ON cfc.items_id = c.cartridgeitems_id AND cfc.itemtype = 'CartridgeItem'
                                   WHERE c.date_out IS NOT NULL AND c.printed_pages_color > 0 AND c.plugin_fields_typefielddropdowns_id = 2
                                   GROUP BY c.printers_id) cc ON cc.pid = p.id
                        LEFT JOIN (SELECT c.printers_id pid, ROUND(avg(c.printed_pages_color) / avg(cfc.atcfield), 2) calc_ucm
                                   FROM glpi_cartridges c
                                   JOIN glpi_cartridgeitems ci ON ci.id = c.cartridgeitems_id AND (ci.ref like '%cton%' OR ci.ref like '%ccat%' OR ci.ref like '%ccai%')
                                   JOIN glpi_plugin_fields_cartridgeitemcartridgecustomfields cfc ON cfc.items_id = c.cartridgeitems_id AND cfc.itemtype = 'CartridgeItem'
                                   WHERE c.date_out IS NOT NULL AND c.printed_pages_color > 0 AND c.plugin_fields_typefielddropdowns_id = 3
                                   GROUP BY c.printers_id) cm ON cm.pid = p.id
                        LEFT JOIN (SELECT c.printers_id pid, ROUND(avg(c.printed_pages_color) / avg(cfc.atcfield), 2) calc_ucy
                                   FROM glpi_cartridges c
                                   JOIN glpi_cartridgeitems ci ON ci.id = c.cartridgeitems_id AND (ci.ref like '%cton%' OR ci.ref like '%ccat%' OR ci.ref like '%ccai%')
                                   JOIN glpi_plugin_fields_cartridgeitemcartridgecustomfields cfc ON cfc.items_id = c.cartridgeitems_id AND cfc.itemtype = 'CartridgeItem'
                                   WHERE c.date_out IS NOT NULL AND c.printed_pages_color > 0 AND c.plugin_fields_typefielddropdowns_id = 4
                                   GROUP BY c.printers_id) cy ON cy.pid = p.id
                        WHERE p.is_deleted = 0
                        GROUP BY p.id
                        ";
        $settings['use_cache']     = true;
        $settings['cache_timeout'] = 43200; // 12 hours
        $settings['cache_query']   = "SELECT *  FROM {table_name}
            WHERE printer_name LIKE '[printer_name]' {$this->getRightCondition('printer_')}
              AND " . PluginIservicePrinter::getCMCondition('cartridge_management', 'printer_types_id', 'printer_states_id') . "
              AND otherserial LIKE '[otherserial]'
              AND serial LIKE '[serial]'
              AND supplier_name LIKE '[supplier_name]'
              AND ((location_name is null AND '[printer_location]' = '%%') OR location_name LIKE '[printer_location]')
              [tech_id]
              [supplier_id]
              [cartridge_limit_reached]
            ";

        $settings['filters']['filter_buttons_prefix'] =
              "<a class='vsubmit' onclick='$(\"#printercounters tr.result-row\").show();'>Toate</a> "
            . "<a class='vsubmit' onclick='$(\"#printercounters tr.result-row\").each(function() {if ($(this).find(\".average-alert\").length === 0) {\$(this).hide();}});'>Cu valori medii incorecte</a>";

        $settings['columns']['ticket_status']['title'] = 'Acțiuni';

        $settings['columns']['min_single_cartridge_percentage'] = [
            'title' => 'Toner instalat pe aparat',
            'align' => 'center',
            'format' => 'function:PluginIserviceView_PrinterCounters::getMinPercentageDisplay("single_cartridge", $row);',
            'class' => 'no-wrap',
        ];
        $settings['columns']['cartridge_limit_reached']         = [
            'title' => 'Toner sub limită',
            'align' => 'center',
        ];
        $settings['filters']['cartridge_limit_reached']         = [
            'type' => self::FILTERTYPE_SELECT,
            'caption' => 'Toner sub limită',
            'options' => ['' => '---', 'c1.min_cartridge_percentage < cfs.atlfield * 100' => 'da', 'c1.min_cartridge_percentage >= cfs.atlfield * 100' => 'nu'],
            'empty_value' => '',
            'zero_is_empty' => false,
            'header' => 'cartridge_limit_reached',
            'format' => 'AND %s',
            'cache_override' => [
                'options' => ['' => '---', 'min_cartridge_percentage < atlfield * 100' => 'da', 'c1.min_cartridge_percentage >= cfs.atlfield * 100' => 'nu'],
            ],
        ];

        $settings['columns']['next_visit_days_cartridge']  = [
            'title' => 'Urm. livrare toner în',
            'default_sort' => 'ASC',
            'align' => 'center',
        ];
        $settings['columns']['min_cartridge_percentage']   = [
            'title' => 'Toner disponibil',
            'align' => 'center',
            'format' => 'function:PluginIserviceView_PrinterCounters::getMinPercentageDisplay("cartridge", $row);',
            'class' => 'no-wrap',
        ];
        $settings['columns']['next_visit_days_consumable'] = [
            'title' => 'Urm. livrare consumabil în',
            'default_sort' => 'ASC',
            'align' => 'center',
        ];
        $settings['columns']['min_consumable_percentage']  = [
            'title' => 'Consumabil disponibil',
            'align' => 'center',
            'format' => 'function:PluginIserviceView_PrinterCounters::getMinPercentageDisplay("consumable", $row);',
            'class' => 'no-wrap',
        ];
        $settings['columns']['dba']                        = [
            'title' => 'Nr. mediu bk',
            'align' => 'center',
            'format' => 'function:PluginIserviceView_PrinterCounters::getDailyAverageDisplay("dba", $row);',
        ];
        $settings['columns']['dca']                        = [
            'title' => 'Nr. mediu color',
            'align' => 'center',
            'format' => 'function:PluginIserviceView_PrinterCounters::getDailyAverageDisplay("dca", $row);',
        ];
        $settings['columns']['calc_ucbk']                  = [
            'title' => 'Coef. folosire Black',
            'align' => 'center',
            'format' => 'function:PluginIserviceView_PrinterCounters::getUsageCoefficientDisplay("bk", $row);',
        ];
        $settings['columns']['calc_ucc']                   = [
            'title' => 'Coef. folosire Cyan',
            'align' => 'center',
            'format' => 'function:PluginIserviceView_PrinterCounters::getUsageCoefficientDisplay("c", $row);',
        ];
        $settings['columns']['calc_ucm']                   = [
            'title' => 'Coef. folosire Mag.',
            'align' => 'center',
            'format' => 'function:PluginIserviceView_PrinterCounters::getUsageCoefficientDisplay("m", $row);',
        ];
        $settings['columns']['calc_ucy']                   = [
            'title' => 'Coef. folosire Yellow',
            'align' => 'center',
            'format' => 'function:PluginIserviceView_PrinterCounters::getUsageCoefficientDisplay("y", $row);',
        ];
        return $settings;
    }

}

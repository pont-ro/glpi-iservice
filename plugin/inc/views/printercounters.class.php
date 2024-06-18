<?php

// Imported from iService2, needs refactoring. Original file: "PrinterCounters2.php".
namespace GlpiPlugin\Iservice\Views;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use GlpiPlugin\Iservice\Views\Printers as PluginIserviceViewPrinter;
use PluginIservicePrinter;
use PluginIserviceDB;
use PluginFieldsPrinterprintercustomfield;

class PrinterCounters extends PluginIserviceViewPrinter
{
    const AVALIABLE_LIMIT = 0.4;

    public function getHeadingLevel()
    {
        return 4;
    }

    public static function getDailyAverageDisplay($column, $row_data): string
    {
        if ($column === 'dca' && $row_data['printer_types_id'] != PluginIservicePrinter::ID_COLOR_TYPE) {
            return '';
        }

        global $DB;
        $call = $DB->prepare("CALL getPrinterDailyAverageCalculation($row_data[printer_id], " . ($column === 'dca' ? 1 : 0) . ", @dailyAverage, @ticketCount, @minCounter, @maxCounter, @minDataLuc, @maxDataLuc)");
        $call->execute();
        $values = PluginIserviceDB::getQueryResult("SELECT @dailyAverage, @ticketCount, @minCounter, @maxCounter, @minDataLuc, @maxDataLuc");
        foreach (array_shift($values) as $var_name => $var_value) {
            $data[substr($var_name, 1)] = $var_value;
        }

        $title  = "Media zilnică $data[dailyAverage] calculată din $data[ticketCount] tichete: ";
        $title .= ($data['maxCounter'] - $data['minCounter']) . " / " . round((strtotime($data['maxDataLuc']) - strtotime($data['minDataLuc'])) / 86400);
        $title .= "\n($data[maxCounter] - $data[minCounter]) / ($data[maxDataLuc] - $data[minDataLuc])";

        if ($row_data["c$column"] != $data['dailyAverage']) {
            return "<span style='color:red'>Daily avg. from query != calculated</span>";
        }

        $class = 'pointer';
        if (empty($data['dailyAverage'])) {
            $title                = "Nu există tickete suficiente pentru calcul";
            $data['dailyAverage'] = 100;
            $row_data["c$column"] = '?';
        } elseif (abs($row_data[$column] - $data['dailyAverage']) > $row_data[$column] * 0.25) {
            $class .= " average-alert";
        } elseif (abs($row_data[$column] - $data['dailyAverage']) > $row_data[$column] * 0.12) {
            $class .= " average-alert";
        }

        $class .= " {$column}_link_$row_data[printer_id]";

        global $CFG_PLUGIN_ISERVICE;
        if (self::inProfileArray('tehnician', 'admin', 'super-admin')) {
            $result  = "<a id='{$column}_link_$row_data[printer_id]_$row_data[consumable_type]' class='$class' onclick='$(\"#{$column}_span_$row_data[printer_id]_$row_data[consumable_type]\").show();$(this).hide();' title='$title'>{$row_data[$column]}</a>";
            $result .= "<span id='{$column}_span_$row_data[printer_id]_$row_data[consumable_type]' style='display:none; white-space: nowrap;'>";
            $result .= "<input id='{$column}_edit_$row_data[printer_id]_$row_data[consumable_type]' class='{$column}_edit_$row_data[printer_id]' style='width:2em;' type='text' value='$data[dailyAverage]' />&nbsp;";
            $result .= "<i class='fa fa-check-circle' onclick='setDailyAverage(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/managePrinter.php?operation=set_$column\", $row_data[printer_id], $(\"#{$column}_edit_$row_data[printer_id]_$row_data[consumable_type]\").val(), \"{$column}\", \"_$row_data[consumable_type]\");' style='color:green'></i>&nbsp;";
            $result .= "<i class='fa fa-times' onclick='$(\"#{$column}_link_$row_data[printer_id]_$row_data[consumable_type]\").show();$(\"#{$column}_span_$row_data[printer_id]_$row_data[consumable_type]\").hide();'></i>";
            $result .= "</span><br/>";
        } else {
            $result = "<a id='{$column}_link_$row_data[printer_id]_$row_data[consumable_type]' class='$class' onclick='return false;' title='$title'>{$row_data[$column]}</a>";
        }

        return $result;
    }

    public static function getUsageCoefficientDisplay($column, $row_data): string
    {
        if ($column !== 'bk' && $row_data['printer_types_id'] != PluginIservicePrinter::ID_COLOR_TYPE) {
            return '';
        }

        if ($row_data['consumable_type'] === 'consumable') {
            return '';
        }

        $title = "Pentru mediu printabil $row_data[average_total_counter]:\n" . $row_data["calc_uc{$column}_explanation"];

        $class = 'pointer';
        if (empty($row_data["calc_uc$column"])) {
            $title                      = 'Nu există cartușe golite ce pot fi folosite pentru calcul';
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

        $class .= " uc{$column}_link_$row_data[printer_id]";

        global $CFG_PLUGIN_ISERVICE;
        if (self::inProfileArray('tehnician', 'admin', 'super-admin')) {
            $result  = "<a id='uc{$column}_link_$row_data[printer_id]_$row_data[consumable_type]' class='$class' onclick='$(\"#uc{$column}_span_$row_data[printer_id]_$row_data[consumable_type]\").show();$(this).hide();' style='color:$color;'>{$row_data["uc{$column}field"]}</a>";
            $result .= "<span id='uc{$column}_span_$row_data[printer_id]_$row_data[consumable_type]' style='display:none; white-space: nowrap;'>";
            $result .= "<input id='uc{$column}_edit_$row_data[printer_id]_$row_data[consumable_type]' class='uc{$column}_edit_$row_data[printer_id]' name='item_values[printer][$row_data[printer_id]][uc{$column}field]' style='width:2em;' type='text' value='{$row_data["calc_uc$column"]}' />&nbsp;";
            $result .= "<i class='fa fa-check-circle' onclick='setDailyAverage(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/managePrinter.php?operation=set_uc$column\", $row_data[printer_id], $(\"#uc{$column}_edit_$row_data[printer_id]_$row_data[consumable_type]\").val(), \"uc{$column}\", \"_$row_data[consumable_type]\");' style='color:green'></i>&nbsp;";
            $result .= "<i class='fa fa-times' onclick='$(\"#uc{$column}_link_$row_data[printer_id]_$row_data[consumable_type]\").show();$(\"#uc{$column}_span_$row_data[printer_id]_$row_data[consumable_type]\").hide();'></i>";
            $result .= "</span><br/>";
            $result .= "<span title='$title' style='white-space:nowrap'>({$row_data["calc_uc$column"]})</span>";
        } else {
            $result = "<a id='{$column}_link_$row_data[printer_id]_$row_data[consumable_type]' class='$class' onclick='return false;' style='color:$color;' title='$title'>{$row_data["uc{$column}field"]}</a>";
        }

        return $result;
    }

    public static function getMinDaysToVisitDisplay($row_data): string
    {
        $min_days_to_visit = intval($row_data['min_days_to_visit']);
        $title             = "title='$row_data[changeable_count] rezerve pentru $row_data[compatible_printer_count] aparate compatibile'";

        if ($min_days_to_visit >= 10) {
            return "<span $title>$min_days_to_visit zile</span>";
        }

        if ($row_data['changeable_count'] == 0) {
            $color = "red";
        } elseif ($row_data['changeable_count'] < $row_data['compatible_printer_count']) {
            $color = "orange";
        } else {
            $color = "green";
        }

        return "<span $title style='color: $color'>$min_days_to_visit zile</span>";
    }

    protected function getSettings(): array
    {
        if (IserviceToolBox::getInputVariable('mass_action_update_daily_averages')) {
            $printer_customfields = new PluginFieldsPrinterprintercustomfield();
            foreach (IserviceToolBox::getArrayInputVariable('item')['printer'] ?? [] as $printer_id => $update) {
                if (!$update || !PluginIserviceDB::populateByItemsId($printer_customfields, $printer_id)) {
                    continue;
                }

                $dba = PluginIserviceDB::getQueryResult("SELECT getPrinterDailyAverage($printer_id, 0) dba")[0]['dba'];
                $dca = PluginIserviceDB::getQueryResult("SELECT getPrinterDailyAverage($printer_id, 1) dca")[0]['dca'];
                $printer_customfields->update(['id' => $printer_customfields->getID(), 'daily_bk_average_field' => $dba, 'daily_color_average_field' => $dca]);
            }

            $this->force_refresh = true;
        }

        if (IserviceToolBox::getInputVariable('mass_action_update_usage_coefficients')) {
            $printer_customfields = new PluginFieldsPrinterprintercustomfield();
            $item_values          = IserviceToolBox::getArrayInputVariable('item_values')['printer'];
            foreach (IserviceToolBox::getArrayInputVariable('item')['printer'] as $printer_id => $update) {
                if (!$update || !PluginIserviceDB::populateByItemsId($printer_customfields, $printer_id)) {
                    continue;
                }

                $printer_customfields->update(array_merge(['id' => $printer_customfields->getID()], $item_values[$printer_id]));
            }

            $this->force_refresh = true;
        }

        $this->enable_emaintenance_data_import = false;
        $settings                              = parent::getSettings();
        $settings['name']                      = __('Printer counters', 'iservice') . " care " . PluginIservicePrinter::getCMConditionForDisplay();
        $settings['enable_refresh']            = false;
        $settings['mass_actions']              = [];
//            'update_daily_averages' => [
//                'caption' => 'Modifică mediile zilnice + reîmprospătează',
//                'action' => 'views.php?view=PrinterCounters',
//            ],
//        ];
        foreach (array_keys($settings['filters']) as $filter_id) {
            if (!in_array($filter_id, ['supplier_id', 'printer_name', 'supplier_name', 'printer_location', 'serial', 'otherserial', 'tech_id'])) {
                unset($settings['filters'][$filter_id]);
            }
        }

        foreach (array_keys($settings['columns']) as $column_id) {
            if (!in_array($column_id, ['ticket_status', 'printer_name', 'supplier_name', 'location_name', 'serial', 'otherserial', 'tech_name'])) {
                unset($settings['columns'][$column_id]);
            }
        }

        $order_by      = IserviceToolBox::getArrayInputVariable($this->getRequestArrayName(), [])['order_by'] ?? '';
        $last_order_by = [
            'days_to_visits' => 'days_to_visit',
            'estimate_percentages' => 'available_percentage_estimate'
        ][$order_by] ?? 'cfci.plugin_fields_cartridgeitemtypedropdowns_id';

        $settings['query']               = "
            select
                t.*
              , group_concat(t.consumable_code separator '<br>') consumable_codes
              , concat( 
                  '<span style=\'display:none;\'>',
                  group_concat(lpad(100 + t.available_percentage_estimate, 6, '0') separator '<br>'),
                  '</span>',
                  group_concat(concat(
                      '<span title=\"',
                      'Contor la instalare (', date_format(t.installed_date, '%Y-%m-%d'), '): ', t.installed_counter,
                      '\\nContor ultima citire (', date_format(t.last_closed_date, '%Y-%m-%d'), '): ', t.last_closed_counter, ' (+', t.last_closed_counter - t.installed_counter, ')', 
                      '\\nContor estimat (+', datediff(NOW(), t.last_closed_date), ' zile): ', t.estimate_counter, ' (+', t.estimate_counter - t.last_closed_counter, ')',
                      '\\nTotal mediu printabil: ', round(t.average_total_counter * t.life_coefficient * t.usage_coefficient), ' (', t.average_total_counter, ' * ' , t.life_coefficient, ' * ', t.usage_coefficient, ')', 
                      '\\nMediu zilnic: ', t.daily_average_counter,
                      '\\n\\nCalculatie: 1 - (', t.estimate_counter, ' - ', t.installed_counter, ')/', round(t.average_total_counter * t.life_coefficient * t.usage_coefficient),
                      '\">', t.available_percentage_estimate * 100, '%</span>') separator '<br>')
                  ) estimate_percentages
              , 'lastClosedCounter + daysSinceLastClose * da' estimated_counter_formula
              , '1 - (estimateCounter - installedCounter) / (atc * lc * uc)' estimate_percentage_formula
              , concat( 
                  '<span style=\'display:none;\'>',
                  group_concat(lpad(1000000 + t.days_to_visit, 7, 0) separator '<br>'),
                  '</span>',   
                  group_concat(concat(
                      '<span title=\"',
                      'Contor la instalare (', date_format(t.installed_date, '%Y-%m-%d'), '): ', t.installed_counter,
                      '\\nContor ultima citire (', date_format(t.last_closed_date, '%Y-%m-%d'), '): ', t.last_closed_counter, ' (+', t.last_closed_counter - t.installed_counter, ')', 
                      '\\nContor estimat (+', datediff(NOW(), t.last_closed_date), ' zile): ', t.estimate_counter, ' (+', t.estimate_counter - t.last_closed_counter, ')',
                      '\\nTotal mediu printabil: ', round(t.average_total_counter * t.life_coefficient * t.usage_coefficient), ' (', t.average_total_counter, ' * ' , t.life_coefficient, ' * ', t.usage_coefficient, ')', 
                      '\\nMediu zilnic: ', t.daily_average_counter,
                      '\\nGolire de la plin in: ', round(t.average_total_counter * t.life_coefficient * t.usage_coefficient / t.daily_average_counter), ' zile',
                      '\\nCartuse rezerva: ', t.changeable_count,
                      '\\nAparate compatibile: ', t.compatible_printer_count,
                      '\\n\\nCalculatie: (', round(t.average_total_counter * t.life_coefficient * t.usage_coefficient), ' - ', t.last_closed_counter - t.installed_counter , ') / ' , t.daily_average_counter, ' - ', datediff(NOW(), t.last_closed_date), ' + ' , round(t.average_total_counter * t.life_coefficient * t.usage_coefficient / t.daily_average_counter), ' * ', t.changeable_count ,' / ', t.compatible_printer_count, 
                      '\">', t.days_to_visit, ' zile</span>') separator '<br>') 
                  ) days_to_visits
              , '(atc * lc * uc - (lastClosedCounter - installedCounter)) / da - daysSinceLastClose + (atc * lc * uc / da) * (changeableCartridges / compatiblePrinterCount)' days_to_visit_formula
              , min(t.available_percentage_estimate) min_estimate_percentage
              , min(t.below_limit) below_limit_exists
              , min(t.days_to_visit) - t.avaliable_limit * abs(min(t.days_to_visit)) min_days_to_visit
              , group_concat(t.in_stock separator '<br>') stocks
            from (
                select
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
                  , cfp.invoiced_total_color_field
                  , cfp.invoice_expiry_date_field
                  , CAST(cfp.invoice_date_field as DATE) invoice_date_field
                  , cfp.week_nr_field
                  , cfp.em_field emaintenance
                  , cfp.disable_em_field disableem
                  , cfp.contact_gps_field
                  , cfp.usage_address_field
                  , cfp.no_invoice_field
                  , cfp.cost_center_field costcenter
                  , pbuc.uc calc_ucbk
                  , pbuc.explanation calc_ucbk_explanation
                  , pcuc.uc calc_ucc
                  , pcuc.explanation calc_ucc_explanation
                  , pmuc.uc calc_ucm
                  , pmuc.explanation calc_ucm_explanation
                  , pyuc.uc calc_ucy
                  , pyuc.explanation calc_ucy_explanation
                  , l.completename location_complete_name
                  , s.id supplier_id
                  , s.name supplier_name
                  , s.phonenumber supplier_tel
                  , s.fax supplier_fax
                  , s.comment supplier_comment
                  , s.is_deleted supplier_deleted
                  , cfs.email_for_invoices_field supplier_email_facturi
                  , cfs.cm_field
                  , u.id tech_id
                  , CONCAT(IFNULL(CONCAT(u.realname, ' '),''), IFNULL(u.firstname, '')) tech_name
                  , ue.name external_user
                  , c.id cid
                  , ci.id ciid
                  , ci.ref consumable_code
                  , cfci.plugin_fields_cartridgeitemtypedropdowns_id
                  , t2.codbenef
                  , t2.numar_facturi_neplatite
                  , @consumableType := if (ci.ref like 'CTON%' or ci.ref like 'CCA%', 'cartridge', 'consumable') consumable_type
                  , @atc := if(coalesce(cfci.atc_field, 0) = 0, 1000, cfci.atc_field) average_total_counter
                  , @lc := if(coalesce(cfci.life_coefficient_field, 0) = 0, 1, cfci.life_coefficient_field) life_coefficient
                  , @ucc := if (coalesce(cfp.uc_cyan_field, 0) = 0, 0.75, cfp.uc_cyan_field) uc_cyan_field
                  , @ucm := if (coalesce(cfp.uc_magenta_field, 0) = 0, 0.75, cfp.uc_magenta_field) uc_magenta_field
                  , @ucy := if (coalesce(cfp.uc_yellow_field, 0) = 0, 0.75, cfp.uc_yellow_field) uc_yellow_field
                  , @ucbk := if (coalesce(cfp.uc_bk_field, 0) = 0, 0.75, cfp.uc_bk_field) uc_bk_field
                  , @uc := if(@consumableType = 'consumable', 1, case cfci.plugin_fields_cartridgeitemtypedropdowns_id
                                                                    when 2 then @ucc
                                                                    when 3 then @ucm
                                                                    when 4 then @ucy
                                                                    else @ucbk 
                                                                 end) usage_coefficient
                  , @dba := coalesce(cfp.daily_bk_average_field, 0) dba
                  , @dca := coalesce(cfp.daily_color_average_field, 0) dca
                  , @da := if(cfci.plugin_fields_cartridgeitemtypedropdowns_id in (2, 3, 4), if(@dca = 0, 100, @dca), if(@dba + @dca = 0, 100, @dba + @dca)) daily_average_counter
                  , @atl := " . self::AVALIABLE_LIMIT . " avaliable_limit
                  , @changeable_count := coalesce(ccc.count, 0) changeable_count
                  , @compatible_printer_count := coalesce(ccpc.count, 0) compatible_printer_count
                  , @installedCounter := if (cfci.plugin_fields_cartridgeitemtypedropdowns_id in (2, 3, 4), cft.total2_color_field, cft.total2_black_field + cft.total2_color_field) installed_counter
                  , @installedDate := cft.effective_date_field installed_date
                  , @lastClosedCounter := if (cfci.plugin_fields_cartridgeitemtypedropdowns_id in (2, 3, 4), plct.total2_color_field, plct.total2_black_field + plct.total2_color_field) last_closed_counter
                  , @lastClosedDate := plct.effective_date_field last_closed_date
                  , @estimateCounter := @lastClosedCounter + datediff(NOW(), @lastClosedDate) * @da estimate_counter
                  , @availableEstimate := 1 - round(IF((@atc * @lc * @uc) > 0, (@estimateCounter - @installedCounter) / (@atc * @lc * @uc), 0), 2) available_percentage_estimate
                  , if (@availableEstimate < @atl, 'da', 'nu') below_limit
                  , round(coalesce(IF(@da > 0, (@atc * @lc * @uc - (@lastClosedCounter - @installedCounter)) / @da, NULL), 180) - datediff(NOW(), @lastClosedDate) + (IF(@da > 0, @atc * @lc * @uc / @da, 0)) * (IF(@compatible_printer_count > 0, @changeable_count / @compatible_printer_count, 0))) days_to_visit
                  , concat('<span title=\"', coalesce(ccc.cids, 'nu există cartușe compatibile'), '\">', @changeable_count, '</span> / <span title=\"', coalesce(ccpc.pids, 'nu există aparate compatibile'), '\">', @compatible_printer_count, '</span>') in_stock
                  , getPrinterDailyAverage(p.id, 0) cdba
                  , getPrinterDailyAverage(p.id, 1) cdca
                from glpi_plugin_iservice_printers p
                left join glpi_plugin_fields_printerprintercustomfields cfp on cfp.items_id = p.id and cfp.itemtype = 'Printer'
                left join glpi_plugin_iservice_printers_last_closed_tickets plct on plct.printers_id = p.id
                left join glpi_locations l on l.id = p.locations_id
                left join glpi_infocoms i on i.items_id = p.id and i.itemtype = 'Printer'
                left join glpi_suppliers s on s.id = i.suppliers_id
                left join glpi_plugin_fields_suppliersuppliercustomfields cfs on cfs.items_id = s.id and cfs.itemtype = 'Supplier'
                left join glpi_users u on u.id = p.users_id_tech
                left join glpi_users ue on ue.id = p.users_id
                left join glpi_cartridges c on c.printers_id = p.id and c.date_use is not null and c.date_out is null
                left join glpi_cartridgeitems  ci on ci.id = c.cartridgeitems_id
                left join glpi_plugin_fields_cartridgecartridgecustomfields cfc on cfc.items_id = c.id and cfc.itemtype = 'Cartridge'
                left join glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cfci on cfci.items_id = ci.id and cfci.itemtype = 'CartridgeItem'
                left join glpi_plugin_iservice_consumable_compatible_printers_counts ccpc on ccpc.id = c.id
                left join glpi_plugin_iservice_consumable_changeable_counts ccc on ccc.id = c.id
                left join glpi_tickets it on it.id = cfc.tickets_id_use_field
                left join glpi_plugin_fields_ticketticketcustomfields cft on cft.items_id = it.id and cft.itemtype = 'Ticket'
                left join glpi_plugin_iservice_printer_usage_coefficients pbuc on pbuc.printers_id = p.id and pbuc.plugin_fields_cartridgeitemtypedropdowns_id = 1 
                left join glpi_plugin_iservice_printer_usage_coefficients pcuc on pcuc.printers_id = p.id and pcuc.plugin_fields_cartridgeitemtypedropdowns_id = 2 
                left join glpi_plugin_iservice_printer_usage_coefficients pmuc on pmuc.printers_id = p.id and pmuc.plugin_fields_cartridgeitemtypedropdowns_id = 3 
                left join glpi_plugin_iservice_printer_usage_coefficients pyuc on pyuc.printers_id = p.id and pyuc.plugin_fields_cartridgeitemtypedropdowns_id = 4 
                left join (select codbenef, count(codbenef) numar_facturi_neplatite
                           from hmarfa_facturi 
                           where (codl = 'f' or stare like 'v%') and tip like 'tf%'
                           and valinc-valpla > 0
                           group by codbenef) t2 on t2.codbenef = cfs.hmarfa_code_field
                where p.is_deleted = 0 and c.id is not null
                order by p.id, consumable_type, $last_order_by
            ) t
            group by t.printer_id, t.consumable_type
            ";
        $settings['use_cache']           = true;
        $settings['ignore_control_hash'] = true;
        $settings['cache_timeout']       = 86400; // 24 hours
        $settings['cache_query']         = "SELECT * FROM {table_name}
            WHERE printer_name LIKE '[printer_name]' {$this->getRightCondition('printer_')}
              AND " . PluginIservicePrinter::getCMCondition('cm_field', 'printer_types_id', 'printer_states_id') . "
              AND ((costcenter is null AND '[costcenter]' = '%%') OR costcenter like '[costcenter]')
              AND otherserial LIKE '[otherserial]'
              AND serial LIKE '[serial]'
              AND supplier_name LIKE '[supplier_name]'
              AND min_days_to_visit < [min_days_to_visit]
              AND consumable_type in ([consumable_type])
              [tech_id]
              [supplier_id]
              [below_limit_exists]
            ";

        unset($settings['filters']['printer_location']);

        $settings['filters']['costcenter']         = [
            'type' => self::FILTERTYPE_TEXT,
            'format' => '%%%s%%',
            'header' => 'costcenter'
        ];
        $settings['filters']['below_limit_exists'] = [
            'type' => self::FILTERTYPE_SELECT,
            'caption' => 'Toner sub limită',
            'options' => ['' => '---', 't.min_estimate_percentage < t.avaliable_limit' => 'da', 't.min_estimate_percentage >= t.avaliable_limit' => 'nu'],
            'empty_value' => '',
            'zero_is_empty' => false,
            'header' => 'below_limit_exists',
            'format' => 'AND %s',
            'cache_override' => [
                'options' => ['' => '---', 'min_estimate_percentage < avaliable_limit' => 'da', 'min_estimate_percentage >= avaliable_limit' => 'nu'],
            ],
        ];
        $settings['filters']['min_days_to_visit']  = [
            'type' => self::FILTERTYPE_INT,
            'header_caption' => '< ',
            'empty_value' => '9999999',
            'zero_is_empty' => false,
            'header' => 'min_days_to_visit',
            'format' => '%d',
            'style' => 'text-align:right;width:6em;'
        ];
        $settings['filters']['consumable_type']    = [
            'type' => self::FILTERTYPE_SELECT,
            'options' => ["'cartridge', 'consumable'" => '---', "'cartridge'" => 'cartușe', "'consumable'" => 'consumabile'],
            'empty_value' => "'cartridge'",
            'header' => 'consumable_codes',
        ];

        $settings['columns']['ticket_status']['title'] = 'Acțiuni';
        $settings['columns']['costcenter']             = [
            'title' => 'Centru de cost',
        ];
        $settings['columns']['below_limit_exists']     = [
            'title' => 'Sub limită',
            'align' => 'center',
        ];
        $settings['columns']['min_days_to_visit']      = [
            'title' => 'Urm. livrare în',
            'align' => 'right',
            'format' => 'function:\GlpiPlugin\Iservice\Views\PrinterCounters::getMinDaysToVisitDisplay($row);',
        ];
        $settings['columns']['consumable_codes']       = [
            'title' => 'Cod hMarfa',
            'align' => 'center',
        ];
        $settings['columns']['estimate_percentages']   = [
            'title' => 'Consumabile instalate',
            'align' => 'right',
        ];
        $settings['columns']['days_to_visits']         = [
            'title' => 'Consumabil<br>disponibil<br>pentru',
            'align' => 'right',
        ];
        $settings['columns']['stocks']                 = [
            'title' => 'Stoc<br>bucăți / aparat',
            'align' => 'center',
        ];

        $settings['columns']['dba'] = [
            'title' => 'Nr. mediu bk',
            'align' => 'center',
            'format' => 'function:\GlpiPlugin\Iservice\Views\PrinterCounters::getDailyAverageDisplay("dba", $row);',
        ];
        $settings['columns']['dca'] = [
            'title' => 'Nr. mediu color',
            'align' => 'center',
            'format' => 'function:\GlpiPlugin\Iservice\Views\PrinterCounters::getDailyAverageDisplay("dca", $row);',
        ];

        return $settings;
    }

}

<?php
class PluginIserviceView_PrinterCounters2 extends PluginIserviceView_Printers
{

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

        $class .= " {$column}_link_$row_data[printer_id]";

        global $CFG_PLUGIN_ISERVICE;
        if (self::inProfileArray('tehnician', 'admin', 'super-admin')) {
            $result  = "<a id='{$column}_link_$row_data[printer_id]_$row_data[consumable_type]' class='$class' onclick='$(\"#{$column}_span_$row_data[printer_id]_$row_data[consumable_type]\").show();$(this).hide();' style='color:$color;' title='$title'>{$row_data[$column]}</a>";
            $result .= "<span id='{$column}_span_$row_data[printer_id]_$row_data[consumable_type]' style='display:none; white-space: nowrap;'>";
            $result .= "<input id='{$column}_edit_$row_data[printer_id]_$row_data[consumable_type]' class='{$column}_edit_$row_data[printer_id]' style='width:2em;' type='text' value='$data[dailyAverage]' />&nbsp;";
            $result .= "<i class='fa fa-check-circle' onclick='setDailyAverage(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/managePrinter.php?operation=set_$column\", $row_data[printer_id], $(\"#{$column}_edit_$row_data[printer_id]_$row_data[consumable_type]\").val(), \"{$column}\", \"_$row_data[consumable_type]\");' style='color:green'></i>&nbsp;";
            $result .= "<i class='fa fa-times' onclick='$(\"#{$column}_link_$row_data[printer_id]_$row_data[consumable_type]\").show();$(\"#{$column}_span_$row_data[printer_id]_$row_data[consumable_type]\").hide();'></i>";
            $result .= "</span><br/>";
            $result .= "({$row_data["c$column"]})";
        } else {
            $result = "<a id='{$column}_link_$row_data[printer_id]_$row_data[consumable_type]' class='$class' onclick='return false;' style='color:$color;' title='$title'>{$row_data[$column]}</a>";
        }

        return $result;
    }

    static function getUsageCoefficientDisplay($column, $row_data)
    {
        if ($column !== 'bk' && $row_data['printer_types_id'] != PluginIservicePrinter::ID_COLOR_TYPE) {
            return '';
        }

        if ($row_data['consumable_type'] === 'consumable') {
            return '';
        }

        $title = "Pentru mediu printabil $row_data[average_total_counter]:\n" . $row_data["calc_uc{$column}_explanation"];

        $class = 'clickable';
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

    static function getMinDaysToVisitDisplay($row_data)
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
                'action' => 'view.php?view=printerCounters2',
            ],
        ];
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

        $order_by      = PluginIserviceCommon::getArrayInputVariable($this->getRequestArrayName(), [])['order_by'] ?? '';
        $last_order_by = [
            'days_to_visits' => 'days_to_visit',
            'estimate_percentages' => 'available_percentage_estimate'
        ][$order_by] ?? 'cfc.plugin_fields_typefielddropdowns_id';

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
                      '\\nCartușe rezervă: ', t.changeable_count,
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
                  , cfp.total2_color_fact
                  , cfp.data_exp_fact
                  , CAST(cfp.data_fact as DATE) data_fact
                  , cfp.week_nr
                  , cfp.emaintenancefield emaintenance
                  , cfp.disableemfield disableem
                  , cfp.gps
                  , cfp.usageaddressfield
                  , cfp.noinvoicefield
                  , cfp.costcenterfield costcenter
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
                  , cfs.part_email_f1 supplier_email_facturi
                  , cfs.cartridge_management
                  , u.id tech_id
                  , CONCAT(IFNULL(CONCAT(u.realname, ' '),''), IFNULL(u.firstname, '')) tech_name
                  , ue.name external_user
                  , c.id cid
                  , ci.id ciid
                  , ci.ref consumable_code
                  , cfc.plugin_fields_typefielddropdowns_id
                  , t2.codbenef
                  , t2.numar_facturi_neplatite
                  , @consumableType := if (ci.ref like 'CTON%' or ci.ref like 'CCA%', 'cartridge', 'consumable') consumable_type
                  , @atc := if(coalesce(cfc.atcfield, 0) = 0, 1000, cfc.atcfield) average_total_counter
                  , @lc := if(coalesce(cfc.lcfield, 0) = 0, 1, cfc.lcfield) life_coefficient
                  , @ucc := if (coalesce(cfp.uccfield, 0) = 0, 0.75, cfp.uccfield) uccfield
                  , @ucm := if (coalesce(cfp.ucmfield, 0) = 0, 0.75, cfp.ucmfield) ucmfield
                  , @ucy := if (coalesce(cfp.ucyfield, 0) = 0, 0.75, cfp.ucyfield) ucyfield
                  , @ucbk := if (coalesce(cfp.ucbkfield, 0) = 0, 0.75, cfp.ucbkfield) ucbkfield
                  , @uc := if(@consumableType = 'consumable', 1, case cfc.plugin_fields_typefielddropdowns_id
                                                                    when 2 then @ucc
                                                                    when 3 then @ucm
                                                                    when 4 then @ucy
                                                                    else @ucbk 
                                                                 end) usage_coefficient
                  , @dba := coalesce(cfp.dailybkaveragefield, 0) dba
                  , @dca := coalesce(cfp.dailycoloraveragefield, 0) dca
                  , @da := if(cfc.plugin_fields_typefielddropdowns_id in (2, 3, 4), if(@dca = 0, 100, @dca), if(@dba + @dca = 0, 100, @dba + @dca)) daily_average_counter
                  , @atl := coalesce(cfs.atlfield, 0.4) avaliable_limit
                  , @changeable_count := coalesce(ccc.count, 0) changeable_count
                  , @compatible_printer_count := coalesce(ccpc.count, 0) compatible_printer_count
                  , @installedCounter := if (cfc.plugin_fields_typefielddropdowns_id in (2, 3, 4), it.total2_color, it.total2_black + it.total2_color) installed_counter
                  , @installedDate := it.data_luc installed_date
                  , @lastClosedCounter := if (cfc.plugin_fields_typefielddropdowns_id in (2, 3, 4), plct.total2_color, plct.total2_black + plct.total2_color) last_closed_counter
                  , @lastClosedDate := plct.data_luc last_closed_date
                  , @estimateCounter := @lastClosedCounter + datediff(NOW(), @lastClosedDate) * @da estimate_counter
                  , @availableEstimate := 1 - round((@estimateCounter - @installedCounter) / (@atc * @lc * @uc), 2) available_percentage_estimate
                  , if (@availableEstimate < @atl, 'da', 'nu') below_limit
                  , round(coalesce((@atc * @lc * @uc - (@lastClosedCounter - @installedCounter)) / @da, 180) - datediff(NOW(), @lastClosedDate) + (@atc * @lc * @uc / @da) * (@changeable_count / @compatible_printer_count)) days_to_visit
                  , concat('<span title=\"', coalesce(ccc.cids, 'nu există cartușe compatibile'), '\">', @changeable_count, '</span> / <span title=\"', coalesce(ccpc.pids, 'nu există aparate compatibile'), '\">', @compatible_printer_count, '</span>') in_stock
                  , getPrinterDailyAverage(p.id, 0) cdba
                  , getPrinterDailyAverage(p.id, 1) cdca
                from glpi_plugin_iservice_printers p
                left join glpi_plugin_fields_printercustomfields cfp on cfp.items_id = p.id and cfp.itemtype = 'Printer'
                left join glpi_plugin_iservice_printers_last_closed_tickets plct on plct.printers_id = p.id
                left join glpi_locations l on l.id = p.locations_id
                left join glpi_infocoms i on i.items_id = p.id and i.itemtype = 'Printer'
                left join glpi_suppliers s on s.id = i.suppliers_id
                left join glpi_plugin_fields_suppliercustomfields cfs on cfs.items_id = s.id and cfs.itemtype = 'Supplier'
                left join glpi_users u on u.id = p.users_id_tech
                left join glpi_users ue on ue.id = p.users_id
                left join glpi_cartridges c on c.printers_id = p.id and c.date_use is not null and c.date_out is null
                left join glpi_cartridgeitems  ci on ci.id = c.cartridgeitems_id
                left join glpi_plugin_fields_cartridgeitemcartridgecustomfields cfc on cfc.items_id = ci.id and cfc.itemtype = 'CartridgeItem'
                left join glpi_plugin_iservice_consumable_compatible_printers_counts ccpc on ccpc.id = c.id
                left join glpi_plugin_iservice_consumable_changeable_counts ccc on ccc.id = c.id
                left join glpi_tickets it on it.id = c.tickets_id_use
                left join glpi_plugin_iservice_printer_usage_coefficients pbuc on pbuc.printers_id = p.id and pbuc.plugin_fields_typefielddropdowns_id = 1 
                left join glpi_plugin_iservice_printer_usage_coefficients pcuc on pcuc.printers_id = p.id and pcuc.plugin_fields_typefielddropdowns_id = 2 
                left join glpi_plugin_iservice_printer_usage_coefficients pmuc on pmuc.printers_id = p.id and pmuc.plugin_fields_typefielddropdowns_id = 3 
                left join glpi_plugin_iservice_printer_usage_coefficients pyuc on pyuc.printers_id = p.id and pyuc.plugin_fields_typefielddropdowns_id = 4 
                left join (select codbenef, count(codbenef) numar_facturi_neplatite
                           from hmarfa_facturi 
                           where (codl = 'f' or stare like 'v%') and tip like 'tf%'
                           and valinc-valpla > 0
                           group by codbenef) t2 on t2.codbenef = cfs.cod_hmarfa
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
              AND " . PluginIservicePrinter::getCMCondition('cartridge_management', 'printer_types_id', 'printer_states_id') . "
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
            'format' => 'function:PluginIserviceView_PrinterCounters2::getMinDaysToVisitDisplay($row);',
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
            'format' => 'function:PluginIserviceView_PrinterCounters2::getDailyAverageDisplay("dba", $row);',
        ];
        $settings['columns']['dca'] = [
            'title' => 'Nr. mediu color',
            'align' => 'center',
            'format' => 'function:PluginIserviceView_PrinterCounters2::getDailyAverageDisplay("dca", $row);',
        ];

        return $settings;
    }

}

<?php

global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

if (!function_exists('iservice_custom_command_check_em_csv')) {

    function iservice_custom_command_check_em_csv()
    {
        global $CFG_PLUGIN_ISERVICE;
        $printers             = PluginIserviceDB::getQueryResult(
            "
        select
              p.id id
            , " . PluginIservicePrinter::getSerialFieldForEM('p') . " serial
            , p.cfid cfid
        from glpi_plugin_iservice_printers p
        where em_field = 1
        "
        ) ?: [];
        $csv_data             = PluginIserviceEmaintenance::getDataFromCsvs();
        $printer_customfields = new PluginFieldsPrinterprintercustomfield();
        foreach ($printers as $printer_fields) {
            if (($csv_data[$printer_fields['serial']]['effective_date_field'] ?? '#empty#import#data#') === '#empty#import#data#') {
                $last_read_date = '1970-01-01';
            } elseif (!empty($csv_data[$printer_fields['serial']]['effective_date_field']['error'])) {
                $last_read_date = '1980-01-01';
            } else {
                $last_read_date = $csv_data[$printer_fields['serial']]['effective_date_field'] ?? '1970-01-01';
            }

            if (is_array($last_read_date)) {
                echo "<pre>" . print_r($last_read_date, true) . "</pre>";
                continue;
            }

            $printer_customfields->update(
                [
                    'id'            => $printer_fields['cfid'],
                    'last_read_field' => $last_read_date
                ]
            );
        }

        file_put_contents(PluginIserviceConfig::getConfigValue('emaintenance.csv_last_check_date_file'), date('Y-m-d H:i:s'));
    }

}

$fileName = PluginIserviceConfig::getConfigValue('emaintenance.csv_last_check_date_file');
if (!file_exists($fileName) || false === ($csv_check_date = @file_get_contents($fileName))) {
    $csv_check_date = 'unknown';
}

return [
    'command_before' => 'check_em_csv',
    'query'          => "
        select
              p.id pid
            , p.name 
            , s.name supplier_name
            , p.last_read_field 
            , if(
				    p.last_read_field = '',
				    'empty',
				    if(
				        datediff(curdate(), p.last_read_field) > 15000,
				        'infinite',
				        if(
				            datediff(curdate(), p.last_read_field) > 14000,
				            'unknown',
				            lpad(datediff(curdate(), p.last_read_field), 5, '0')
				        )
				    )
            ) as days_since_last_read
            , u.name tech_park_name
        from glpi_plugin_iservice_printers p
        join glpi_users u on u.id = p.users_id_tech
        join glpi_infocoms ic on ic.items_id = p.id and ic.itemtype = 'Printer'
        join glpi_suppliers s on s.id = ic.suppliers_id
        where p.is_deleted = 0
          and p.em_field = 1
          and coalesce(p.disable_em_field, 0) = 0
          and coalesce(p.snooze_read_check_field, '0000-00-00') <= CURDATE()
          and DATEDIFF(CURDATE(), p.last_read_field) > 4
        order by case when days_since_last_read = 'unknown' or days_since_last_read = 'infinite' then days_since_last_read end desc,
        		 case when days_since_last_read != 'unknown' and days_since_last_read != 'infinite'  then days_since_last_read end asc,
                 u.name
        ",
    'test'           => [
        'type'            => 'compare_query_count',
        'zero_result'     => [
            // 'summary_text' => "On <b><i>$csv_check_date</i></b> there were no printers with old data from E-maintenance CSV",
            'summary_text' => "There are no printers with old data from E-maintenance CSV",
            'result_type'  => 'em_info'
        ],
        'positive_result' => [
        // 'summary_text'   => "On <b><i>$csv_check_date</i></b> there were {count} printers with old data from E-maintenance CSV",
            'summary_text'   => "There are {count} printers with old data from E-maintenance CSV",
            'iteration_text' => "For technician <b><i>[tech_park_name]</i></b> last counter is <b><i style='color: green' title='Unknown: the printer is in the csv, but never transmitted data\nInfinite: the printer does not exist in the csv'>[days_since_last_read]</i></b> days old for printer <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/printer.form.php?id=[pid]' target='_blank'>[name]</a> at <b><i>[supplier_name]</i></b>. <span id='snooze-read-check-[pid]'> <a href='javascript:void(0);' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/managePrinter.php?id=[pid]&operation=snooze_read_check&snooze=\" + \$(\"#snooze-for-[pid]\").val(), \"\", function(message) {if (message !== \"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {\$(\"#snooze-read-check-[pid]\").remove();}});return false;'>» Snooze «</a> check for <input id='snooze-for-[pid]' type='text' value ='3' style='width:1em;'> days</span> or  <span id='manage-em-[pid]'><a href='javascript:void(0);' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/managePrinter.php?id=[pid]&operation=exclude_from_em\", \"\", function(message) {if (message !== \"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {\$(\"#manage-em-[pid]\").remove();}});'>» Exclude from EM «</a></span>",
            'result_type'    => 'em_warning'
        ],
    ],
    'schedule'       => [
        'display_last_result' => true,
        'h:m'               => ['7:46', '14:01', '17:31'],
        'weekdays'            => [1, 2, 3, 4, 5],
        'ignore_text'         => [
            'hours'    => "Checked only on workdays at 7:46, 14:01 and 17:31",
            'weekdays' => "Checked only on workdays"
        ]
    ]
];

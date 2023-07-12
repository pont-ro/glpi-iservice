<?php

global $CFG_GLPI;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

return [
    'query' => "
        select
            p.id pid
          , p.serial
          , case p.printertypes_id
              when " . PluginIservicePrinter::ID_COLOR_TYPE . " then 'set_color_coefficients'
              when " . PluginIservicePrinter::ID_PLOTTER_TYPE . " then 'set_color_coefficients'
              else 'clear_color_coefficients'
            end operation
          , pt.name printer_type
          , cfp.daily_color_average_field 
          , cfp.uc_cyan_field
          , cfp.uc_magenta_field
          , cfp.uc_yellow_field 
        from glpi_printers p
        join glpi_plugin_fields_printerprintercustomfields cfp on cfp.items_id = p.id and cfp.itemtype = 'Printer'
        left join glpi_printertypes pt on pt.id = p.printertypes_id
        where p.is_deleted = 0
          and (
                (
                      p.printertypes_id != " . PluginIservicePrinter::ID_COLOR_TYPE . "
                  and p.printertypes_id != " . PluginIservicePrinter::ID_PLOTTER_TYPE . "
                  and (   cfp.daily_color_average_field != 0 
                       or cfp.uc_cyan_field != 0
                       or cfp.uc_magenta_field != 0
                       or cfp.uc_yellow_field != 0
                      )
                )
                or
                (
                  (
                       p.printertypes_id = " . PluginIservicePrinter::ID_COLOR_TYPE . "
                    or p.printertypes_id = " . PluginIservicePrinter::ID_PLOTTER_TYPE . "
                  )
                  and (   cfp.daily_color_average_field = 0 
                       or cfp.uc_cyan_field = 0
                       or cfp.uc_magenta_field = 0
                       or cfp.uc_yellow_field = 0
                      )
                )
              )
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no printers with invalid coefficients',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} printers with invalid coefficients',
            // 'iteration_text' => "Printer <a href='$CFG_GLPI[root_doc]/front/printer.form.php?id=[pid]' target='_blank'>[pid]</a> with serial [serial] is of type [printer_type] but has 'Daily color average' [daily_color_average_field] and the following usage coefficients: [uc_cyan_field], [uc_magenta_field], [uc_yellow_field].",
            'iteration_text' => "Printer <a href='$CFG_GLPI[root_doc]/front/printer.form.php?id=[pid]' target='_blank'>[pid]</a> with serial [serial] is of type [printer_type] but has 'Daily color average' [daily_color_average_field] and the following usage coefficients: [uc_cyan_field], [uc_magenta_field], [uc_yellow_field]. <a id='fix-printer-[pid]' href='javascript:void(0);' onclick='ajaxCall(\"$CFG_GLPI[root_doc]/plugins/iservice/ajax/managePrinter.php?id=[pid]&operation=[operation]\", \"\", function(message) {if (message !== \"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {\$(\"#fix-printer-[pid]\").remove();}});'>»»» FIX «««</a>",
        ],
    ],
];

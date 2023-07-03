<?php

global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

return [
    'query' => "
        select
              p.id pid
            , p.name
            , pm.id pmid
            , pm.name model
            , CONCAT(IFNULL(CONCAT(u.realname, ' '),''), IFNULL(u.firstname, '')) tech_park_name
        from glpi_plugin_iservice_printers p
        join glpi_printermodels pm on pm.id = p.printermodels_id
        join glpi_users u on u.id = p.users_id_tech
        join glpi_plugin_fields_printercustomfields cfp on cfp.items_id = p.id and cfp.itemtype = 'Printer' and cfp.emaintenancefield = 0 and cfp.disableemfield = 0
        join glpi_plugin_fields_printermodelprintermodelcustomfields cfpm on cfpm.items_id = p.printermodels_id and cfpm.itemtype = 'PrinterModel' and cfpm.emfield = 1
        where p.is_deleted = 0
        ",
    'test' => [
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no printers for which E-maintenance is not enabled, but have models that support E-Maintenance',
            'result_type' => 'em_info'
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} printers for which E-maintenance is not enabled, but have models that support E-Maintenance',
            'iteration_text' => "E-maintenance is not enabled for printer <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/printer.form.php?id=[pid]' target='_blank'>[name]</a> and park technician <b><i>[tech_park_name]</i></b>, but it has a model that supports E-Maintenance. <span id='manage-em-[pid]'><a href='javascript:void(0);' onclick='ajaxCall(\"$CFG_GLPI[root_doc]/plugins/iservice/ajax/managePrinter.php?id=[pid]&operation=enable_em\", \"\", function(message) {if (message !== \"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {\$(\"#manage-em-[pid]\").remove();}});'>» Enable EM «</a> or <a href='javascript:void(0);' onclick='ajaxCall(\"$CFG_GLPI[root_doc]/plugins/iservice/ajax/managePrinter.php?id=[pid]&operation=exclude_from_em\", \"\", function(message) {if (message !== \"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {\$(\"#manage-em-[pid]\").remove();}});'>» Exclude from EM «</a></span>",
            'result_type' => 'em_error'
        ],
    ],
];

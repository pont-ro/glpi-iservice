<?php
global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
$data_luc_max_difference = 3;
return [
    'query' => "
        select
            t.id tid
          , t.total2_color
          , p.id pid
          , p.serial
        from glpi_tickets t
        join glpi_items_tickets it on it.tickets_id = t.id and itemtype = 'Printer'
        join glpi_printers p on p.id = it.items_id 
        where t.is_deleted = 0
          and p.printertypes_id != " . PluginIservicePrinter::ID_COLOR_TYPE . "
          and p.printertypes_id != " . PluginIservicePrinter::ID_PLOTTER_TYPE . "
          and t.total2_color <> 0
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => "There are no tickets with color counter but no color printer",
        ],
        'positive_result' => [
            'summary_text' => "There are {count} tickets with color counter but no color printer",
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]&mode=9999' target='_blank'>[tid]</a> has non color printer <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/printer.form.php?id=[pid]' target='_blank'>[pid]</a>  with serial [serial], but color counter [total2_color]. <a id='fix-ticket-[tid]' href='javascript:void(0);' onclick='ajaxCall(\"$CFG_GLPI[root_doc]/plugins/iservice/ajax/manageTicket.php?id=[tid]&operation=remove_color_counter\", \"\", function(message) {if (message !== \"" . PluginIserviceCommon::RESPONSE_OK . "\") {alert(message);} else {\$(\"#fix-ticket-[tid]\").remove();}});'>»»» FIX «««</a></b>",
        ],
    ],
];

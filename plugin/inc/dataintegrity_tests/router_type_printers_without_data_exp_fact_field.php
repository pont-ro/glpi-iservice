<?php
global $CFG_GLPI;
return [
    'query' => "
    SELECT p.id pid
    FROM glpi_printers p
    LEFT JOIN glpi_plugin_fields_printercustomfields pcf on pcf.items_id = p.id and pcf.itemtype = 'Printer'
    WHERE p.is_deleted = 0 
      AND p.printertypes_id =" . PluginIservicePrinter::ID_ROUTER_TYPE . "
      AND pcf.data_exp_fact IS NULL
      ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no routers with empty data_exp_fact customfield',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} routers with empty data_exp_fact customfield',
            'iteration_text' => "Router <a href='$CFG_GLPI[root_doc]/front/printer.form.php?id=[pid]' target='_blank'>[pid]</a> has empty data_exp_fact customfield"
        ],
    ]
];

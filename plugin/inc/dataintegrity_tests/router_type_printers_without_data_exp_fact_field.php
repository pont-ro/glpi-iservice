<?php
global $CFG_GLPI;
return [
    'query' => "
    SELECT p.id pid
    FROM glpi_printers p
    LEFT JOIN glpi_plugin_fields_printerprintercustomfields pcf on pcf.items_id = p.id and pcf.itemtype = 'Printer'
    WHERE p.is_deleted = 0 
      AND p.printertypes_id =" . PluginIservicePrinter::ID_ROUTER_TYPE . "
      AND pcf.invoice_expiry_date_field IS NULL
      ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no routers with empty invoice_expiry_date_field customfield',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} routers with empty invoice_expiry_date_field customfield',
            'iteration_text' => "Router <a href='$CFG_GLPI[root_doc]/front/printer.form.php?id=[pid]' target='_blank'>[pid]</a> has empty invoice_expiry_date_field customfield"
        ],
    ]
];

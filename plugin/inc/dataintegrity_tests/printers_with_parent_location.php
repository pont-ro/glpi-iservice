<?php
global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select 
               p.id pid, 
               p.serial, 
               l.completename location
        from glpi_plugin_iservice_printers p
        join glpi_infocoms i on i.items_id = p.id and i.itemtype = 'Printer'
        join glpi_suppliers s on s.id = i.suppliers_id and s.is_deleted = 0
        join glpi_plugin_fields_suppliercustomfields fsc on fsc.items_id = s.id and fsc.forcelocationparentfield = 1
        join glpi_locations l ON l.id = p.locations_id and l.locations_id = 0
        where p.is_deleted = 0
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no printers that should have location with parent location, but have no parent location',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} printers that should have location with parent location, but have no parent location',
            'iteration_text' => "Printer <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/printer.form.php?id=[pid]' target='_blank'>[pid]</a> with serial <b>[serial]</b> should have a location with parent location, but has location <b>[location]</b>",
        ],
    ],
];

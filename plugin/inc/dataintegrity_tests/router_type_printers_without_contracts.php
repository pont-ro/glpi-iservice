<?php
global $CFG_PLUGIN_ISERVICE;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

return [
    'query' => "
        SELECT
            p.id pid
          , p.name p_name
          , s.id sid
          , s.name s_name
          , COALESCE(l.name, '«empty»') l_name
        FROM glpi_printers p
        LEFT JOIN glpi_contracts_items ci ON p.id = ci.items_id and ci.itemtype = 'Printer'
        LEFT JOIN glpi_infocoms ic ON ic.items_id = p.id and ic.itemtype = 'Printer'
        LEFT JOIN glpi_locations l ON p.locations_id = l.id
        LEFT JOIN glpi_suppliers s ON s.id = ic.suppliers_id
        WHERE p.is_deleted = 0 
          AND p.printertypes_id =" . PluginIservicePrinter::ID_ROUTER_TYPE . "
          AND ci.id is null 
          AND (s.id <> " . IserviceToolBox::getExpertLineId() . " OR COALESCE(l.name, '') <> 'Fara cartela')
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => "There are no routers without contract not at 'Expert Line' and not 'fara cartela'",
        ],
        'positive_result' => [
            'summary_text' => "There are {count} routers without contract not at 'Expert Line' and not 'fara cartela'",
            'iteration_text' => "Router <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/printer.form.php?id=[pid]' target='_blank'>[p_name]</a> at partner <b>[s_name]</b> ([sid]) with location <b>[l_name]</b> has no contract"
        ],
    ]
];

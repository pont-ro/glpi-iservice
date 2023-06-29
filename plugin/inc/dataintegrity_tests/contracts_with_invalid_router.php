<?php
global $CFG_GLPI;
return [
    'query' => "
        SELECT
            c.id cid
          , c.name contract
          , s.id sid
          , s.name s_name
          , p.id pid
          , p.name p_name
          , ps.id psid
          , ps.name ps_name
          , IF(ci.id is null, 'and has no router', CONCAT('but router <b>', p.name, '</b> (', p.id, ') beeing at partner <b>', ps.name, '</b> (', ps.id, ')')) error_text
        FROM glpi_contracts c
        JOIN glpi_contracts_suppliers cs ON cs.contracts_id = c.id
        JOIN glpi_suppliers s ON s.id = cs.suppliers_id
        LEFT JOIN glpi_contracts_items ci ON ci.contracts_id = c.id and ci.itemtype = 'Printer'
        LEFT JOIN glpi_printers p ON p.is_deleted = 0 and p.id = ci.items_id and p.printertypes_id = " . PluginIservicePrinter::ID_ROUTER_TYPE . " 
        LEFT JOIN glpi_infocoms i ON i.items_id = p.id AND i.itemtype = 'Printer'
        LEFT JOIN glpi_suppliers ps ON ps.id = i.suppliers_id
        WHERE c.is_deleted = 0 AND  c.name like 'VDF_%' 
          AND (cs.suppliers_id != i.suppliers_id OR ci.id is null)
      ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => "There are no 'VDF_' contracts with no or invalid router",
        ],
        'positive_result' => [
            'summary_text' => "There are {count} 'VDF_' contracts with no or invalid router",
            'iteration_text' => "Contract <a href='$CFG_GLPI[root_doc]/front/contract.form.php?id=[cid]' target='_blank'>[contract]</a> has partner <b>[s_name]</b> ([sid]) [error_text]"
        ],
    ]
];

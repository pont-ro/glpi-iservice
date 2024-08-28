<?php

global $CFG_GLPI;
global $CFG_PLUGIN_ISERVICE;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

return [
    'query' => "
        select
            ci.contracts_id cid
            , ci.items_id pid
            , cs.suppliers_id
            , i.suppliers_id sid
            , s.name partner_name
            , c.name contract
            from glpi_contracts_items ci
            left join glpi_contracts c on ci.contracts_id = c.id
            left join glpi_contracts_suppliers cs on cs.contracts_id = ci.contracts_id
            join glpi_infocoms i on i.itemtype = 'printer' and i.items_id = ci.items_id
            join glpi_suppliers s on s.id = i.suppliers_id
            where cs.suppliers_id is null
            group by ci.contracts_id
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no contracts without partner',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} contracts without partner',
            'iteration_text' => "Contract <a href='$CFG_GLPI[root_doc]/front/contract.form.php?id=[cid]' target='_blank'><b>[contract]</b></a> has no partner. The <a href='$CFG_GLPI[root_doc]/front/printer.form.php?id=[pid]' target='_blank'><b>printer (ID: [pid])</b></a> on the contract is at partner <b>[partner_name]</b> <a id='fix-contract-[cid]' href='javascript:void(0);' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/setContractSupplier.php?supplier_id=[sid]&contract_id=[cid]\", \"\", function(message) {if (message !== \"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {\$(\"#fix-contract-[cid]\").remove();}});'>»»» FIX «««</a>",
        ],
    ],
];

// QUERY to fix the issue.
/*
INSERT INTO glpi_contracts_suppliers (contracts_id, suppliers_id)
SELECT
    ci.contracts_id,
    i.suppliers_id
FROM glpi_contracts_items ci
JOIN glpi_infocoms i ON i.itemtype = 'Printer' AND i.items_id = ci.items_id
LEFT JOIN glpi_contracts_suppliers cs ON cs.contracts_id = ci.contracts_id
WHERE cs.suppliers_id IS NULL
GROUP BY ci.contracts_id
*/

<?php
global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select *
        from
            (
            select
                  p.id pid
                , p.serial
                , count(ci.id) contract_count
                , group_concat(concat('<a href=\"/front/contract.form.php?id=', ci.contracts_id, '\"  target=\"_blank\">', ci.contracts_id, '</a>') separator ', ') contract_ids
            from glpi_printers p
            join glpi_contracts_items ci on ci.items_id = p.id and ci.itemtype = 'Printer'
            join glpi_contracts c on c.id = ci.contracts_id and c.is_deleted = 0
            where p.is_deleted = 0
            group by p.id
            ) t
        where t.contract_count > 1
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no printers present on more then one contract',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} printers present on more then one contract',
            'iteration_text' => "Printer <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/printer.form.php?id=[pid]' target='_blank'>[pid]</a> with serial [serial] is present on contracts [contract_ids]",
        ],
    ],
];

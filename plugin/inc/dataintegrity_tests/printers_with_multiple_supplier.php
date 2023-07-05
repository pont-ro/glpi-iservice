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
                , count(i.id) supplier_count
                , group_concat(concat('<a href=\"/front/supplier.form.php?id=', i.suppliers_id, '\"  target=\"_blank\">', i.suppliers_id, '</a>') separator ', ') suppliers_ids
            from glpi_printers p
            join glpi_infocoms i on i.items_id = p.id and i.itemtype = 'Printer'
            join glpi_suppliers s on s.id = i.suppliers_id and s.is_deleted = 0
            where p.is_deleted = 0
            group by p.id
            ) t
        where t.supplier_count > 1
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no printers present with more then one supplier',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} printers present with more then one supplier',
            'iteration_text' => "Printer <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/printer.form.php?id=[pid]' target='_blank'>[pid]</a> with serial [serial] is present with suppliers [suppliers_ids]",
        ],
    ],
];

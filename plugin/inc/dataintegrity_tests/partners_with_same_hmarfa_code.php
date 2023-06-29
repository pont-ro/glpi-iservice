<?php
global $CFG_GLPI;
return [
    'query' => "
        select
            cfs1.cod_hmarfa
        , group_concat(concat('<a href=\"$CFG_GLPI[root_doc]/front/supplier.form.php?id=', cfs1.items_id, '\" target=\"_blank\">', s1.name, '</a>') separator ', ') partners
        from glpi_plugin_fields_suppliercustomfields cfs1
        join glpi_suppliers s1 on s1.id = cfs1.items_id and s1.is_deleted = 0
        join glpi_plugin_fields_suppliercustomfields cfs2 on cfs2.cod_hmarfa = cfs1.cod_hmarfa
        join glpi_suppliers s2 on s2.id = cfs2.items_id and s2.is_deleted = 0
        where cfs1.items_id != cfs2.items_id and coalesce(cfs1.cod_hmarfa, '') != ''
        group by cfs1.cod_hmarfa
        ",
    'test' => [
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no hMarfa codes for more then one partner',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} hMarfa codes for more then one partner',
            'iteration_text' => "hMarfa code <b>[cod_hmarfa]</b> is the same for partners [partners]",
        ],
    ],
];

<?php
global $CFG_GLPI;
return [
    'query' => "
            select s.id, s.name, scf.groupfield
            from glpi_suppliers s
            left join glpi_plugin_fields_suppliercustomfields scf on scf.items_id = s.id and scf.itemtype = 'Supplier'
            where s.is_deleted = 0 and coalesce(scf.groupfield, '') not like concat('%', scf.items_id, '%');
            ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no partners with invalid group',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} partners with invalid group',
            'iteration_text' => "Partener <a href='$CFG_GLPI[root_doc]/front/supplier.form.php?id=[id]' target='_blank'>[name]</a> has group \"[groupfield]\", which does not include [id]",
        ],
    ],
];

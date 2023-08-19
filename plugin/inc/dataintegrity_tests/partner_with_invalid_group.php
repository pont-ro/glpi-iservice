<?php
global $CFG_GLPI;
return [
    'query' => "
            select s.id, s.name, s.group_field
            from glpi_plugin_iservice_suppliers s
            where s.is_deleted = 0 and coalesce(s.group_field, '') not like concat('%', s.id, '%');
            ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no partners with invalid group',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} partners with invalid group',
            'iteration_text' => "Partener <a href='$CFG_GLPI[root_doc]/front/supplier.form.php?id=[id]' target='_blank'>[name]</a> has group \"[group_field]\", which does not include [id]",
        ],
    ],
];

<?php
global $CFG_GLPI;
return [
    'query' => "
        select p.id pid, p.serial
        from glpi_printers p
        left join glpi_printertypes pt on pt.id = p.printertypes_id
        where p.is_deleted = 0
          and pt.id is null",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no printers without type',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} printers without type',
            'iteration_text' => "Printer <a href='$CFG_GLPI[root_doc]/front/printer.form.php?id=[pid]' target='_blank'>[pid]</a> with serial [serial] has no type",
        ],
    ],
];

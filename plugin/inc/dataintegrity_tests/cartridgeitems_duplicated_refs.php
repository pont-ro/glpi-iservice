<?php
global $CFG_GLPI;
return [
    'query' => "
        select ci1.ref, GROUP_CONCAT(CONCAT(\"<a href='$CFG_GLPI[root_doc]/front/cartridgeitem.form.php?id=\", ci1.id, \"' target='_blank'>\", ci1.id, '</a>') SEPARATOR ', ') ids
        from glpi_cartridgeitems ci1
        join glpi_cartridgeitems ci2 on ci1.ref = ci2.ref and ci1.id != ci2.id
        where ci1.is_deleted = 0 and ci2.is_deleted = 0
        group by ci1.ref;
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridge types with the same hMarfa code',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridge types with the same hMarfa code',
            'iteration_text' => 'hMarfa code [ref] is the same for the following ids: [ids]',
        ],
    ],
];

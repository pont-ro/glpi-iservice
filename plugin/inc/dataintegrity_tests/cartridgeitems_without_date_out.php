<?php
global $CFG_GLPI;
return [
    'query' => "
        select gci.id gciid, gci.name gciname
        from glpi_cartridgeitems gci
        join glpi_cartridges gc on gc.cartridgeitems_id = gci.id and gc.date_out is null
        where gci.is_deleted = 1
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no deleted cartridge types for which not emptied cartridges exist',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} deleted cartridge types for which not emptied cartridges exist',
            'iteration_text' => "Cartridge type <a href='$CFG_GLPI[root_doc]/front/cartridgeitem.form.php?id=[gciid]' target='_blank'>[gciname] ([gciid])</a> is deleted, but it has not emptied cartridges ",
        ],
    ],
];

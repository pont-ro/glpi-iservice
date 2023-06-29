<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select c.id, c.tickets_id_use tid, c.date_use
        from glpi_cartridges c
        where c.printers_id = 0 and c.date_use is not null
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no installed cartridges without printer id',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} installed cartridges without printer id',
            'iteration_text' => "Cartridge [id] was installed on [date_use] by ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]&mode=9999' target='_blank'>[tid]</a> on no printer",
        ],
    ],
];

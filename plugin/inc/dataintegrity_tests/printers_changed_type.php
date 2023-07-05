<?php
global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select 
               p.id pid, 
               p.serial, 
               l.date_mod,
               l.user_name
        from glpi_logs l
        join glpi_printers p on p.id = l.items_id and l.itemtype = 'Printer'
        where p.is_deleted = 0
          and l.old_value = 'alb-negru (3)'
          and l.new_value = 'color (4)'
          and l.date_mod > '" . date('Y-m-d H:i:s', strtotime('-1 day')) . "'
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no printers that changed type from alb-negru to color in the last 24 hours',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} printers that changed type from alb-negru to color in the last 24 hours',
            'iteration_text' => "On [date_mod] [user_name] changed the type of the printer <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/printer.form.php?id=[pid]' target='_blank'>[pid]</a> with serial <b>[serial]</b> form alb-negru to color",
        ],
    ],
];

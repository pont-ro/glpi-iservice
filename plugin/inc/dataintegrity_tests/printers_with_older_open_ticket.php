<?php
global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select
              pctc.printers_id pid
            , potc.min_effective_date first_open_effective_date
            , pctc.max_effective_date last_closed_effective_date
        from glpi_plugin_iservice_printer_unclosed_ticket_counts potc 
        join glpi_plugin_iservice_printer_closed_ticket_counts pctc on pctc.printers_id = potc.printers_id
                                                                   and pctc.max_effective_date > potc.min_effective_date
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no printers that have older open tickets than the last closed one',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} printers that have older open tickets than the last closed one',
            'iteration_text' => "Printer <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/printer.form.php?id=[pid]' target='_blank'>[pid]</a> has an open ticket with <b>[first_open_effective_date]</b> effective date, but has a closed ticket with <b>[last_closed_effective_date]</b> effective date, see <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Operations&operations0%5Bprinter_id%5D=[pid]' target='_blank'>operations list</a>",
        ],
    ],
];
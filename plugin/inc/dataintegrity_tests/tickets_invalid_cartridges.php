<?php
global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select t.id tid, group_concat(ct.cartridges_id separator ', ') cartridge_ids, p.id pid, p.printermodels_id, c.id cid, cp.id cp_id
        from glpi_tickets t
        left join glpi_items_tickets it on it.tickets_id = t.id and it.itemtype = 'Printer'
        join glpi_plugin_iservice_cartridges_tickets ct on ct.tickets_id = t.id
        left join glpi_cartridges c on c.id = ct.cartridges_id
        left join glpi_printers p on p.id = it.items_id
        left join glpi_cartridgeitems_printermodels cp on cp.printermodels_id = p.printermodels_id and cp.cartridgeitems_id = c.cartridgeitems_id
        where cp.id is null
        group by t.id;
        ",
    'test' => [
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no tickets with invalid cartridges',
        ],
        'positive_result' => [
            'alert' => true,
            'summary_text' => 'There are {count} tickets with invalid cartridges',
            'iteration_text' => [
                "return empty('[pid]');" => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]&mode=9999' target='_blank'>[tid]</a> has no printer but the following cartridges: [cartridge_ids]",
                "return empty('[cid]');" => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]&mode=9999' target='_blank'>[tid]</a> has the following cartridges that do not exist anymore: [cartridge_ids]",
                "return empty('[cp_id]');" => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]&mode=9999' target='_blank'>[tid]</a> has cartridges ([cartridge_ids]) that are not compatible with the printer on the ticket: <a href='$CFG_GLPI[root_doc]/front/printer.form.php?id=[pid]'>[pid]</a>",
            ]
        ],
    ],
];

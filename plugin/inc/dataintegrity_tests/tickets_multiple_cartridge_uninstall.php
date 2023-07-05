<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select *
        from (
              select count(*) `count`, group_concat('<a href=\"$CFG_PLUGIN_ISERVICE[root_doc]/front/view.php?view=cartridges&cartridges0[date_use_null]=1&cartridges0[date_out_null]=1&filtering=1&cartridges0[id]=', c.id, '\"  target=\"_blank\">', c.id, '</a>' separator ', ') cartridge_ids, cfc.mercurycodefield mercurycode, c.plugin_fields_typefielddropdowns_id type_id, td.completename type_name, tickets_id_out ticket_id
              from glpi_cartridges c
              join glpi_plugin_fields_cartridgeitemcartridgecustomfields cfc on cfc.items_id = c.cartridgeitems_id and cfc.itemtype = 'CartridgeItem'
              left join glpi_plugin_fields_typefielddropdowns td on td.id = c.plugin_fields_typefielddropdowns_id
              where c.tickets_id_out is not null and c.tickets_id_use is not null
              group by tickets_id_out, cfc.mercurycodefield, c.plugin_fields_typefielddropdowns_id
             ) t
        where `count` > 1
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no tickets that uninstall more than 1 cartridges with the same mercury code'
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} tickets that uninstall more than 1 cartridges with the same mercury code',
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[ticket_id]&mode=9999' target='_blank'>[ticket_id]</a> uninstalls [count] cartridges with the same type ([type_name]) and mercury code ([mercurycode]): [cartridge_ids]",
        ],
    ],
];

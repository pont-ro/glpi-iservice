<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select
            sid
          , s.name
          , count(cid) cartridge_count
          , group_concat('<a href=\"$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Cartridges&cartridges0[date_use_null]=1&cartridges0[date_out_null]=1&filtering=1&cartridges0[id]=', cid, '\"  target=\"_blank\">', cid, '</a>' separator ', ') cartridge_ids
        from (
          select c.id cid, c.suppliers_id_field sid, count(t1.pid) printer_count
          from glpi_plugin_iservice_cartridges c
          left join (
            select c.id cid, p.id pid, cl.id cartridge_location, cl.locations_id cartridge_location_parent, p.locations_id printer_location, pl.locations_id printer_location_parent
            from glpi_plugin_iservice_cartridges c
            left join glpi_locations cl on cl.id = c.locations_id_field
            join glpi_cartridgeitems_printermodels cp on cp.cartridgeitems_id = c.cartridgeitems_id
            join glpi_printers p on p.printermodels_id = cp.printermodels_id and p.is_deleted = 0
            join glpi_infocoms ic on ic.items_id = p.id and ic.itemtype = 'Printer' and ic.suppliers_id = c.suppliers_id_field
            left join glpi_locations pl on pl.id = p.locations_id
            where coalesce(pl.locations_id, 0) = coalesce(cl.locations_id, 0)
          ) t1 on t1.cid = c.id
          where c.date_use is null and c.date_out is null
          group by c.id
        ) t2
        join glpi_plugin_iservice_consumables_tickets ct on ct.amount > 0 and ct.new_cartridge_ids LIKE CONCAT('%|', t2.cid, '|%')
        join glpi_items_tickets it on it.itemtype = 'Printer' and it.tickets_id = ct.tickets_id 
        left join glpi_suppliers s on s.id = sid
        where t2.printer_count = 0
        group by sid;
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges that can not be installed',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} partners who have cartridges that can not be installed on any printer',
            'iteration_text' => "<a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Printers&printers0[supplier_name]=[name]' target='_blank' title=\"click to see partner's printers\">[name]</a> has <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Cartridges&cartridges0[date_use_null]=1&cartridges0[date_out_null]=1&filtering=1&cartridges0[partner_name]=[name]' target='_blank' title=\"click to see partner's cartridges\">[cartridge_count]</a> cartridges that can not be installed: [cartridge_ids]",
        ],
    ],
];

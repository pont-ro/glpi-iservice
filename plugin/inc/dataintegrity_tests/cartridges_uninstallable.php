<?php
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
          select
              sid
            , s.name
            , cartridge_count
            , cartridge_ids
            , case
                when other_location_printer_count > 0 then 'Mutați locația cartușelor!'
                else concat('Clientul nu mai are aparate compatibile cu aceste cartuse, <a id=''fix-partner-', sid, ''' href=''javascript:void(0);'' onclick=''ajaxCall(\\\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageCartridge.php?operation=delete_cartridge&ids=', cids, '\\\", \\\"\\\", function(message) {if (message !== \\\"" . IserviceToolBox::RESPONSE_OK . "\\\") {alert(message);} else {\$(\\\"#fix-partner-',sid,'\\\").remove();}});''>»»» ștergeți cartușele «««</a>')
              end fix
          from (
            select
                sid
              , other_location_printer_count
              , count(cid) cartridge_count
              , group_concat(cid separator ',') cids
              , group_concat('<a href=\"$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Cartridges&cartridges0[date_use_null]=1&cartridges0[date_out_null]=1&filtering=1&cartridges0[id]=', cid, '\"  target=\"_blank\">', cid, '</a>' separator ', ') cartridge_ids
            from (
              select 
                  c.id cid
                , cfc.suppliers_id_field sid
                , sum(case when coalesce(pl.locations_id, 0) = coalesce(cl.locations_id, 0) or coalesce(pl.locations_id, 0) = coalesce(cfc.locations_id_field, 0) then 1 else 0 end) same_location_printer_count
                , sum(case when coalesce(pl.locations_id, 0) != coalesce(cl.locations_id, 0) and coalesce(pl.locations_id, 0) != coalesce(cfc.locations_id_field, 0) then 1 else 0 end) > 0 other_location_printer_count
              from glpi_cartridges c
              left join glpi_plugin_fields_cartridgecartridgecustomfields cfc on cfc.items_id = c.id and cfc.itemtype = 'Cartridge'
              left join glpi_locations cl on cl.id = cfc.locations_id_field
              left join (
                  glpi_cartridgeitems_printermodels cp 
                  join glpi_printers p on p.printermodels_id = cp.printermodels_id and p.is_deleted = 0
                  join glpi_infocoms ic on ic.items_id = p.id and ic.itemtype = 'Printer'
                  left join glpi_locations pl on pl.id = p.locations_id
              ) on cp.cartridgeitems_id = c.cartridgeitems_id
                and (
                    ic.suppliers_id = cfc.suppliers_id_field
                    or ic.suppliers_id in (
                        select sgp.items_id
                        from glpi_plugin_fields_suppliersuppliercustomfields sgp
                        join glpi_plugin_fields_suppliersuppliercustomfields sgc
                            on sgc.group_field = sgp.group_field
                            and sgc.items_id = cfc.suppliers_id_field
                        where sgp.group_field is not null and sgp.group_field != '' and sgp.group_field != 'NULL'
                    )
                )
              where c.date_use is null and c.date_out is null
              group by c.id
            ) t1
            where t1.same_location_printer_count = 0
            group by sid, other_location_printer_count
          ) t2
          left join glpi_suppliers s on s.id = sid
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges that can not be installed',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} partners who have cartridges that can not be installed on any printer',
            'iteration_text' => "<a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Printers&printers0[supplier_name]=[name]' target='_blank' title=\"click to see partner's printers\">[name]</a> has <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Cartridges&cartridges0[date_use_null]=1&cartridges0[date_out_null]=1&filtering=1&cartridges0[partner_name]=[name]' target='_blank' title=\"click to see partner's cartridges\">[cartridge_count]</a> cartridges that can not be installed: [cartridge_ids] [fix]",
        ],
    ],
    'schedule' => [
        'display_last_result' => true,
        'h:m'                 => ['6:00'],
        'ignore_text'         => [
            'hours' => "Checked only at 06:00.",
        ]
    ]
];
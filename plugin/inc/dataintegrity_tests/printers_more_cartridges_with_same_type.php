<?php
return [
    'query' => "
        select * from (
        select
            p.id pid
          , p.serial
          , s.id sid
          , s.name supplier
          , ci.mercury_code_field mercury_code
          , ci.plugin_fields_cartridgeitemtypedropdowns_id  type_id
          , coalesce(td.completename, 'unknown') type_name
          , count(c.id) `count`
          , GROUP_CONCAT(c.id SEPARATOR ', ') ids
        from glpi_plugin_iservice_cartridges c
        join glpi_plugin_iservice_cartridge_items ci on ci.id = c.cartridgeitems_id
        join glpi_printers p on p.id = c.printers_id
        join glpi_suppliers s on s.id = c.suppliers_id_field
        left join glpi_plugin_fields_cartridgeitemtypedropdowns td on td.id = ci.plugin_fields_cartridgeitemtypedropdowns_id 
        where printers_id > 0 and date_use is not null and date_out is null and (ci.ref like 'cton%' or ci.ref like 'ccat%')
        group by p.id, s.id, ci.plugin_fields_cartridgeitemtypedropdowns_id 
        ) st
        where st.`count` > 1
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no printers with more then one cartridge with the same type',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} printers with more then one cartridge with the same type',
            'iteration_text' => "Printer with serial <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/view.php?view=cartridges&cartridges0%5Bprinter_name%5D=[serial]' target='_blank'>[serial]</a> at partner [supplier] has the following [type_name] cartridges: [ids]",
        ],
    ],
];

<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select 
            t.id tid
          , count(distinct cst.id) consumables_count
          , concat(' (', group_concat(cst.plugin_iservice_consumables_id separator ', '), ')') consumbale_ids
          , count(distinct crt.id) cartridges_count
          , concat(' (', group_concat(crt.cartridges_id separator ', '), ')') cartridge_ids
        from glpi_tickets t
        left join glpi_plugin_iservice_consumables_tickets cst on cst.tickets_id = t.id
        left join glpi_plugin_iservice_cartridges_tickets crt on crt.tickets_id = t.id
        where t.is_deleted = 1 
          and t.data_luc > '2018-07-01 00:00:00'
          and (cst.id is not null or crt.id is not null)
        group by t.id
        order by t.id desc
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no deleted tickets which deliver consumables or install cartridges',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} deleted tickets which deliver consumables or install cartridges',
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]' target='_blank'>[tid]</a> is deleted but delivers <b>[consumables_count]</b> consumables[consumbale_ids] and installs <b>[cartridges_count]</b> cartridges[cartridge_ids]"
        ],
    ],
];

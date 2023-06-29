<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select t.id, count(ct.id) cartridge_count, cft.cartridge_install
        from glpi_tickets t
        left join glpi_plugin_fields_ticketcustomfields cft on cft.items_id = t.id and cft.itemtype = 'Ticket'
        left join glpi_plugin_iservice_cartridges_tickets ct on ct.tickets_id = t.id
        where t.is_deleted = 0
          and t.`status` = 6
          and ct.id is not null
          and cft.cartridge_install is null
        group by t.id
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no tickets which install cartridges but do not have installation date',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} tickets which install cartridges but do not have installation date',
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[id]&mode=9999' target='_blank'>[id]</a> installs [cartridge_count] cartridges but does not have installation date",
        ],
    ],
];

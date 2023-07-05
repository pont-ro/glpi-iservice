<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select t.id, count(ct.id) consumable_count
        from glpi_tickets t
        join glpi_plugin_fields_ticketcustomfields cft on cft.items_id = t.id and cft.itemtype = 'Ticket'
        join glpi_plugin_iservice_consumables_tickets ct on ct.tickets_id = t.id
        where t.is_deleted = 0 and (cft.export_type is null or cft.export_type = '')
        group by t.id
        ",
    'test' => [
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no tickets with consumables without export type',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} tickets with consumables without export type',
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[id]&mode=9999' target='_blank'>[id]</a> has [consumable_count] consumables but no export type",
        ],
    ],
];

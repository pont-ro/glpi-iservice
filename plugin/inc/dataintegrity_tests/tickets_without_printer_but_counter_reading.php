<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select t.id tid, t.total2_black_field, t.total2_color_field, REPLACE(SUBSTR(l.new_value, POSITION('(' IN l.new_value) + 1), ')', '') pid, p.name
        from glpi_plugin_iservice_tickets t
        left join glpi_items_tickets it on it.tickets_id = t.id and it.itemtype = 'Printer'
        left join glpi_plugin_iservice_consumables_tickets ct on ct.tickets_id = t.id
        left join glpi_plugin_iservice_cartridges_tickets crt on crt.tickets_id = t.id
        left join 
          (select max(id) max_lid, items_id tid
           from glpi_logs
           where itemtype = 'Ticket' and itemtype_link = 'Printer' and coalesce(new_value, '') != ''
           group by items_id) ml on ml.tid = t.id
        left join glpi_logs l on l.id = ml.max_lid
        left join glpi_printers p on p.id = REPLACE(SUBSTR(l.new_value, POSITION('(' IN l.new_value) + 1), ')', '')
        where t.is_deleted = 0 and coalesce(t.total2_black_field, 0) + coalesce(t.total2_color_field, 0) > 0
          and it.id is null
          and (p.id is not null or l.new_value is null)
          and t.effective_date_field > '2017-03-01 00:00:00'
        group by t.id
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no tickets without printers but counter reading > 0',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} tickets without printers but counter reading > 0',
            'iteration_text' => "Ticket <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]&mode=9999' target='_blank'>[tid]</a> has black counter [total2_black] and color counter [total2_color], but printer <b>[name]</b> is missing from it",
        ],
    ],
];

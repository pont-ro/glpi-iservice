<?php
global $CFG_PLUGIN_ISERVICE;
$negative_counter_differences_since = '2019-09-01';
return [
    'query' => "
        select *
        from (
            select
                  it.items_id         pid
                , t.id                tid
                , t.effective_date_field
                , t.total2_black_field
                , t.total2_black_field - coalesce(
                    (
                        select t2.total2_black_field
                        from glpi_plugin_iservice_tickets t2
                        join glpi_items_tickets it2 on it2.tickets_id = t2.id and it2.itemtype = 'Printer'
                        where t2.is_deleted = 0
                          and t2.status = " . Ticket::CLOSED . " 
                          and it2.items_id = it.items_id
                          and (t2.effective_date_field > t.effective_date_field or (t2.effective_date_field = t.effective_date_field and t2.id > t.id))
                        order by t2.effective_date_field, t2.id
                        limit 1
                    ), t.total2_black_field) black_difference
                , t.total2_color_field
                , t.total2_color_field - coalesce(
                    (
                        select t2.total2_color_field
                        from glpi_plugin_iservice_tickets t2
                        join glpi_items_tickets it2 on it2.tickets_id = t2.id and it2.itemtype = 'Printer'
                        where t2.is_deleted = 0
                          and t2.status = " . Ticket::CLOSED . " 
                          and it2.items_id = it.items_id
                          and (t2.effective_date_field > t.effective_date_field or (t2.effective_date_field = t.effective_date_field and t2.id > t.id))
                        order by t2.effective_date_field, t2.id
                        limit 1
                    ), t.total2_color_field) color_difference
                , coalesce(
                    (
                        select t2.id
                        from glpi_plugin_iservice_tickets t2
                        join glpi_items_tickets it2 on it2.tickets_id = t2.id and it2.itemtype = 'Printer'
                        where t2.is_deleted = 0
                          and t2.status = " . Ticket::CLOSED . " 
                          and it2.items_id = it.items_id
                          and (t2.effective_date_field > t.effective_date_field or (t2.effective_date_field = t.effective_date_field and t2.id > t.id))
                        order by t2.effective_date_field, t2.id
                        limit 1
                    ), 0) next_tid
                , (select t2.effective_date_field from glpi_plugin_iservice_tickets t2 where t2.id = next_tid) next_data_luc
            from glpi_plugin_iservice_tickets t
            join glpi_items_tickets it on it.tickets_id = t.id and it.itemtype = 'Printer'
            where t.is_deleted = 0
              and t.status = " . Ticket::CLOSED . " 
              and it.items_id > 0
              and t.effective_date_field > '$negative_counter_differences_since'
            order by it.items_id, t.effective_date_field, t.id
            ) q
        where q.black_difference > 0 or q.color_difference > 0
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => "There are there are no negative counter differences for any printer beginning from $negative_counter_differences_since",
        ],
        'positive_result' => [
            'summary_text' => "There are {count} negative counter differences beginning from $negative_counter_differences_since",
            'iteration_text' => "Printer <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=GlpiPlugin\Iservice\Specialviews\Operations&operations0%5Bprinter_id%5D=[pid]' target='_blank'>[pid]</a> has negative counter difference (bk: -[black_difference], color: -[color_difference]) for tickets <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]&mode=9999' target='_blank'>[tid]</a> from <i>[effective_date_field]</i> and <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[next_tid]&mode=9999' target='_blank'>[next_tid]</a> from <i>[next_data_luc]</i>",
        ],
    ],
    'schedule' => [
        'display_last_result' => true,
        'hours'               => ['2-4:01', '14:01'],
        'ignore_text'         => [
            'hours' => "Checked only from 02 to 04 and 14",
        ]
    ]
];

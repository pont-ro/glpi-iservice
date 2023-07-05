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
                , t.data_luc
                , t.total2_black
                , t.total2_black - coalesce(
                    (
                        select t2.total2_black
                        from glpi_tickets t2
                        join glpi_items_tickets it2 on it2.tickets_id = t2.id and it2.itemtype = 'Printer'
                        where t2.is_deleted = 0
                          and t2.status = " . Ticket::CLOSED . " 
                          and it2.items_id = it.items_id
                          and (t2.data_luc > t.data_luc or (t2.data_luc = t.data_luc and t2.id > t.id))
                        order by t2.data_luc, t2.id
                        limit 1
                    ), t.total2_black) black_difference
                , t.total2_color
                , t.total2_color - coalesce(
                    (
                        select t2.total2_color
                        from glpi_tickets t2
                        join glpi_items_tickets it2 on it2.tickets_id = t2.id and it2.itemtype = 'Printer'
                        where t2.is_deleted = 0
                          and t2.status = " . Ticket::CLOSED . " 
                          and it2.items_id = it.items_id
                          and (t2.data_luc > t.data_luc or (t2.data_luc = t.data_luc and t2.id > t.id))
                        order by t2.data_luc, t2.id
                        limit 1
                    ), t.total2_color) color_difference
                , coalesce(
                    (
                        select t2.id
                        from glpi_tickets t2
                        join glpi_items_tickets it2 on it2.tickets_id = t2.id and it2.itemtype = 'Printer'
                        where t2.is_deleted = 0
                          and t2.status = " . Ticket::CLOSED . " 
                          and it2.items_id = it.items_id
                          and (t2.data_luc > t.data_luc or (t2.data_luc = t.data_luc and t2.id > t.id))
                        order by t2.data_luc, t2.id
                        limit 1
                    ), 0) next_tid
                , (select t2.data_luc from glpi_tickets t2 where t2.id = next_tid) next_data_luc
            from glpi_tickets t
            join glpi_items_tickets it on it.tickets_id = t.id and it.itemtype = 'Printer'
            where t.is_deleted = 0
              and t.status = " . Ticket::CLOSED . " 
              and it.items_id > 0
              and t.data_luc > '$negative_counter_differences_since'
            order by it.items_id, t.data_luc, t.id
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
            'iteration_text' => "Printer <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/view.php?view=operations&operations0%5Bprinter_id%5D=[pid]' target='_blank'>[pid]</a> has negative counter difference (bk: -[black_difference], color: -[color_difference]) for tickets <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[tid]&mode=9999' target='_blank'>[tid]</a> from <i>[data_luc]</i> and <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=[next_tid]&mode=9999' target='_blank'>[next_tid]</a> from <i>[next_data_luc]</i>",
        ],
    ],
    'schedule' => [
        'display_last_result' => true,
        'hours'               => ['2-4:00', '14:00'],
        'ignore_text'         => [
            'hours' => "Checked only from 02 to 04 and 14",
        ]
    ]
];

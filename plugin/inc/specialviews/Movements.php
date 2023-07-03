<?php

class PluginIserviceView_Movements extends PluginIserviceView
{

    protected function getSettings()
    {
        return [
            'name' => __('Movement list', 'iservice'),
            'query' => "
						SELECT
							  m.id movement_id
							, m.init_date movement_start_date
							, p.name printer_name
							, p.serial printer_serial
							, os.name old_partner
							, ns.name new_partner
							, CASE WHEN m.moved = 1 THEN 'Da' ELSE 'Nu' END movement_finalized
							, IFNULL(t2.total2_black, t1.total2_black) black_counter
							, IFNULL(t2.total2_color, t1.total2_color) color_counter
							, t1.id ticket1_id
							, t2.id ticket2_id
						FROM glpi_plugin_iservice_movements m
						LEFT JOIN glpi_printers p ON p.id = m.items_id AND m.itemtype='Printer'
						LEFT JOIN glpi_suppliers os ON os.id = m.suppliers_id_old
						LEFT JOIN glpi_suppliers ns ON ns.id = m.suppliers_id
						LEFT JOIN glpi_plugin_fields_ticketcustomfields tc1 ON tc1.movement_id = m.id and tc1.itemtype = 'Ticket'
						LEFT JOIN glpi_tickets t1 ON t1.id = tc1.items_id
						LEFT JOIN glpi_plugin_fields_ticketcustomfields tc2 ON tc2.movement2_id = m.id and tc2.itemtype = 'Ticket'
						LEFT JOIN glpi_tickets t2 ON t2.id = tc2.items_id
						WHERE m.moved in ([finalized])
						  AND ((p.name IS NULL AND '[printer_name]' = '%%') OR p.name LIKE '[printer_name]')
						  AND ((p.serial IS NULL AND '[printer_serial]' = '%%') OR p.serial LIKE '[printer_serial]')
						  AND ((os.name IS NULL AND '[old_partner]' = '%%') OR os.name LIKE '[old_partner]')
						  AND ((ns.name IS NULL AND '[new_partner]' = '%%') OR ns.name LIKE '[new_partner]')
						",
            'default_limit' => 30,
            'filters' => [
                'printer_name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'printer_name',
                ],
                'printer_serial' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'printer_serial',
                ],
                'old_partner' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'old_partner',
                ],
                'new_partner' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'new_partner',
                ],
                'finalized' => [
                    'type' => self::FILTERTYPE_SELECT,
                    'options' => [
                        '0' => 'Nu',
                        '1' => 'Da',
                        '0,1' => 'Toate'
                    ],
                    'default' => '0,1',
                    'empty_value' => '0',
                    'header' => 'movement_finalized',
                ],
            ],
            'columns' => [
                'movement_start_date' => [
                    'title' => 'Data începerii mutării',
                    'link' => [
                        'type' => 'normal',
                        'href' => 'movement.form.php?id=[movement_id]',
                        'title' => 'Deschide mutare',
                        'target' => '_blank',
                    ],
                    'default_sort' => 'DESC',
                ],
                'ticket1_id' => [
                    'title' => 'Tichet preluare',
                    'link' => [
                        'type' => 'normal',
                        'href' => 'ticket.form.php?id=[ticket1_id]&mode=' . PluginIserviceTicket::MODE_CLOSE,
                        'title' => 'Vezi tichet preluare',
                        'target' => '_blank',
                    ],
                    'default_sort' => 'DESC',
                ],
                'ticket2_id' => [
                    'title' => 'Tichet livrare',
                    'link' => [
                        'type' => 'normal',
                        'href' => 'ticket.form.php?id=[ticket2_id]&mode=' . PluginIserviceTicket::MODE_CLOSE,
                        'title' => 'Vezi tichet livrare',
                        'target' => '_blank',
                    ],
                    'default_sort' => 'DESC',
                ],
                'printer_name' => [
                    'title' => 'Nume aparat',
                ],
                'printer_serial' => [
                    'title' => 'Număr serie aparat',
                ],
                'old_partner' => [
                    'title' => 'Partener vechi',
                ],
                'new_partner' => [
                    'title' => 'Partener nou',
                ],
                'black_counter' => [
                    'title' => 'Counter alb&#8209;negru',
                    'align' => 'center',
                ],
                'color_counter' => [
                    'title' => 'Counter color',
                    'align' => 'center',
                ],
                'movement_finalized' => [
                    'title' => 'Mutat',
                    'align' => 'center',
                ],
            ],
        ];
    }

}

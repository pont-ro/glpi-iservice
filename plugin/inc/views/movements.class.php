<?php

// Imported from iService2, needs refactoring. Original file: "Movements.php".
namespace GlpiPlugin\Iservice\Views;

use GlpiPlugin\Iservice\Views\View;
use \PluginIserviceTicket;

class Movements extends View
{

    public static $rightname = 'plugin_iservice_view_movements';

    public static $icon = 'ti ti-arrows-move';

    public static function getName(): string
    {
        return _n('Movement', 'Movements', \Session::getPluralNumber(), 'iservice');
    }

    public static function getAdditionalMenuOptions()
    {
        return [
            'sortOrder' => 50,
        ];
    }

    protected function getSettings(): array
    {
        return [
            'name' => __('Movement list', 'iservice'),
            'query' => "
						SELECT
							  m.id movement_id_field
							, m.init_date movement_start_date
						    , p.id printer_id
							, p.name printer_name
							, p.serial printer_serial
							, os.name old_partner
							, ns.name new_partner
							, CASE WHEN m.moved = 1 THEN 'Da' ELSE 'Nu' END movement_finalized
							, IFNULL(cft2.total2_black_field, cft1.total2_black_field) black_counter
							, IFNULL(cft2.total2_color_field, cft1.total2_color_field) color_counter
							, t1.id ticket1_id
							, t2.id ticket2_id
						FROM glpi_plugin_iservice_movements m
						LEFT JOIN glpi_printers p ON p.id = m.items_id AND m.itemtype='Printer'
						LEFT JOIN glpi_suppliers os ON os.id = m.suppliers_id_old
						LEFT JOIN glpi_suppliers ns ON ns.id = m.suppliers_id
						LEFT JOIN glpi_plugin_fields_ticketticketcustomfields tc1 ON tc1.movement_id_field = m.id and tc1.itemtype = 'Ticket'
						LEFT JOIN glpi_tickets t1 ON t1.id = tc1.items_id
                        LEFT JOIN glpi_plugin_fields_ticketticketcustomfields cft1 ON cft1.items_id = t1.id and cft1.itemtype = 'Ticket'
						LEFT JOIN glpi_plugin_fields_ticketticketcustomfields tc2 ON tc2.movement2_id_field = m.id and tc2.itemtype = 'Ticket'
						LEFT JOIN glpi_tickets t2 ON t2.id = tc2.items_id
						LEFT JOIN glpi_plugin_fields_ticketticketcustomfields cft2 ON cft2.items_id = t2.id and cft2.itemtype = 'Ticket'
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
                        'href' => 'movement.form.php?id=[movement_id_field]',
                        'title' => 'Deschide mutare',
                        'target' => '_blank',
                    ],
                    'default_sort' => 'DESC',
                ],
                'ticket1_id' => [
                    'title' => 'Tichet preluare',
                    'link' => [
                        'type' => 'normal',
                        'href' => 'ticket.form.php?id=[ticket1_id]',
                        'title' => 'Vezi tichet preluare',
                        'target' => '_blank',
                    ],
                    'default_sort' => 'DESC',
                ],
                'ticket2_id' => [
                    'title' => 'Tichet livrare',
                    'link' => [
                        'type' => 'normal',
                        'href' => 'ticket.form.php?id=[ticket2_id]',
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
                    'link' => [
                        'type' => 'normal',
                        'href' => 'printer.form.php?id=[printer_id]',
                        'title' => __('Manage printer', 'iservice'), //'Administrare aparat',
                        'target' => '_blank',
                    ],
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

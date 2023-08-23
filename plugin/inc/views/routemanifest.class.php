<?php

namespace GlpiPlugin\Iservice\Views;

// Imported from iService2, needs refactoring. Original file: "Foaie_de_Parcurs.class.php".
class RouteManifest extends View
{

    public static $rightname = 'plugin_iservice_view_route_manifest';

    public static $icon = 'ti ti-road';

    public static function getName(): string
    {
        return __('Route Manifest', 'iservice');
    }

    protected function getSettings(): array
    {
        return [
            'name' => self::getName(),
            'query' => "
                        SELECT
                              t.id AS Nr_ticket
                            , t.name AS Title
                            , t.effective_date_field AS Data_efectiva
                            , CONCAT(u.firstname,' ',u.realname) AS Atribuit
                            , e.name AS Partener
                            , e.address AS Adresa_Partener
                            , p.name AS Aparat
                            , ifnull(l.completename,'') AS Locatie_Aparat
                            , '________' AS Km_Oras
                            , '________' AS KM_P 
                        FROM glpi_plugin_iservice_tickets t
                        LEFT JOIN glpi_tickets_users tu ON tu.tickets_id = t.id AND tu.type = 2
                        LEFT JOIN glpi_suppliers_tickets ts ON ts.tickets_id = t.id
                        LEFT JOIN glpi_users u ON u.id = tu.users_id
                        LEFT JOIN glpi_suppliers e ON e.id = ts.suppliers_id
                        LEFT JOIN glpi_items_tickets it ON it.tickets_id = t.id and it.itemtype = 'Printer'
                        LEFT JOIN glpi_printers p ON p.ID = it.items_id
                        LEFT JOIN glpi_locations l ON l.id = t.locations_id
                        WHERE t.is_deleted = 0 AND t.status in (" . implode(',', [\Ticket::SOLVED, \Ticket::CLOSED]) . ")
                          AND t.effective_date_field >= '[start_date]'
                          AND t.effective_date_field <= '[end_date]'
                            AND CAST(t.id AS CHAR) LIKE '[tichet]'
                            AND e.name LIKE '[partener]'
                            AND ((p.name is null AND '[aparat]' = '%%') OR p.name LIKE '[aparat]')
                            AND ((l.name is null AND '[locatie]' = '%%') OR l.name LIKE '[locatie]')
                            AND (t.no_travel_field is null or not t.no_travel_field = 1)
                            [atribuit]
								",
            'default_limit' => 50,
            'filters' => [
                'start_date' => [
                    'type' => self::FILTERTYPE_DATE,
                    'caption' => 'Data efectivă',
                    'format' => 'Y-m-d 00:00:00',
                    'empty_value' => date("Y-m-01"),
                    'pre_widget' => "{$this->getWidgets()[self::WIDGET_LAST_MONTH]} {$this->getWidgets()[self::WIDGET_THIS_MONTH]} ",
                ],
                'end_date' => [
                    'type' => 'date',
                    'caption' => ' - ',
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => date("Y-m-t"),
                ],
                'atribuit' => [
                    'type' => 'user',
                    'caption' => 'Tehnician',
                    'format' => 'AND u.ID = %d',
                    'header' => 'Atribuit',
                ],
                'tichet' => [
                    'type' => 'text',
                    'caption' => 'Tichet',
                    'format' => '%%%s%%',
                    'style' => 'width: 4em;',
                    'header' => 'Nr_ticket',
                ],
                'partener' => [
                    'type' => 'text',
                    'caption' => 'Partener',
                    'format' => '%%%s%%',
                    'header' => 'Partener',
                ],
                'aparat' => [
                    'type' => 'text',
                    'caption' => 'Aparat',
                    'format' => '%%%s%%',
                    'header' => 'Aparat',
                ],
                'locatie' => [
                    'type' => 'text',
                    'caption' => 'Locatie',
                    'format' => '%%%s%%',
                    'header' => 'Locatie_Aparat',
                ],
            ],
            'columns' => [
                'Nr_ticket' => [
                    'title' => 'Nr ticket',
                ],
                'Data_efectiva' => [
                    'title' => 'Data efectiva',
                    'default_sort' => 'DESC',
                    'style' => 'white-space: nowrap;'
                ],
                'Atribuit' => [
                    'title' => 'Atribuit',
                ],
                'Title' => [
                    'title' => 'Titlu',
                ],
                'Partener' => [
                    'title' => 'Partener',
                ],
                'Adresa_Partener' => [
                    'title' => 'Adresa Partener',
                ],
                'Aparat' => [
                    'title' => 'Aparat',
                ],
                'Locatie_Aparat' => [
                    'title' => 'Locatie aparat',
                ],
                'Km_Oras' => [
                    'title' => 'Km&nbsp;oras',
                ],
                'KM_P' => [
                    'title' => 'KM&nbsp;P',
                ],
            ],
        ];
    }

}

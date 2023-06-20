<?php

// Imported from iService2, needs refactoring.
class PluginIserviceView_Foaie_de_Parcurs extends PluginIserviceView {

    static $order = 60;

    static function getName() {
        return 'Foaie de parcurs';
    }

    protected function getSettings() {
        return array(
            'name' => self::getName(),
            'query' => "
                        SELECT
                              t.id AS Nr_ticket
                            , t.name AS Title
                            , data_luc AS Data_efectiva
                            , CONCAT(u.firstname,' ',u.realname) AS Atribuit
                            , e.name AS Partener
                            , e.address AS Adresa_Partener
                            , p.name AS Aparat
                            , ifnull(l.completename,'') AS Locatie_Aparat
                            , '________' AS Km_Oras
                            , '________' AS KM_P 
                        FROM glpi_tickets t
                        LEFT JOIN glpi_plugin_fields_ticketcustomfields tcf ON tcf.items_id = t.id and tcf.itemtype = 'Ticket'
                        LEFT JOIN glpi_tickets_users tu ON tu.tickets_id = t.id AND tu.type = 2
                        LEFT JOIN glpi_suppliers_tickets ts ON ts.tickets_id = t.id
                        LEFT JOIN glpi_users u ON u.id = tu.users_id
                        LEFT JOIN glpi_suppliers e ON e.id = ts.suppliers_id
                        LEFT JOIN glpi_items_tickets it ON it.tickets_id = t.id and it.itemtype = 'Printer'
                        LEFT JOIN glpi_printers p ON p.ID = it.items_id
                        LEFT JOIN glpi_locations l ON l.id = t.locations_id
                        WHERE t.is_deleted = 0 AND t.status in (" . implode(',', array(Ticket::SOLVED, Ticket::CLOSED)) . ")
                          AND data_luc >= '[start_date]'
                          AND data_luc <= '[end_date]'
                            AND CAST(t.id AS CHAR) LIKE '[tichet]'
                            AND e.name LIKE '[partener]'
                            AND ((p.name is null AND '[aparat]' = '%%') OR p.name LIKE '[aparat]')
                            AND ((l.name is null AND '[locatie]' = '%%') OR l.name LIKE '[locatie]')
                            AND (tcf.fara_deplasare is null or not tcf.fara_deplasare = 1)
                            [atribuit]
								",
            'default_limit' => 50,
            'filters' => array(
                'start_date' => array(
                    'type' => self::FILTERTYPE_DATE,
                    'caption' => 'Data efectivă',
                    'format' => 'Y-m-d 00:00:00',
                    'empty_value' => date("Y-m-01"),
                    'pre_widget' => "{$this->getWidgets()[self::WIDGET_LAST_MONTH]} {$this->getWidgets()[self::WIDGET_THIS_MONTH]} ",
                ),
                'end_date' => array(
                    'type' => 'date',
                    'caption' => ' - ',
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => date("Y-m-t"),
                ),
                'atribuit' => array(
                    'type' => 'user',
                    'caption' => 'Tehnician',
                    'format' => 'AND u.ID = %d',
                    'header' => 'Atribuit',
                ),
                'tichet' => array(
                    'type' => 'text',
                    'caption' => 'Tichet',
                    'format' => '%%%s%%',
                    'style' => 'width: 4em;',
                    'header' => 'Nr_ticket',
                ),
                'partener' => array(
                    'type' => 'text',
                    'caption' => 'Partener',
                    'format' => '%%%s%%',
                    'header' => 'Partener',
                ),
                'aparat' => array(
                    'type' => 'text',
                    'caption' => 'Aparat',
                    'format' => '%%%s%%',
                    'header' => 'Aparat',
                ),
                'locatie' => array(
                    'type' => 'text',
                    'caption' => 'Locatie',
                    'format' => '%%%s%%',
                    'header' => 'Locatie_Aparat',
                ),
            ),
            'columns' => array(
                'Nr_ticket' => array(
                    'title' => 'Nr ticket',
                ),
                'Data_efectiva' => array(
                    'title' => 'Data efectiva',
                    'default_sort' => 'DESC',
                    'style' => 'white-space: nowrap;'
                ),
                'Atribuit' => array(
                    'title' => 'Atribuit',
                ),
                'Title' => array(
                    'title' => 'Titlu',
                ),
                'Partener' => array(
                    'title' => 'Partener',
                ),
                'Adresa_Partener' => array(
                    'title' => 'Adresa Partener',
                ),
                'Aparat' => array(
                    'title' => 'Aparat',
                ),
                'Locatie_Aparat' => array(
                    'title' => 'Locatie aparat',
                ),
                'Km_Oras' => array(
                    'title' => 'Km&nbsp;oras',
                ),
                'KM_P' => array(
                    'title' => 'KM&nbsp;P',
                ),
            ),
        );
    }

}

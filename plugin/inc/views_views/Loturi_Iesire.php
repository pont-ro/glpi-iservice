<?php

// Imported from iService2, needs refactoring.
class PluginIserviceView_Loturi_Iesire extends PluginIserviceView {
    
    static $order = 30;
    
    static function getName() {
        return 'Loturi de ieșire';
    }

    protected function getSettings() {
        return array(
            'name' => self::getName(),
            'query' => "
                SELECT
                        fr.nrfac AS nrfac
                    , fa.datafac AS Data_Fact
                    , fa.nrcmd AS tehnician
                    , fi.initiale AS Denumire_Client
                    , fr.codmat As Cod
                    , n.denum AS Denumire_Articol
                    , p.serial AS printer_serial
                    , fr.descr AS Descriere
                    , ROUND((fr.puliv/fr.puini),2) AS Proc
                    , fr.cant AS Cant
                    , IF(fr.tip='AIMFS' OR fr.tip='TAIM', 0, fr.puliv) AS Pret_Liv
                    , IF(fr.tip='AIMFS' OR fr.tip='TAIM', -(ROUND(fr.puini*fr.cant,2)), ROUND(fr.cant*(fr.puliv-fr.puini),2)) AS Adaos
                FROM {$this->table_prefix}hmarfa_facrind fr
                LEFT JOIN {$this->table_prefix}hmarfa_facturi fa ON fa.nrfac = fr.nrfac
                LEFT JOIN {$this->table_prefix}hmarfa_firme fi ON fi.cod = fa.codbenef
                LEFT JOIN {$this->table_prefix}hmarfa_nommarfa n ON fr.codmat = n.cod
                LEFT JOIN glpi_tickets t ON t.id = SUBSTR(fa.nrcmd FROM 1 FOR 5)
                LEFT JOIN glpi_items_tickets it ON it.tickets_id = t.id and it.itemtype = 'Printer'
                LEFT JOIN glpi_printers p ON p.id = it.items_id
                WHERE fr.tip IN ('TFAC','AIMFS','TFACR','TAIM')
                    AND fa.datafac >= '[start_date]'
                    AND fa.datafac <= '[end_date]'
                    AND fa.nrfac LIKE '[nrfac]'
                    AND fi.initiale LIKE '[nume_client]'
                    AND fr.codmat LIKE '[codmat]'
                    AND n.denum LIKE '[denum_art]'
                    AND fa.nrcmd LIKE '[tehnician]'
                    AND fr.descr LIKE '[descr]'
                    AND ((p.serial IS NULL AND '[printer_serial]' = '%%') or p.serial LIKE '[printer_serial]')
                ",
            'default_limit' => 25,
            'filters' => array(
                'start_date' => array(
                    'type' => 'date',
                    'caption' => 'Data factură',
                    'format' => 'Y-m-d',
                    'empty_value' => '2000-01-01',
                    'pre_widget' => "{$this->getWidgets()[self::WIDGET_LAST_6_MONTH]} {$this->getWidgets()[self::WIDGET_LAST_MONTH]} {$this->getWidgets()[self::WIDGET_THIS_MONTH]} ",
                ),
                'end_date' => array(
                    'type' => 'date',
                    'caption' => ' - ',
                    'format' => 'Y-m-d',
                    'empty_value' => date('Y-m-d'),
                ),
                'nrfac' => array(
                    'type' => 'text',
                    'caption' => 'Nrfac',
                    'format' => '%%%s%%',
                    'header' => 'nrfac',
                ),
                'tehnician' => array(
                    'type' => 'text',
                    'caption' => 'Tehnician',
                    'format' => '%%%s%%',
                    'header' => 'tehnician',
                ),
                'nume_client' => array(
                    'type' => 'text',
                    'caption' => 'Client',
                    'format' => '%%%s%%',
                    'header' => 'Denumire_Client',
                ),
                'codmat' => array(
                    'type' => 'text',
                    'caption' => 'Cod mat',
                    'format' => '%%%s%%',
                    'header' => 'Cod',
                ),
                'denum_art' => array(
                    'type' => 'text',
                    'caption' => 'Articol',
                    'format' => '%%%s%%',
                    'header' => 'Denumire_Articol',
                ),
                'descr' => array(
                    'type' => 'text',
                    'format' => '%%%s%%',
                    'header' => 'Descriere',
                ),
                'printer_serial' => array(
                    'type' => 'text',
                    'caption' => 'Serie aparat',
                    'format' => '%%%s%%',
                    'header' => 'printer_serial',
                ),
            ),
            'columns' => array(
                'nrfac' => array(
                    'title' => 'Nrfac'
                ),
                'Data_Fact' => array(
                    'title' => 'Data facturii',
                    'default_sort' => 'DESC',
                    'style' => 'white-space: nowrap',
                ),
                'tehnician' => array(
                    'title' => 'Tehnician',
                ),
                'Denumire_Client' => array(
                    'title' => 'Denumire client',
                ),
                'Cod' => array(
                    'title' => 'Cod material',
                ),
                'Denumire_Articol' => array(
                    'title' => 'Denumire articol',
                ),
                'printer_serial' => array(
                    'title' => 'Serie aparat',
                ),
                'Descriere' => array(
                    'title' => 'Descriere',
                ),
                'Proc' => array(
                    'title' => 'Proc',
                    'align' => 'right',
                    'format' => '%.2f',
                ),
                'Cant' => array(
                    'title' => 'Cant',
                    'align' => 'center',
                ),
                'Pret_Liv' => array(
                    'title' => 'Preț liv',
                    'align' => 'right',
                    'format' => '%.2f',
                ),
                'Adaos' => array(
                    'title' => 'Adaos',
                    'align' => 'right',
                    'format' => '%.2f',
                    'total' => true,
                ),
            ),
        );
    }

}

<?php

namespace GlpiPlugin\Iservice\Views;

// Imported from iService2, needs refactoring. Original file: "Loturi_Iesire.php".
class OutboundLots extends View
{

    public static $rightname = 'plugin_iservice_view_outbound_lots';

    public static $icon = 'ti ti-transfer-out';

    public static function getName(): string
    {
        return __('Outbound Lots', 'iservice');
    }

    protected function getSettings(): array
    {
        return [
            'name' => self::getName(),
            'query' => "
                SELECT
                        fr.nrfac AS nrfac
                    , fa.datafac AS Data_Fact
                    , fa.nrcmd AS tehnician
                    , fi.initiale AS Denumire_Client
                    , fr.codmat As Cod
                    , n.denum AS Denumire_Articol
                    , fr.descr AS Descriere
                    , ROUND((fr.puliv / NULLIF(fr.puini, 0)), 2) AS Proc
                    , fr.cant AS Cant
                    , IF(fr.tip='AIMFS' OR fr.tip='TAIM', 0, fr.puliv) AS Pret_Liv
                    , IF(fr.tip='AIMFS' OR fr.tip='TAIM', -(ROUND(fr.puini*fr.cant,2)), ROUND(fr.cant*(fr.puliv-fr.puini),2)) AS Adaos
                FROM {$this->table_prefix}hmarfa_facrind fr
                LEFT JOIN {$this->table_prefix}hmarfa_facturi fa ON fa.nrfac = fr.nrfac
                LEFT JOIN {$this->table_prefix}hmarfa_firme fi ON fi.cod = fa.codbenef
                LEFT JOIN {$this->table_prefix}hmarfa_nommarfa n ON fr.codmat = n.cod
                WHERE fr.tip IN ('TFAC','AIMFS','TFACR','TAIM')
                    AND fa.datafac >= '[start_date]'
                    AND fa.datafac <= '[end_date]'
                    AND fa.nrfac LIKE '[nrfac]'
                    AND fi.initiale LIKE '[nume_client]'
                    AND fr.codmat LIKE '[codmat]'
                    AND n.denum LIKE '[denum_art]'
                    AND fa.nrcmd LIKE '[tehnician]'
                    AND fr.descr LIKE '[descr]'
                ",
            'default_limit' => 25,
            'filters' => [
                'start_date' => [
                    'type' => 'date',
                    'caption' => 'Data factură',
                    'format' => 'Y-m-d',
                    'empty_value' => '2000-01-01',
                    'pre_widget' => "{$this->getWidgets()[self::WIDGET_LAST_6_MONTH]} {$this->getWidgets()[self::WIDGET_LAST_MONTH]} {$this->getWidgets()[self::WIDGET_THIS_MONTH]} ",
                ],
                'end_date' => [
                    'type' => 'date',
                    'caption' => ' - ',
                    'format' => 'Y-m-d',
                    'empty_value' => date('Y-m-d'),
                ],
                'nrfac' => [
                    'type' => 'text',
                    'caption' => 'Nrfac',
                    'format' => '%%%s%%',
                    'header' => 'nrfac',
                ],
                'tehnician' => [
                    'type' => 'text',
                    'caption' => 'Tehnician',
                    'format' => '%%%s%%',
                    'header' => 'tehnician',
                ],
                'nume_client' => [
                    'type' => 'text',
                    'caption' => 'Client',
                    'format' => '%%%s%%',
                    'header' => 'Denumire_Client',
                ],
                'codmat' => [
                    'type' => 'text',
                    'caption' => 'Cod mat',
                    'format' => '%%%s%%',
                    'header' => 'Cod',
                ],
                'denum_art' => [
                    'type' => 'text',
                    'caption' => 'Articol',
                    'format' => '%%%s%%',
                    'header' => 'Denumire_Articol',
                ],
                'descr' => [
                    'type' => 'text',
                    'format' => '%%%s%%',
                    'header' => 'Descriere',
                ],
            ],
            'columns' => [
                'nrfac' => [
                    'title' => 'Nrfac'
                ],
                'Data_Fact' => [
                    'title' => 'Data facturii',
                    'default_sort' => 'DESC',
                    'style' => 'white-space: nowrap',
                ],
                'tehnician' => [
                    'title' => 'Tehnician',
                ],
                'Denumire_Client' => [
                    'title' => 'Denumire client',
                ],
                'Cod' => [
                    'title' => 'Cod material',
                ],
                'Denumire_Articol' => [
                    'title' => 'Denumire articol',
                ],
                'Descriere' => [
                    'title' => 'Descriere',
                ],
                'Proc' => [
                    'title' => 'Proc',
                    'align' => 'right',
                    'format' => '%.2f',
                ],
                'Cant' => [
                    'title' => 'Cant',
                    'align' => 'center',
                ],
                'Pret_Liv' => [
                    'title' => 'Preț liv',
                    'align' => 'right',
                    'format' => '%.2f',
                ],
                'Adaos' => [
                    'title' => 'Adaos',
                    'align' => 'right',
                    'format' => '%.2f',
                    'total' => true,
                ],
            ],
        ];
    }

}

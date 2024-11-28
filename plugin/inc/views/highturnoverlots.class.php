<?php

namespace GlpiPlugin\Iservice\Views;

class HighTurnoverLots extends View
{

    public static $rightname = 'plugin_iservice_view_stock_lots';

    public static $icon = 'ti ti-building-warehouse';

    public static function getName(): string
    {
        return _t('High Turnover Lots');
    }

    protected function getSettings(): array
    {
        return [
            'name' => self::getName(),
            'query' => "
                SELECT 
                  ol.item_code AS item_code
                , ol.item_name AS item_name
                , ol.quantity_on_outbond_lots AS quantity_on_outbond_lots
                , ol.number_of_outbond_lots AS number_of_outbond_lots
                , SUM(l.stoci) AS inbound_items_number
                , SUM(l.iesiri) AS outbound_items_number
                , SUM(l.stoci) - SUM(l.iesiri) AS stock
                FROM (SELECT
                    fr.codmat AS item_code,
                    n.denum AS item_name,
                    SUM(fr.cant) AS quantity_on_outbond_lots,
                    COUNT(fr.codmat) AS number_of_outbond_lots
                    FROM
                        hmarfa_facrind fr
                    LEFT JOIN hmarfa_facturi fa ON fa.nrfac = fr.nrfac
                    LEFT JOIN hmarfa_firme fi ON fi.cod = fa.codbenef
                    LEFT JOIN hmarfa_nommarfa n ON fr.codmat = n.cod
                    WHERE
                        fr.tip IN ('TFAC', 'AIMFS', 'TFACR', 'TAIM')
                        AND fa.datafac >= '[start_date]'
                        AND fa.datafac <= '[end_date]'
                        AND fr.codmat NOT LIKE 'C%'
                        AND fr.codmat NOT LIKE 'S%'
                        AND fr.codmat LIKE '[item_code]'
                    GROUP BY fr.codmat
                    HAVING number_of_outbond_lots > [number_of_outbond_lots]) AS ol
                LEFT JOIN hmarfa_lotm l ON l.codmat = ol.item_code
                GROUP BY ol.item_code
                HAVING stock > [stock]
                AND outbound_items_number > [outbound_items_number]
                ORDER BY quantity_on_outbond_lots desc
                ",
            'default_limit' => 25,
            'filters' => [
                'start_date' => [
                    'type' => 'date',
                    'caption' => _t("Invoice Date"),
                    'class' => 'mx-1',
                    'format' => 'Y-m-d',
                    'empty_value' => date("Y-m-d", strtotime("first day of January this year", strtotime(date("Y-m-01")))),
                    'pre_widget' => "{$this->getWidgets()[self::WIDGET_LAST_6_MONTH]} {$this->getWidgets()[self::WIDGET_LAST_MONTH]} {$this->getWidgets()[self::WIDGET_THIS_MONTH]} ",
                ],
                'end_date' => [
                    'type' => 'date',
                    'caption' => ' - ',
                    'format' => 'Y-m-d',
                    'empty_value' => date('Y-m-d'),
                ],
                'number_of_outbond_lots' => [
                    'type' => 'int',
                    'caption' => _t("Number of lots"),
                    'format' => '%d',
                    'default' => 3,
                    'empty_value' => 0,
                    'style' => 'text-align:right;width:3em;',
                    'header' => 'number_of_outbond_lots',
                    'header_caption' => '> ',
                ],
                'outbound_items_number' => [
                    'type' => 'int',
                    'caption' => _t("Outbound Lots"),
                    'format' => '%d',
                    'default' => 0,
                    'empty_value' => 0,
                    'style' => 'text-align:right;width:3em;',
                    'header' => 'outbound_items_number',
                    'header_caption' => '> ',
                ],
                'stock' => [
                    'type' => 'int',
                    'caption' => _t("Stock Lots"),
                    'format' => '%d',
                    'default' => -1,
                    'empty_value' => -1,
                    'style' => 'text-align:right;width:3em;',
                    'header' => 'stock',
                    'header_caption' => '> ',
                ],
                'item_code' => [
                    'type' => 'text',
                    'caption' => _t("Item code"),
                    'format' => '%%%s%%',
                    'header' => 'item_code',
                ],
            ],
            'columns' => [
                'item_code' => [
                    'title' => _t("Item code"),
                ],
                'item_name' => [
                    'title' => _t("Item name"),
                ],
                'quantity_on_outbond_lots' => [
                    'title' => _t("Quantity on outbound lots"),
                ],
                'number_of_outbond_lots' => [
                    'title' => _t("Number of lots"),
                ],
                'inbound_items_number' => [
                    'title' => _t("Inbound quantity"),
                    'align' => 'center',
                ],
                'outbound_items_number' => [
                    'title' => _t("Outbound quantity"),
                    'align' => 'center',
                ],
                'stock' => [
                    'title' => _t("Stock"),
                    'align' => 'center',
                ],
            ],
        ];
    }

}

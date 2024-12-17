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

    public static function getExcludeCondition(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        $prefixes     = explode(',', $value);
        $sqlCondition = '';
        foreach ($prefixes as $prefix) {
            $sqlCondition .= " AND fr.codmat NOT LIKE '{$prefix}%'";
        }

        return $sqlCondition;
    }

    protected function getSettings(): array
    {
        global $CFG_PLUGIN_ISERVICE;
        return [
            'name' => self::getName(),
            'query' => "
                SELECT 
                 ol.item_code AS item_code
                , ol.item_name AS item_name
                , ol.quantity_on_outbond_lots AS quantity_on_outbond_lots
                , ol.number_of_outbond_lots AS number_of_outbond_lots
                , SUM(ls.stoci) AS inbound_items_number
                , SUM(ls.iesiri) AS outbound_items_number
                , SUM(ls.stoci) - sum(ls.iesiri) AS stock
                , ROUND(SUM(ls.total_lot_value)/IF(SUM(ls.stoci)=SUM(ls.iesiri), 1, SUM(ls.stoci) - SUM(ls.iesiri)),2) AS average_price
                , mn.model_names
                , ls.grupa AS lot_group
                , GROUP_CONCAT(DISTINCT ls.obs SEPARATOR ' | ') AS obs
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
                        AND n.denum LIKE '[item_name]'
                        [exclude]
                        AND fr.codmat LIKE '[item_code]'
                    GROUP BY fr.codmat
                    HAVING number_of_outbond_lots > [number_of_outbond_lots]) AS ol
                 LEFT JOIN (
                        SELECT 
                            l.codmat
                            , l.nrtran AS Nr_receptie
                            , t.dataint AS Data_Receptie
                            , l.stoci
                            , l.iesiri
                            , l.grupa
                            , IF(l.stoci - l.iesiri > 0, l.obs, 'N/A') AS obs
                            , (l.pcont * (l.stoci - l.iesiri)) as total_lot_value
                        FROM hmarfa_lotm l
                        INNER JOIN hmarfa_tran t USING (nrtran)
                        WHERE t.dataint >= '[start_date]' AND t.dataint <= '[end_date]'
                    ) ls ON ls.codmat = ol.item_code
                    LEFT JOIN
                        ( SELECT GROUP_CONCAT(CONCAT(pm.name) SEPARATOR '<br>') model_names, cm.plugin_iservice_consumables_id
                          FROM glpi_plugin_iservice_consumables_models cm
                          LEFT JOIN glpi_printermodels pm on pm.id = cm.printermodels_id
                          GROUP BY cm.plugin_iservice_consumables_id
                        ) mn ON mn.plugin_iservice_consumables_id = ol.item_code
                GROUP BY ol.item_code
                HAVING stock > [stock] AND average_price > [average_price] AND lot_group LIKE '[lot_group]' AND obs LIKE '[obs]'
                AND outbound_items_number > [outbound_items_number]
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
                'exclude' => [
                    'type' => 'text',
                    'default' => 'C,S',
                    'caption' => _t('Exclude item codes starting with: '),
                    'format' => 'function:\GlpiPlugin\Iservice\Views\HighTurnoverLots::getExcludeCondition',
                    'empty_format' => '',
                ],
                'item_name' => [
                    'type' => 'text',
                    'caption' => _t("Item name"),
                    'format' => '%%%s%%',
                    'header' => 'item_name',
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
                'average_price' => [
                    'type' => 'int',
                    'caption' => _t("Average price"),
                    'format' => '%d',
                    'default' => -1,
                    'empty_value' => -1,
                    'style' => 'text-align:right;width:3em;',
                    'header' => 'average_price',
                    'header_caption' => '> ',
                ],
                'item_code' => [
                    'type' => 'text',
                    'caption' => _t("Item code"),
                    'format' => '%%%s%%',
                    'header' => 'item_code',
                ],
                'lot_group' => [
                    'type' => 'text',
                    'caption' => _t("Lot group"),
                    'format' => '%%%s%%',
                    'header' => 'lot_group',
                ],
                'obs' => [
                    'type' => 'text',
                    'caption' => _t('Observations'),
                    'format' => '%%%s%%',
                    'header' => 'obs',
                ],
            ],
            'columns' => [
                'item_code' => [
                    'title' => _t("Item code"),
                    'link' => [
                        'href' => $CFG_PLUGIN_ISERVICE['root_doc'] . '/front/views.php?view=StockLots&stocklots0[cod]=[item_code]',
                        'target' => '_blank',
                    ],
                ],
                'item_name' => [
                    'title' => _t("Item name"),
                ],
                'quantity_on_outbond_lots' => [
                    'title' => _t("Quantity on outbound lots"),
                    'default_sort' => 'DESC',
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
                'average_price' => [
                    'title' => _t("Average price"),
                    'align' => 'center',
                ],
                'model_names' => [
                    'title' => _t('Compatible models'),
                    'align' => 'center',
                ],
                'lot_group' => [
                    'title' => _t('Lot group'),
                    'align' => 'center',
                ],
                'obs' => [
                    'title' => _t('Observations'),
                    'align' => 'center',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\HighTurnoverLots::getObservationsDisplay($row);',
                ],
            ],
        ];
    }

    public static function getObservationsDisplay(array $row): string
    {
        return str_replace(['| NULL', 'NULL |', 'NULL', 'N/A |', '| N/A', 'N/A'], '', $row['obs']);
    }

}

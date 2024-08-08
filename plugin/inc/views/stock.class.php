<?php

namespace GlpiPlugin\Iservice\Views;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use PluginIserviceConsumable_Model;
use PluginIserviceOrderStatus;

// Imported from iService2, needs refactoring. Original file: "Stoc.php".
class Stock extends View
{

    public static $rightname = 'plugin_iservice_view_stock';

    public static $icon = 'ti ti-building-warehouse';

    public static function getName(): string
    {
        return _t('Stock optimization');
    }

    public static function getModelNamesDisplay($row_data): string
    {
        $model_names     = explode('<br>', $row_data['model_names']);
        $consumable_data = [];
        foreach ($model_names as $model_name) {
            $data = explode(':', $model_name, 2);
            if (count($data) > 1) {
                $consumable_data[$data[0]] = $data[1];
            }
        }

        return PluginIserviceConsumable_Model::showForConsumable($row_data['Cod_Articol'], $consumable_data, true);
    }

    public static function getMinimumStockDisplay($row_data): string
    {
        if (empty($row_data['minimum_stock'])) {
            $row_data['minimum_stock'] = 0;
        }

        global $CFG_PLUGIN_ISERVICE;
        $sanitized_consumable_id = IserviceToolBox::getHtmlSanitizedValue($row_data['Cod_Articol']);

        $result  = "<a id='min-stock-link-$row_data[__row_id__]' class='pointer min-stock-link-$sanitized_consumable_id' onclick='$(\"#min-stock-span-$row_data[__row_id__]\").show();$(this).hide();'>{$row_data['minimum_stock']}</a>";
        $result .= "<span id='min-stock-span-$row_data[__row_id__]' style='display:none; white-space: nowrap;'>";
        $result .= "<input id='min-stock-edit-$row_data[__row_id__]' class='min-stock-edit-$sanitized_consumable_id' style='width:2em;' type='text' value='$row_data[minimum_stock]' />&nbsp;";
        $result .= "<i class='fa fa-check-circle' onclick='manageItemViaAjax(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageConsumable.php?operation=set_min_stock\", \"$row_data[Cod_Articol]\", \"$sanitized_consumable_id\", \"min-stock\", \"$row_data[__row_id__]\", \"\");' style='color:green'></i>&nbsp;";
        $result .= "<i class='fa fa-times' onclick='$(\"#min-stock-link-$row_data[__row_id__]\").show();$(\"#min-stock-span-$row_data[__row_id__]\").hide();'></i>";
        $result .= "</span>";

        return $result;
    }

    protected function getSettings(): array
    {
        return [
            'name' => self::getName(),
            'query' => "
                SELECT
                      l.codmat AS Cod_Articol
                    , n.denum AS Denumire
                    , l.gest AS Gest
                    , l.grupa AS Grupa
                    , SUM(l.stoci-l.iesiri) AS Stoc
                    , o.amount ordered_amount
                    , m.minimum_stock
                    , mn.model_names
                FROM {$this->table_prefix}hmarfa_lotm l
                LEFT JOIN {$this->table_prefix}hmarfa_nommarfa n ON n.cod = l.codmat
                LEFT JOIN glpi_plugin_iservice_minimum_stocks m on m.plugin_iservice_consumables_id = l.codmat
                LEFT JOIN
                    ( SELECT SUM(io.amount) amount, plugin_iservice_consumables_id
                      FROM glpi_plugin_iservice_intorders io
                      WHERE io.plugin_iservice_orderstatuses_id < " . PluginIserviceOrderStatus::getIdFromWeight(PluginIserviceOrderStatus::WEIGHT_RECEIVED) . "
                      GROUP BY io.plugin_iservice_consumables_id
                    ) o ON o.plugin_iservice_consumables_id = l.codmat
                LEFT JOIN
                    ( SELECT GROUP_CONCAT(CONCAT(pm.id, ':', pm.name) SEPARATOR '<br>') model_names, cm.plugin_iservice_consumables_id
                      FROM glpi_plugin_iservice_consumables_models cm
                      LEFT JOIN glpi_printermodels pm on pm.id = cm.printermodels_id
                      GROUP BY cm.plugin_iservice_consumables_id
                    ) mn ON mn.plugin_iservice_consumables_id = l.codmat
                WHERE l.stoci-l.iesiri > [stoc]
                  AND n.denum LIKE '[denum]'
                  AND l.codmat LIKE '[cod]'
                  AND l.grupa LIKE '[tip]'
                  AND ((mn.model_names is null AND '[model_names]' = '%%') OR mn.model_names LIKE '[model_names]')
                  AND (m.minimum_stock is null and 0 > [minimum_stock] OR m.minimum_stock > [minimum_stock])
                  [gest]
                GROUP BY l.codmat, l.gest, l.grupa
                ",
            'default_limit' => 25,
            'filters' => [
                'gest' => [
                    'type' => 'select',
                    'caption' => 'Gestiune',
                    'options' => [
                        '' => 'oricare',
                        'MR' => 'MR',
                        'MR2' => 'MR2',
                    ],
                    'format' => "AND l.gest = '%s'",
                    'header' => 'Gest',
                ],
                'denum' => [
                    'type' => 'text',
                    'caption' => 'Denumire articol',
                    'format' => '%%%s%%',
                    'header' => 'Denumire',
                ],
                'stoc' => [
                    'type' => 'int',
                    'caption' => 'Stoc',
                    'format' => '%d',
                    'default' => -1,
                    'empty_value' => -1,
                    'style' => 'text-align:right;width:2em;',
                    'header' => 'Stoc',
                    'header_caption' => '> ',
                ],
                'minimum_stock' => [
                    'type' => 'int',
                    'format' => '%d',
                    'default' => -1,
                    'empty_value' => -1,
                    'style' => 'text-align:right;width:2em;',
                    'header' => 'minimum_stock',
                    'header_caption' => '> ',
                ],
                'cod' => [
                    'type' => 'text',
                    'caption' => 'Cod articol',
                    'format' => '%%%s%%',
                    'header' => 'Cod_Articol',
                ],
                'tip' => [
                    'type' => 'text',
                    'caption' => 'Tip aparat',
                    'format' => '%%%s%%',
                    'header' => 'Grupa',
                ],
                'model_names' => [
                    'type' => 'text',
                    'caption' => 'Modele compatibile',
                    'format' => '%%%s%%',
                    'header' => 'model_names',
                ],
            ],
            'columns' => [
                'Cod_Articol' => [
                    'title' => 'Cod articol',
                    'default_sort' => 'Asc',
                    'align' => 'center',
                ],
                'Denumire' => [
                    'title' => 'Denumire',
                    'align' => 'center',
                ],
                'Gest' => [
                    'title' => 'Gest',
                    'align' => 'center',
                ],
                'Grupa' => [
                    'title' => 'Grupa',
                    'align' => 'center',
                ],
                'model_names' => [
                    'title' => 'Modele compatibile',
                    'align' => 'center',
                    'format' => 'function:default', // this will call PluginIserviceView_Stoc::getModelNamesDisplay($row);
                ],
                'Stoc' => [
                    'title' => 'Stoc',
                    'align' => 'center',
                    'format' => '%0.2f'
                ],
                'ordered_amount' => [
                    'title' => 'Comenzi deschise',
                    'align' => 'center',
                    'format' => '%0.2f'
                ],
                'minimum_stock' => [
                    'title' => 'Stoc minim',
                    'align' => 'center',
                    'format' => 'function:default' // this will call PluginIserviceView_Stoc::getMinimumStockDisplay($row);
                ],
            ],
        ];
    }

}

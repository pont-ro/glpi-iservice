<?php

// Imported from iService2, needs refactoring.
class PluginIserviceView_Loturi_Stoc extends PluginIserviceView {

    static $order = 40;

    static function getName() {
        return 'Loturi de stoc';
    }

    static function getModelNamesDisplay($row_data) {
        $model_names = explode('<br>', $row_data['model_names']);
        $consumable_data = [];
        foreach ($model_names as $model_name) {
            $data = explode(':', $model_name, 2);
            if (count($data) > 1) {
                $consumable_data[$data[0]] = $data[1];
            }
        }

        return PluginIserviceConsumable_Model::showForConsumable($row_data['Cod_Articol'], $consumable_data, true);
    }

    static function getMinimumStockDisplay($row_data) {
        if (empty($row_data['minimum_stock'])) {
            $row_data['minimum_stock'] = 0;
        }

        global $CFG_PLUGIN_ISERVICE;
        $sanitized_consumable_id = PluginIserviceCommon::getHtmlSanitizedValue($row_data['Cod_Articol']);
        $result = "<a id='min-stock-link-$row_data[__row_id__]' class='clickable min-stock-link-$sanitized_consumable_id' onclick='$(\"#min-stock-span-$row_data[__row_id__]\").show();$(this).hide();'>{$row_data['minimum_stock']}</a>";
        $result .= "<span id='min-stock-span-$row_data[__row_id__]' style='display:none; white-space: nowrap;'>";
        $result .= "<input id='min-stock-edit-$row_data[__row_id__]' class='min-stock-edit-$sanitized_consumable_id' style='width:2em;' type='text' value='$row_data[minimum_stock]' />&nbsp;";
        $result .= "<i class='fa fa-check-circle' onclick='manageItemViaAjax(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageConsumable.php?operation=set_min_stock\", \"$row_data[Cod_Articol]\", \"$sanitized_consumable_id\", \"min-stock\", \"$row_data[__row_id__]\", \"\");' style='color:green'></i>&nbsp;";
        $result .= "<i class='fa fa-times' onclick='$(\"#min-stock-link-$row_data[__row_id__]\").show();$(\"#min-stock-span-$row_data[__row_id__]\").hide();'></i>";
        $result .= "</span>";

        return $result;
    }

    protected function getSettings() {
        return array(
            'name' => self::getName(),
            'query' => "
                SELECT
                      l.nrtran AS Nr_receptie
                    , t.dataint AS Data_Receptie
                    , codmat AS Cod_Articol
                    , n.denum AS Denumire
                    , l.gest AS Gest
                    , tipgest AS Tip_Gest
                    , l.grupa AS Grupa
                    , ROUND(pcont,2) AS Pret_unitar
                    , stoci AS Intrat
                    , iesiri AS Iesit
                    , stoci-iesiri AS Stoc
                    , (stoci-iesiri)*pcont AS Valoare
                    , obs as Observatii
                    , mn.model_names
                    , m.minimum_stock
                FROM {$this->table_prefix}hmarfa_lotm l
                LEFT JOIN {$this->table_prefix}hmarfa_nommarfa n ON l.codmat=n.cod
                INNER JOIN {$this->table_prefix}hmarfa_tran t USING (nrtran)
                LEFT JOIN
                    ( SELECT GROUP_CONCAT(CONCAT(pm.id, ':', pm.name) SEPARATOR '<br>') model_names, cm.plugin_iservice_consumables_id
                      FROM glpi_plugin_iservice_consumables_models cm
                      LEFT JOIN glpi_printermodels pm on pm.id = cm.printermodels_id
                      GROUP BY cm.plugin_iservice_consumables_id
                    ) mn ON mn.plugin_iservice_consumables_id = l.codmat
                LEFT JOIN glpi_plugin_iservice_minimum_stocks m on m.plugin_iservice_consumables_id = l.codmat
                WHERE stoci-iesiri > [stoc]
                    AND n.denum LIKE '[denum]'
                    AND codmat LIKE '[cod]'
                    AND obs LIKE '[obs]'
                    AND l.grupa LIKE '[tip]'
                    AND ((mn.model_names is null AND '[model_names]' = '%%') OR mn.model_names LIKE '[model_names]')
                    AND (m.minimum_stock is null and 0 > [minimum_stock] OR m.minimum_stock > [minimum_stock])
                    [gest]
                ",
            'default_limit' => 25,
            'filters' => array(
                'gest' => array(
                    'type' => 'select',
                    'caption' => 'Gestiune',
                    'options' => array(
                        '' => 'oricare',
                        'MR' => 'MR',
                        'MR2' => 'MR2',
                    ),
                    'format' => "AND l.gest = '%s'",
                    'header' => 'Gest',
                ),
                'denum' => array(
                    'type' => 'text',
                    'caption' => 'Denumire articol',
                    'format' => '%%%s%%',
                    'header' => 'Denumire',
                ),
                'stoc' => array(
                    'type' => 'int',
                    'caption' => 'Stoc',
                    'format' => '%d',
                    'default' => -999,
                    'empty_value' => -999,
                    'style' => 'text-align:right;width:2em;',
                    'header' => 'Stoc',
                    'header_caption' => '> ',
                ),
                'cod' => array(
                    'type' => 'text',
                    'caption' => 'Cod articol',
                    'format' => '%%%s%%',
                    'header' => 'Cod_Articol',
                ),
                'tip' => array(
                    'type' => 'text',
                    'caption' => 'Tip aparat',
                    'format' => '%%%s%%',
                    'header' => 'Grupa',
                ),
                'obs' => array(
                    'type' => 'text',
                    'caption' => 'Observații',
                    'format' => '%%%s%%',
                    'header' => 'Observatii',
                ),
                'model_names' => array(
                    'type' => 'text',
                    'caption' => 'Modele compatibile',
                    'format' => '%%%s%%',
                    'header' => 'model_names',
                ),
                'minimum_stock' => array(
                    'type' => 'int',
                    'format' => '%d',
                    'default' => -1,
                    'empty_value' => -1,
                    'style' => 'text-align:right;width:2em;',
                    'header' => 'minimum_stock',
                    'header_caption' => '> ',
                ),
            ),
            'columns' => array(
                'Nr_receptie' => array(
                    'title' => 'Nr recepție',
                ),
                'Data_Receptie' => array(
                    'title' => 'Data recepție',
                    'style' => 'white-space: nowrap;',
                    'default_sort' => 'DESC',
                ),
                'Cod_Articol' => array(
                    'title' => 'Cod articol',
                ),
                'Denumire' => array(
                    'title' => 'Denumire',
                ),
                'Gest' => array(
                    'title' => 'Gest',
                ),
                'model_names' => array(
                    'title' => 'Modele compatibile',
                    'align' => 'center',
                    'format' => 'function:default', // this will call PluginIserviceView_Loturi_Stoc::getModelNamesDisplay($row);
                ),
                'Grupa' => array(
                    'title' => 'Grupa',
                ),
                'Pret_unitar' => array(
                    'title' => 'Preț unitar',
                    'align' => 'right',
                    'format' => '%.2f',
                ),
                'Intrat' => array(
                    'title' => 'Intrat',
                    'align' => 'center',
                ),
                'Iesit' => array(
                    'title' => 'Ieșit',
                    'align' => 'center',
                ),
                'Stoc' => array(
                    'title' => 'Stoc',
                    'align' => 'center',
                ),
                'minimum_stock' => array(
                    'title' => 'Stoc minim',
                    'align' => 'center',
                    'format' => 'function:default' // this will call PluginIserviceView_Loturi_Stoc::getMinimumStockDisplay($row);
                ),
                'Valoare' => array(
                    'title' => 'Valoare',
                    'align' => 'right',
                    'format' => '%.2f',
                    'total' => true,
                ),
                'Observatii' => array(
                    'title' => 'Observații',
                ),
            ),
        );
    }

}
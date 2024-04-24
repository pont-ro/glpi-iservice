<?php

// Imported from iService2, needs refactoring. Original file: "Introders.php".
namespace GlpiPlugin\Iservice\Views;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use GlpiPlugin\Iservice\Views\View;
use PluginIserviceConsumable_Model;
use PluginIserviceHtml;
use PluginIserviceOrderStatus;
use PluginIserviceTicket;
use Session;

class Intorders extends View
{
    public static $rightname = 'plugin_iservice_view_intorders';

    public static $icon = 'ti ti-box-padding';

    public static function getName(): string
    {
        return _n('Internal order', 'Internal orders', SESSION::getPluralNumber(), 'iservice');
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

        return PluginIserviceConsumable_Model::showForConsumable($row_data['consumable_code'], $consumable_data, true);
    }

    public static function getAdditionalMenuOptions()
    {
        return [
            'sortOrder' => 80,
        ];
    }

    public static function getOpenedOrdersDisplay($row_data): string
    {
        if ($row_data['opened_orders'] > $row_data['amount']) {
            return $row_data['opened_orders'] - $row_data['amount'];
        }

        return '';
    }

    public static function getMinimumStockDisplay($row_data): string
    {
        if (empty($row_data['minimum_stock'])) {
            $row_data['minimum_stock'] = 0;
        }

        global $CFG_PLUGIN_ISERVICE;
        $sanitized_consumable_id = IserviceToolBox::getHtmlSanitizedValue($row_data['consumable_code']);
        $result                  = "<a id='min-stock-link-$row_data[__row_id__]' class='pointer min-stock-link-$sanitized_consumable_id' onclick='$(\"#min-stock-span-$row_data[__row_id__]\").show();$(this).hide();'>{$row_data['minimum_stock']}</a>";
        $result                 .= "<span id='min-stock-span-$row_data[__row_id__]' style='display:none; white-space: nowrap;'>";
        $result                 .= "<input id='min-stock-edit-$row_data[__row_id__]' class='min-stock-edit-$sanitized_consumable_id' style='width:2em;' type='text' value='$row_data[minimum_stock]' />&nbsp;";
        $result                 .= "<i class='fa fa-check-circle' onclick='manageItemViaAjax(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageConsumable.php?operation=set_min_stock\", \"$row_data[consumable_code]\", \"$sanitized_consumable_id\", \"min-stock\", \"$row_data[__row_id__]\", \"\");' style='color:green'></i>&nbsp;";
        $result                 .= "<i class='fa fa-times' onclick='$(\"#min-stock-link-$row_data[__row_id__]\").show();$(\"#min-stock-span-$row_data[__row_id__]\").hide();'></i>";
        $result                 .= "</span>";

        return $result;
    }

    protected function getSettings(): array
    {
        global $CFG_GLPI;
        $iservice_front = $CFG_GLPI['root_doc'] . "/plugins/iservice/front/";
        $order_buttons  = [];
        if (Session::haveRight('plugin_iservice_intorder', CREATE)) {
            $order_buttons[] = "<a class='vsubmit noprint' href='{$iservice_front}intorder.form.php'>" . __('Add') . " " . _n('Internal order', 'Internal orders', 1, 'iservice') . "</a>";
        }

        if (Session::haveRight('plugin_iservice_extorder', CREATE)) {
            $order_buttons[] = "<a class='vsubmit noprint' href='{$iservice_front}extorder.form.php'>" . __('Add') . " " . _n('External order', 'External orders', 1, 'iservice') . "</a>";
        }

//        if (Session::haveRight('plugin_iservice_view_extorders', READ)) {
//            $order_buttons[] = "<a class='vsubmit noprint' href='{$iservice_front}views.php?view=Extorders'>" . _n('External order', 'External orders', Session::getPluralNumber(), 'iservice') . "</a>";
//        }

        $order_status_options                            = PluginIserviceOrderStatus::getAllForDropdown();
        $order_status_all_options                        = implode(',', array_keys($order_status_options));
        $order_status_default                            = implode(',', PluginIserviceOrderStatus::getIdsFromWeight(PluginIserviceOrderStatus::WEIGHT_RECEIVED, '<'));
        $order_status_options[$order_status_default]     = 'Deschise';
        $order_status_options[$order_status_all_options] = 'Toate';

        $orderstatus_dropdown_options = [
            'type' => 'PluginIserviceOrderStatus',
            'options' => [
                'comments' => false,
                'addicon' => false,
            ],
        ];
        $form                         = new PluginIserviceHtml();
        $new_order_status_dropdown    = $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'intorder[new_status]', 0, false, $orderstatus_dropdown_options);

        return [
            'name' => _n('Internal order', 'Internal orders', Session::getPluralNumber(), 'iservice'),
            'prefix' => implode('&nbsp;&nbsp;&nbsp;', $order_buttons),
            'query' => "
                SELECT * FROM
                (
                SELECT
                      ic.id order_id
                    , ic.tickets_id ticket_id
                    , MAX(ioeo.plugin_iservice_extorders_id) extorder_id
                    , ic.plugin_iservice_consumables_id consumable_code
                    , c.denumire consumable_name
                    , c.grupa consumable_grupa
                    , c.stoc consumable_stock
                    , ic.amount
                    , ic.deadline
                    , u.id order_placer_id
                    , CONCAT(IFNULL(CONCAT(u.realname, ' '), ''), IFNULL(u.firstname, '')) order_placer
                    , s.id order_status_id
                    , s.name order_status
                    , ic.content order_comment
                    , ic.create_date
                    , ic.modify_date
                    , MAX(sc1.date) arrival_date
                    , MAX(sc2.date) ordered_date
                    , oc.`sum` opened_orders
                    , mn.model_names
                    , m.minimum_stock
                FROM glpi_plugin_iservice_intorders ic
                LEFT JOIN glpi_plugin_iservice_consumables c ON c.id = ic.plugin_iservice_consumables_id
                LEFT JOIN glpi_users u ON u.id = ic.users_id
                LEFT JOIN glpi_plugin_iservice_orderstatuses s ON s.id = ic.plugin_iservice_orderstatuses_id
                LEFT JOIN glpi_plugin_iservice_intorders_extorders ioeo ON ioeo.plugin_iservice_intorders_id = ic.id
                LEFT JOIN glpi_plugin_iservice_orderstatuschanges sc1 ON sc1.orders_id = ic.id
                                                                     AND sc1.type = 'plugin_iservice_intorder'
                                                                     AND sc1.plugin_iservice_orderstatuses_id_new = " . PluginIserviceOrderStatus::getIdReceived() . "
                LEFT JOIN glpi_plugin_iservice_orderstatuschanges sc2 ON sc2.orders_id = ic.id
                                                                     AND sc2.type = 'plugin_iservice_intorder'
                                                                     AND sc2.plugin_iservice_orderstatuses_id_new = " . PluginIserviceOrderStatus::getIdOrdered() . "
                LEFT JOIN
                    (
                        SELECT io.plugin_iservice_consumables_id id, SUM(amount) `sum`
                        FROM glpi_plugin_iservice_intorders io
                        JOIN glpi_plugin_iservice_orderstatuses os on os.id = io.plugin_iservice_orderstatuses_id
                        WHERE os.weight < " . PluginIserviceOrderStatus::WEIGHT_RECEIVED . "
                        GROUP BY io.plugin_iservice_consumables_id
                    ) oc on oc.id = c.id
                LEFT JOIN
                    ( SELECT GROUP_CONCAT(CONCAT(pm.id, ':', pm.name) SEPARATOR '<br>') model_names, cm.plugin_iservice_consumables_id
                      FROM glpi_plugin_iservice_consumables_models cm
                      LEFT JOIN glpi_printermodels pm on pm.id = cm.printermodels_id
                      GROUP BY cm.plugin_iservice_consumables_id
                    ) mn ON mn.plugin_iservice_consumables_id = c.id
                LEFT JOIN glpi_plugin_iservice_minimum_stocks m on m.plugin_iservice_consumables_id = c.id
                GROUP BY ic.id
                ) o
                WHERE order_id LIKE '[order_id]'
                    AND ((ticket_id IS NULL AND '[ticket_id]' = '%%') OR ticket_id LIKE '[ticket_id]')
                    AND order_status_id in ([order_status])
                    AND create_date < '[create_date]'
                    AND consumable_code LIKE '[consumable_code]'
                    AND ((consumable_name IS NULL AND '[consumable_name]' = '%%') OR consumable_name LIKE '[consumable_name]')
                    AND ((consumable_grupa IS NULL AND '[consumable_grupa]' = '%%') OR consumable_grupa LIKE '[consumable_grupa]')
                    AND ((order_comment IS NULL AND '[order_comment]' = '%%') OR order_comment LIKE '[order_comment]')
                    AND (deadline IS NULL OR deadline < '[deadline]')
                    AND (arrival_date IS NULL OR arrival_date < '[arrival_date]')
                    AND (ordered_date IS NULL OR ordered_date < '[ordered_date]')
                    AND ((extorder_id IS NULL AND '[extorder_id]' = '%%') OR extorder_id LIKE '[extorder_id]')
                    AND ((model_names is null AND '[model_names]' = '%%') OR model_names LIKE '[model_names]')
                    AND (minimum_stock is null and 0 > [minimum_stock] OR minimum_stock > [minimum_stock])
                    [amount]
                    [order_placer]
            ",
            'id_field' => 'order_id',
            'itemtype' => 'intorder',
            'default_limit' => 50,
            'mass_actions' => [
                'order_again' => [
                    'caption' => 'Comandă din nou',
                    'action' => 'intorder.form.php',
                ],
                'create_extorder' => [
                    'caption' => 'Creează comandă externă',
                    'action' => 'extorder.form.php',
                ],
                'change_status' => [
                    'caption' => 'Schimbă starea',
                    'action' => 'intorder.form.php',
                    'suffix' => $new_order_status_dropdown,
                ],
            ],
            'show_export' => true,
            'filters' => [
                'order_id' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'order_id',
                ],
                'ticket_id' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'ticket_id',
                ],
                'extorder_id' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'extorder_id',
                ],
                'create_date' => [
                    'type' => self::FILTERTYPE_DATE,
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => date('Y-m-d'),
                    'header' => 'create_date',
                    'header_caption' => '< ',
                ],
                'deadline' => [
                    'type' => self::FILTERTYPE_DATE,
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => date('Y-m-d', strtotime("+7 days")),
                    'header' => 'deadline',
                    'header_caption' => '< ',
                ],
                'ordered_date' => [
                    'type' => self::FILTERTYPE_DATE,
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => date('Y-m-d'),
                    'header' => 'ordered_date',
                    'header_caption' => '< ',
                ],
                'arrival_date' => [
                    'type' => self::FILTERTYPE_DATE,
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => date('Y-m-d'),
                    'header' => 'arrival_date',
                    'header_caption' => '< ',
                ],
                'consumable_code' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'consumable_code',
                ],
                'consumable_name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'consumable_name',
                ],
                'model_names' => [
                    'type' => 'text',
                    'caption' => 'Modele compatibile',
                    'format' => '%%%s%%',
                    'header' => 'model_names',
                ],
                'consumable_grupa' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'consumable_grupa',
                ],
                'amount' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => 'AND amount < %d',
                    'empty_value' => 99999,
                    'style' => 'width: 3em;',
                    'header' => 'amount',
                    'header_caption' => '< ',
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
                'order_comment' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'order_comment',
                ],
                'order_placer' => [
                    'type' => self::FILTERTYPE_SELECT,
                    'format' => 'AND order_placer_id = %d',
                    'header' => 'order_placer',
                    'options' => IserviceToolBox::getUsersByProfiles(['tehnician']),
                    'visible' => !in_array($_SESSION["glpiactiveprofile"]["name"], ['subtehnician', 'superclient', 'client']),
                ],
                'order_status' => [
                    'type' => self::FILTERTYPE_SELECT,
                    'options' => $order_status_options,
                    'empty_value' => $order_status_default,
                    'header' => 'order_status',
                ],
            ],
            'columns' => [
                'order_id' => [
                    'title' => 'Nr. cmd. int.',
                    'align' => 'center',
                    'link' => [
                        'href' => 'intorder.form.php?id=[order_id]',
                        'title' => 'Vizualizează comanda internă',
                    ],
                    'default_sort' => 'DESC',
                ],
                'extorder_id' => [
                    'title' => 'Nr. cmd. ext.',
                    'align' => 'center',
                    'link' => [
                        'href' => 'extorder.form.php?id=[extorder_id]',
                        'title' => 'Vizualizează comanda externă',
                    ],
                ],
                'ticket_id' => [
                    'title' => 'Tichet',
                    'align' => 'center',
                    'link' => [
                        'href' => 'ticket.form.php?id=[ticket_id]&mode=' . PluginIserviceTicket::MODE_CLOSE,
                        'title' => 'Vizualizează tichetul originator',
                    ],
                    'default_sort' => 'DESC',
                ],
                'create_date' => [
                    'title' => 'Dată comandă',
                    'align' => 'center',
                ],
                'deadline' => [
                    'title' => 'Termen de livrare',
                    'align' => 'center',
                ],
                'ordered_date' => [
                    'title' => 'Data trimiterii la furnizor',
                    'align' => 'center',
                ],
                'arrival_date' => [
                    'title' => 'Data sosirii',
                    'align' => 'center',
                ],
                'consumable_code' => [
                    'title' => 'Cod',
                ],
                'consumable_name' => [
                    'title' => __('Consumable', 'iservice'),
                ],
                'model_names' => [
                    'title' => 'Modele compatibile',
                    'align' => 'center',
                    'format' => 'function:default', // This will call PluginIserviceView_Intorders::getModelNamesDisplay($row).
                ],
                'consumable_grupa' => [
                    'title' => 'grupa',
                ],
                'amount' => [
                    'title' => 'Cantitate',
                    'align' => 'center',
                ],
                'consumable_stock' => [
                    'title' => 'Stoc curent',
                    'align' => 'center',
                ],
                'opened_orders' => [
                    'title' => 'Alte comenzi nefinalizate',
                    'align' => 'center',
                    'format' => 'function:default', // This will call PluginIserviceView_Intorders::getOpenedOrdersDisplay($row).
                ],
                'minimum_stock' => [
                    'title' => 'Stoc minim',
                    'align' => 'center',
                    'format' => 'function:default' // This will call PluginIserviceView_Intorders::getMinimumStockDisplay($row).
                ],
                'order_comment' => [
                    'title' => 'Observații',
                ],
                'order_placer' => [
                    'title' => 'Solicitant',
                    'align' => 'center',
                ],
                'order_status' => [
                    'title' => 'Stare comandă',
                    'align' => 'center',
                    'link' => [
                        'type' => 'detail',
                        'name' => 'Lista modificări comanda internă [order_id]',
                        'query' => "
                            SELECT
                                    sc.date
                                , CONCAT(IFNULL(CONCAT(u.realname, ' '), ''), IFNULL(u.firstname, '')) order_modifier
                                , os.name old_status
                                , ns.name new_status
                            FROM glpi_plugin_iservice_orderstatuschanges sc
                            LEFT JOIN glpi_users u on u.id = sc.users_id
                            LEFT JOIN glpi_plugin_iservice_orderstatuses os ON os.id = sc.plugin_iservice_orderstatuses_id_old
                            LEFT JOIN glpi_plugin_iservice_orderstatuses ns ON ns.id = sc.plugin_iservice_orderstatuses_id_new
                            WHERE sc.type='plugin_iservice_intorder' AND sc.orders_id = [order_id]
                            ORDER BY sc.date DESC
                            ",
                        'columns' => [
                            'date' => [
                                'title' => 'Data',
                                'align' => 'center',
                            ],
                            'order_modifier' => [
                                'title' => 'Modificator',
                                'align' => 'center',
                            ],
                            'old_status' => [
                                'title' => 'Stare veche',
                                'align' => 'center',
                            ],
                            'new_status' => [
                                'title' => 'Stare nouă',
                                'align' => 'center',
                            ],
                        ],
                    ],
                ],
                /*
                'modify_date' => [
                'title' => 'Dată modificare stare',
                'align' => 'center',
                ],
                /**/
            ],
        ];
    }

}

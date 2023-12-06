<?php

// Imported from iService2, needs refactoring. Original file: "Extorders.php".
namespace GlpiPlugin\Iservice\Specialviews;

use GlpiPlugin\Iservice\Views\View;
use \Session;
use \PluginIserviceOrderStatus;
use \PluginIserviceHtml;

class Extorders extends View
{
    public static $rightname = 'plugin_iservice_view_extorders';

    public static $icon = 'ti ti-box-margin';

    public static function getName(): string
    {
        return _n('External order', 'External orders', Session::getPluralNumber(), 'iservice');
    }

    protected function getSettings(): array
    {
        global $CFG_GLPI;
        $iservice_front = $CFG_GLPI['root_doc'] . "/plugins/iservice/front/";
        $order_buttons  = [];
        if (Session::haveRight('plugin_iservice_extorder', CREATE)) {
            $order_buttons[] = "<a class='submit noprint' href='{$iservice_front}extorder.form.php'>" . __('Add') . " " . _n('External order', 'External orders', 1, 'iservice') . "</a>";
        }

        if (Session::haveRight('plugin_iservice_view_intorders', READ)) {
            $order_buttons[] = "<a class='submit noprint' href='{$iservice_front}views.php?view=GlpiPlugin\Iservice\Specialviews\Intorders'>"
                . _n('Internal order', 'Internal orders', Session::getPluralNumber(), 'iservice') . "</a>";
        }

        $order_status_options                            = PluginIserviceOrderStatus::getAllForDropdown();
        $order_status_all_options                        = implode(',', array_keys($order_status_options));
        $order_status_default                            = implode(',', PluginIserviceOrderStatus::getIdsFromWeight(PluginIserviceOrderStatus::WEIGHT_RECEIVED, '<'));
        $order_status_options[$order_status_default]     = 'Deschise';
        $order_status_options[$order_status_all_options] = 'Toate';

        $orderstatus_dropdown_options = [
            'type' => 'PluginIserviceOrderStatus',
            'options' => [
                'comments' => false,
                'addicon' => false
            ],
        ];
        $form                         = new PluginIserviceHtml();
        $new_order_status_dropdown    = $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'extorder[new_status]', 0, false, $orderstatus_dropdown_options);

        return [
            'name' => _n('External order', 'External orders', Session::getPluralNumber(), 'iservice'),
            'prefix' => implode('&nbsp;&nbsp;&nbsp;', $order_buttons),
            'query' => "
						SELECT * FROM
						(
						SELECT
								eo.id order_id
							, GROUP_CONCAT(iow.name SEPARATOR '<br>') intorders
							, su.name supplier_name
							, u.id order_placer_id
							, CONCAT(IFNULL(CONCAT(u.realname, ' '), ''), IFNULL(u.firstname, '')) order_placer
							, s.id order_status_id
							, s.name order_status
							, eo.content order_comment
							, eo.create_date
							, eo.modify_date
						FROM glpi_plugin_iservice_extorders eo
						LEFT JOIN glpi_suppliers su ON su.id = eo.suppliers_id
						LEFT JOIN glpi_users u ON u.id = eo.users_id
						LEFT JOIN glpi_plugin_iservice_orderstatuses s ON s.id = eo.plugin_iservice_orderstatuses_id
						LEFT JOIN glpi_plugin_iservice_intorders_extorders ioeo ON ioeo.plugin_iservice_extorders_id = eo.id
						LEFT JOIN glpi_plugin_iservice_intorders_view iow ON iow.id = ioeo.plugin_iservice_intorders_id
						GROUP BY eo.id
						) o
						WHERE order_id LIKE '[order_id]'
						  AND order_status_id in ([order_status])
						  AND create_date < '[create_date]'
							AND ((intorders IS NULL AND '[intorders]' = '%%') OR intorders LIKE '[intorders]')
							AND ((supplier_name IS NULL AND '[supplier_name]' = '%%') OR supplier_name LIKE '[supplier_name]')
							AND ((order_comment IS NULL AND '[order_comment]' = '%%') OR order_comment LIKE '[order_comment]')
							[order_placer]
						",
            'id_field' => 'order_id',
            'itemtype' => 'extorder',
            'default_limit' => 50,
            'mass_actions' => [
                'change_status' => [
                    'caption' => 'Schimbă starea',
                    'action' => 'extorder.form.php',
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
                'create_date' => [
                    'type' => self::FILTERTYPE_DATE,
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => date('Y-m-d'),
                    'header' => 'create_date',
                    'header_caption' => '< ',
                ],
                'intorders' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'intorders',
                ],
                'supplier_name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'supplier_name',
                ],
                'order_comment' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'order_comment',
                ],
                'order_placer' => [
                    'type' => self::FILTERTYPE_USER,
                    'format' => 'AND order_placer_id = %d',
                    'header' => 'order_placer',
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
                    'title' => 'Nr. cmd. ext.',
                    'align' => 'center',
                    'link' => [
                        'href' => 'extorder.form.php?id=[order_id]',
                        'title' => 'Vizualizează comandă',
                    ],
                ],
                'create_date' => [
                    'title' => 'Dată comandă',
                    'align' => 'center',
                    'default_sort' => 'DESC',
                ],
                'intorders' => [
                    'title' => _n('Consumable', 'Consumables', Session::getPluralNumber(), 'iservice'),
                ],
                'supplier_name' => [
                    'title' => _n('Supplier', 'Suppliers', 1, 'iservice'),
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
                        'name' => 'Lista modificări',
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
												WHERE sc.type='plugin_iservice_extorder' AND sc.orders_id = [order_id]
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
                    ]
                ],
            ],
        ];
    }

}

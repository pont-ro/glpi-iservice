<?php

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use Glpi\Application\View\TemplateRenderer;

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * PluginIserviceIntOrder Class
 * */
class PluginIserviceIntOrder extends CommonDBTM
{
    public $dohistory     = true;
    static $rightname     = 'plugin_iservice_intorder';
    protected $usenotepad = true;

    public static function getTypeName($nb = 0)
    {
        return _tn('Internal order', 'Internal orders', $nb);
    }

    public function getRawName()
    {
        return _tn('Internal order', 'Internal orders', 1) . " #" . $this->getID();
    }

    public function prepareInputForAdd($input)
    {
        return $this->prepareInputForUpdate($input);
    }

    public function prepareInputForUpdate($input)
    {
        if (empty($this->fields['users_id']) && !isset($input['users_id'])) {
            $input['users_id'] = $_SESSION['glpiID'];
        }

        $input['modify_date'] = date('Y-m-d H:i:s');

        if ($input['plugin_iservice_orderstatuses_id'] != ($this->fields['plugin_iservice_orderstatuses_id'] ?? '') || isset($input['_add'])) {
            $input['_status_change']['add']                                  = 'add';
            $input['_status_change']['_no_message']                          = true;
            $input['_status_change']['type']                                 = 'plugin_iservice_intorder';
            $input['_status_change']['plugin_iservice_orderstatuses_id_old'] = $this->isNewItem() ? 0 : $this->fields['plugin_iservice_orderstatuses_id'];
            $input['_status_change']['plugin_iservice_orderstatuses_id_new'] = $input['plugin_iservice_orderstatuses_id'];
        }

        return $input;
    }

    public function post_addItem()
    {
        parent::post_addItem();
        $this->post_updateItem();
    }

    public function post_updateItem($history = 1)
    {
        parent::post_updateItem($history);

        if ($_SESSION['plugin']['iservice']['importInProgress'] ?? false) {
            return;
        }

        if (isset($this->input['_status_change'])) {
            $status_change                              = new PluginIserviceOrderStatusChange();
            $this->input['_status_change']['orders_id'] = $this->getID();
            $status_change->add($this->input['_status_change']);

            $extorder     = new PluginIserviceExtOrder();
            $order_status = null;
            foreach (PluginIserviceIntOrder_ExtOrder::getAllIntordersForIntorder($this->getID()) as $intorder) {
                if ($order_status === null) {
                    $order_status = $intorder['plugin_iservice_orderstatuses_id'];
                    $extorder->getFromDB($intorder['extorder_id']);
                } elseif ($order_status !== $intorder['plugin_iservice_orderstatuses_id']) {
                    $order_status = '';
                    break;
                }
            }

            if (!empty($order_status) && !$extorder->isNewItem() && $extorder->fields['plugin_iservice_orderstatuses_id'] != $order_status) {
                $extorder->update(
                    [
                        'id' => $extorder->getID(),
                        '_no_message' => true,
                        '_skip_intorders_update' => true,
                        'plugin_iservice_orderstatuses_id' => $order_status,
                    ]
                );
            }
        }
    }

    public function display($options = [])
    {
        if (isset($options['id']) && !$this->isNewID($options['id'])) {
            if (!$this->getFromDB($options['id'])) {
                Html::displayNotFoundError();
            }
        }

        // in case of lefttab layout, we couldn't see "right error" message
        if ($this->get_item_to_display_tab) {
            if (isset($_GET["id"]) && $_GET["id"] && !$this->can($_GET["id"], READ)) {
                html::displayRightError();
            }
        }

        $this->showForm($options['id'], $options);
    }

    public function showForm($ID, $options = [])
    {
        $form = new PluginIserviceHtml();
        $this->initForm($ID, $options);
        $options['candel']      = false; // not to show the Delete button
        $options['formoptions'] = " class='iservice-form two-column'";

        $defaults = [
            'amount' => 1,
            'users_id' => $_SESSION['glpiID'],
            'deadline' => date("Y-m-d", strtotime("+7 days")),
            'plugin_iservice_orderstatuses_id' => PluginIserviceOrderStatus::getIdFromWeight(PluginIserviceOrderStatus::WEIGHT_STARTED),
        ];

        if (!($this->getID() > 0)) {
            foreach ($defaults as $field_name => $default_value) {
                $this->fields[$field_name] = $default_value;
            }
        }

        // Form header
        $this->showFormHeader($options);

        $consumable_id = IserviceToolBox::getInputVariable('plugin_iservice_consumables_id', $this->fields['plugin_iservice_consumables_id']);
        if (!empty($consumable_id)) {
            $this->fields['plugin_iservice_consumables_id'] = $consumable_id;
        }

        // Consumable
        $stock_and_other_orders = '';
        if ($consumable_id) {
            $consumable = new PluginIserviceConsumable();
            $consumable->getFromDB($consumable_id);
            $intorders              = new PluginIserviceIntOrder();
            $other_orders           = PluginIserviceDB::getQueryResult(
                "
                select sum(amount) `sum`
                from glpi_plugin_iservice_intorders io
                join glpi_plugin_iservice_orderstatuses os on os.id = io.plugin_iservice_orderstatuses_id and os.weight < " . PluginIserviceOrderStatus::WEIGHT_RECEIVED . "
                where io.plugin_iservice_consumables_id = '$consumable_id'"
            );
            $other_orders_sum       = !empty($other_orders[0]['sum']) ? $other_orders[0]['sum'] : 0;
            $other_orders_sum_style = $other_orders_sum ? "" : "style='color:red;'";
            $stock_and_other_orders = "<br><br>Stoc curent: <b>{$consumable->fields['stoc']}</b>, alte comenzi nefinalizate: <b $other_orders_sum_style>$other_orders_sum</b>";
        }

        $consumables_dropdown_options = [
            'type' => 'PluginIserviceConsumable',
            'class' => 'full',
            'options' => [
                'comments' => false,
                'addicon' => false,
                'on_change' => '$(this).closest("form").submit();',
            ],
        ];
        $form->displayFieldTableRow(_tn('Consumable', 'Consumables', 1), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'plugin_iservice_consumables_id', $this->fields['plugin_iservice_consumables_id'], !empty($this->fields['plugin_iservice_consumables_id']), $consumables_dropdown_options) . $stock_and_other_orders);

        // Amount
        $form->displayFieldTableRow(_t('Amount'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'amount', $this->fields['amount'], $this->getOrderStatusWeight() > PluginIserviceOrderStatus::WEIGHT_STARTED));

        // User
        $user_dropdown_options = [
            'type' => 'Dropdown',
            'class' => 'full',
            'method' => 'showFromArray',
            'values' => IserviceToolBox::getUsersByProfiles(['tehnician']),
        ];
        $form->displayFieldTableRow('Solicitant', $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'users_id', $this->fields['users_id'], $this->getOrderStatusWeight() > PluginIserviceOrderStatus::WEIGHT_STARTED, $user_dropdown_options));

        // Deadline
        $form->displayFieldTableRow(_t('Deadline'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DATE, 'deadline', $this->fields['deadline'], false, ['class' => 'full']));

        // Order status
        $orderstatus_dropdown_options = [
            'type' => 'PluginIserviceOrderStatus',
            'options' => [
                'comments' => false,
                'addicon' => false,
            ],
            'class' => 'full',
        ];
        $form->displayFieldTableRow(_tn('Order status', 'Order statuses', Session::getPluralNumber()), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'plugin_iservice_orderstatuses_id', $this->fields['plugin_iservice_orderstatuses_id'], $this->isNewItem(), $orderstatus_dropdown_options));

        // Comments
        $form->displayFieldTableRow(__('Comments'), $form->generateField(PluginIserviceHtml::FIELDTYPE_MEMO, 'content', $this->fields['content']));

        // Form footer
        $this->showFormButtons($options);
        return true;
    }

    public function getOrderStatusWeight()
    {
        if (isset($this->fields['plugin_iservice_orderstatuses_id'])) {
            return PluginIserviceOrderStatus::getWeight($this->fields['plugin_iservice_orderstatuses_id']);
        } else {
            return null;
        }
    }

    public function getSpecificMassiveActions($checkitem = null)
    {
        $actions = parent::getSpecificMassiveActions($checkitem);
        // TODO: Insert intorder creation
        return $actions;
    }

    public function showFormButtons($options = [])
    {
        $params = [
            'colspan'      => 2,
            'withtemplate' => '',
            'candel'       => true,
            'canedit'      => true,
            'addbuttons'   => [],
            'formfooter'   => null,
        ];

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $params[$key] = $val;
            }
        }

        echo "</table>";

        TemplateRenderer::getInstance()->display(
            '@iservice/pages/support/components/buttons.html.twig', [
                'item'   => $this,
                'params' => $params,
            ]
        );

        echo "</div>"; // .asset
    }

}

<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

/**
 * PluginIserviceExtOrder Class
 * */
class PluginIserviceExtOrder extends CommonDBTM
{

    public $dohistory     = true;
    static $rightname     = 'plugin_iservice_extorder';
    protected $usenotepad = true;

    static function getTypeName($nb = 0)
    {
        return _n('External order', 'External orders', $nb, 'iservice');
    }

    function getRawName()
    {
        return __('External order', 'iservice') . " #" . $this->getID();
    }

    function prepareInputForAdd($input)
    {
        return $this->prepareInputForUpdate($input);
    }

    function prepareInputForUpdate($input)
    {
        if (!isset($input['users_id'])) {
            $input['users_id'] = $_SESSION['glpiID'];
        }

        $input['modify_date'] = date('Y-m-d H:i:s');

        if ($input['plugin_iservice_orderstatuses_id'] != ($this->fields['plugin_iservice_orderstatuses_id'] ?? null) || isset($input['_add'])) {
            $input['_status_change']['add']                                  = 'add';
            $input['_status_change']['_no_message']                          = true;
            $input['_status_change']['type']                                 = 'plugin_iservice_extorder';
            $input['_status_change']['plugin_iservice_orderstatuses_id_old'] = $this->isNewItem() ? '' : $this->fields['plugin_iservice_orderstatuses_id'];
            $input['_status_change']['plugin_iservice_orderstatuses_id_new'] = $input['plugin_iservice_orderstatuses_id'];
        }

        return $input;
    }

    function post_addItem()
    {
        $this->post_updateItem();
    }

    function post_updateItem($history = 1)
    {
        if ($_SESSION['plugin']['iservice']['importInProgress'] ?? false) {
            return;
        }

        if (isset($this->input['_status_change'])) {
            $status_change                              = new PluginIserviceOrderStatusChange();
            $this->input['_status_change']['orders_id'] = $this->getID();
            $status_change->add($this->input['_status_change']);

            $status_weight = PluginIserviceOrderStatus::getWeight($this->input['plugin_iservice_orderstatuses_id']);

            if (!isset($this->input['_skip_intorders_update']) || !$this->input['_skip_intorders_update']) {
                foreach (PluginIserviceIntOrder_ExtOrder::getForExtOrder($this->getID()) as $intorder_fields) {
                    $intorder = new PluginIserviceIntOrder();
                    $intorder->getFromDB($intorder_fields['id']);
                    $intorder_status_weight = PluginIserviceOrderStatus::getWeight($intorder->fileds['plugin_iservice_orderstatuses_id']);
                    if ($intorder_status_weight < $status_weight) {
                        $intorder->update(
                            [
                                $intorder->getIndexName() => $intorder_fields['id'],
                                '_no_message' => true,
                                'plugin_iservice_orderstatuses_id' => $this->input['plugin_iservice_orderstatuses_id'],
                            ]
                        );
                    }
                }
            }
        }
    }

    function display($options = [])
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

    function showForm($ID, $options = [])
    {
        $form = new PluginIserviceHtml();
        $this->initForm($ID, $options);
        $options['candel']      = false; // not to show the Delete button
        $options['formoptions'] = " class='iservice-form two-column'";

        $defaults = [
            'users_id' => $_SESSION['glpiID'],
            'plugin_iservice_orderstatuses_id' => PluginIserviceOrderStatus::getIdFromWeight(PluginIserviceOrderStatus::WEIGHT_PROCESSED),
        ];

        // Form header
        $this->showFormHeader($options);

        echo "<tr>";
        echo "<td>" . _n('Internal order', 'Internal orders', Session::getPluralNumber(), 'iservice') . "</td>";
        echo "<td>";

        if ($this->isNewItem()) {
            foreach ($defaults as $field_name => $default_value) {
                $this->fields[$field_name] = $default_value;
            }

            echo "Salvați comanda externă pentru a adăuga comenzt interne";
            // Cannot add intorders just yet
        } else {
            // IntOrders - can add intorders only to an existing extorder
            PluginIserviceIntOrder_ExtOrder::showForExtOrder($this, false, $this->getOrderStatusWeight() > PluginIserviceOrderStatus::WEIGHT_PROCESSED);
        }

        echo "</td>";
        echo "</tr>";

        // User
        $user_dropdown_options = [
            'type' => 'Dropdown',
            'class' => 'full',
            'method' => 'showFromArray',
            'values' => IserviceToolBox::getUsersByProfiles(['tehnician']),
        ];
        $form->displayFieldTableRow('Solicitant', $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'users_id', $this->fields['users_id'], $this->getOrderStatusWeight() > PluginIserviceOrderStatus::WEIGHT_PROCESSED, $user_dropdown_options));

        // Supplier
        $supplier_dropdown_options = [
            'type' => 'Supplier',
            'class' => 'full',
            'options' => [
                'comments' => false,
                'addicon' => false,
            ],
        ];
        $form->displayFieldTableRow(_n('Supplier', 'Suppliers', 1, 'iservice'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'suppliers_id', $this->fields['suppliers_id'], $this->getOrderStatusWeight() > PluginIserviceOrderStatus::WEIGHT_PROCESSED, $supplier_dropdown_options));

        // Order status
        $orderstatus_dropdown_options = [
            'type' => 'PluginIserviceOrderStatus',
            'class' => 'full',
            'options' => [
                'comments' => false,
                'addicon' => false,
                'used' => [1],
            ],
        ];
        $form->displayFieldTableRow(_n('Order status', 'Order statuses', Session::getPluralNumber(), 'iservice'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'plugin_iservice_orderstatuses_id', $this->fields['plugin_iservice_orderstatuses_id'], $this->isNewItem(), $orderstatus_dropdown_options));

        // Comments
        $form->displayFieldTableRow(__('Comments'), $form->generateField(PluginIserviceHtml::FIELDTYPE_MEMO, 'content', $this->fields['content']));

        // Form footer
        $this->showFormButtons($options);
        return true;
    }

    function getOrderStatusWeight()
    {
        if (isset($this->fields['plugin_iservice_orderstatuses_id'])) {
            return PluginIserviceOrderStatus::getWeight($this->fields['plugin_iservice_orderstatuses_id']);
        } else {
            return null;
        }
    }

    function getSpecificMassiveActions($checkitem = null)
    {
        $actions = parent::getSpecificMassiveActions($checkitem);
        // TODO: Insert extorder creation
        return $actions;
    }

}

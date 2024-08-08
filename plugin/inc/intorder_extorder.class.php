<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceIntOrder_ExtOrder extends CommonDBRelation
{
    public static $itemtype_1             = 'PluginIserviceIntOrder';
    public static $items_id_1             = 'plugin_iservice_intorders_id';
    public static $itemtype_2             = 'PluginIserviceExtOrder';
    public static $items_id_2             = 'plugin_iservice_extorders_id';
    public static $check_entity_coherency = false;

    function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }

    static function canHaveIntOrder(PluginIserviceIntOrder $intorder)
    {
        if (empty($intorder) || get_class($intorder) != 'PluginIserviceIntOrder' || $intorder->isNewItem()) {
            return false;
        }

        $order_status = new PluginIserviceOrderStatus();
        if (!$order_status->getFromDB($intorder->fields['plugin_iservice_orderstatuses_id'])) {
            return false;
        }

        return $order_status->fields['weight'] < PluginIserviceOrderStatus::WEIGHT_PROCESSED;
    }

    static function canHaveExtOrder(PluginIserviceExtOrder $extorder)
    {
        if (empty($extorder) || get_class($extorder) != 'PluginIserviceExtOrder' || $extorder->isNewItem()) {
            return false;
        }

        $order_status = new PluginIserviceOrderStatus();
        if (!$order_status->getFromDB($extorder->fields['plugin_iservice_orderstatuses_id'])) {
            return false;
        }

        return $order_status->fields['weight'] < PluginIserviceOrderStatus::WEIGHT_ORDERED;
    }

    function canCreateItem()
    {
        $intorder = new PluginIserviceIntOrder();
        if (!isset($this->fields['plugin_iservice_intorders_id']) || !$intorder->getFromDB($this->fields['plugin_iservice_intorders_id']) || !self::canHaveExtOrder($intorder)) {
            return false;
        }

        $extorder = new PluginIserviceExtOrder();
        if (!isset($this->fields['plugin_iservice_extorders_id']) || !$extorder->getFromDB($this->fields['plugin_iservice_extorders_id']) || !self::canHaveExtOrder($extorder)) {
            return false;
        }

        return parent::canCreateItem();
    }

    static function getForExtOrder($id)
    {
        global $DB;

        if (empty($id)) {
            return false;
        }

        $sql = "SELECT ie.id idd, i.*, c.id consumable_code, c.denumire consumable_name
            FROM `glpi_plugin_iservice_intorders_extorders` ie
						LEFT JOIN `glpi_plugin_iservice_intorders` i on i.id = ie.plugin_iservice_intorders_id
						LEFT JOIN `glpi_plugin_iservice_consumables` c on c.id = i.plugin_iservice_consumables_id
            WHERE `plugin_iservice_extorders_id` = $id
						ORDER BY ie.id";

        $intorders = [];

        foreach ($DB->request($sql) as $data) {
            $intorders[$data['idd']] = $data;
        }

        return $intorders;
    }

    public static function getAllIntordersForIntorder($id)
    {
        global $DB;

        if (empty($id)) {
            return false;
        }

        $sql = "SELECT ie.id idd, ie.plugin_iservice_extorders_id extorder_id, i.*, c.id consumable_code, c.denumire consumable_name
            FROM `glpi_plugin_iservice_intorders_extorders` ie
						LEFT JOIN `glpi_plugin_iservice_intorders` i on i.id = ie.plugin_iservice_intorders_id
						LEFT JOIN `glpi_plugin_iservice_consumables` c on c.id = i.plugin_iservice_consumables_id
            WHERE `plugin_iservice_extorders_id` = (SELECT plugin_iservice_extorders_id
						                                       FROM `glpi_plugin_iservice_intorders_extorders`
																									 WHERE plugin_iservice_intorders_id = $id)
						ORDER BY ie.id";

        $intorders = [];

        foreach ($DB->request($sql) as $data) {
            $intorders[$data['idd']] = $data;
        }

        return $intorders;
    }

    public static function showForExtOrder(PluginIserviceExtOrder $ext_order, $generate_form = true, $readonly = false)
    {
        $id = $ext_order->getID();

        if (!$ext_order->can($id, READ)) {
            return false;
        }

        $canedit = !$readonly && $ext_order->canEdit($id);
        $rand    = mt_rand();

        $intorders    = self::getForExtOrder($id);
        $intorder_ids = [];
        foreach ($intorders as $intorder) {
            $intorder_ids[] = $intorder['idd'];
        }

        if ($canedit) {
            echo "<div class='firstbloc'>";
            if ($generate_form) {
                echo "<form name='extorderintorder_form$rand' id='extorderintorder_form$rand' method='post'
								 action='" . Toolbox::getItemTypeFormURL(__CLASS__) . "'>";
            }

            echo "<table class='tab_cadre_fixe add-intorder-table'>";
            if ($generate_form) {
                echo "<tr class='tab_bg_2'><th colspan='2'>" . __('Add an item') . "</th></tr>";
            }

            echo "<tr class='tab_bg_1'><td width='81%'>";
            $intorders_selector_options = [
                'comments' => false,
                'display' => false,
                'condition' => ['plugin_iservice_orderstatuses_id = 1'],
                'name' => '_plugin_iservice_intorder[plugin_iservice_intorders_id]',
                'used' => $intorder_ids,
            ];
            echo PluginIserviceIntorder_View::dropdown($intorders_selector_options);
            echo "</td><td>";
            echo "<input type='submit' name='add_intorder' value=\"" . _sx('button', 'Add') . "\" class='submit'>";
            echo "</td></tr>";
            echo "</table>";
            if ($generate_form) {
                Html::closeForm();
            }

            echo "</div>";
        }

        if (!($number = count($intorders))) {
            return 0;
        }

        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><td width='81%'>";
        if ($canedit && $number && $generate_form) {
            Html::openMassiveActionsForm('mass' . __CLASS__ . $rand);
            $massiveactionparams = ['container' => 'mass' . __CLASS__ . $rand];
            Html::showMassiveActions($massiveactionparams);
        }

        echo "<table class='tab_cadre_fixe' width='81%'>";
        $header = '<tr>';
        if ($canedit && $number) {
            $header .= "<th width='10'>";// . Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand);
            $header .= "</th>";
        }

        $header .= "<th>" . _tn('Internal order', 'Internal orders', 1) . "</th>";
        $header .= "<th>" . "Cod" . "</th>";
        $header .= "<th>" . _tn('Consumable', 'Consumables', 1) . "</th>";
        $header .= "<th>" . _t('Amount') . "</th></tr>";
        echo $header;

        foreach ($intorders as $key => $intorder) {
            echo "<tr class='tab_bg_1'>";
            if ($canedit) {
                echo "<td width='10'>";
                // Html::showMassiveActionCheckBox(__CLASS__, $consumable["IDD"]);
                echo Html::getCheckbox(
                    [
                        'name' => "_plugin_iservice_intorders_extorders[$key]",
                        'zero_on_empty' => false,
                    ]
                );
                echo "</td>";
            }

            echo "<td class='center'>$intorder[id]</td>";
            echo "<td>$intorder[consumable_code]</td>";
            echo "<td>$intorder[consumable_name]</td>";
            echo "<td class='center'>$intorder[amount]</td>";
        }

        echo $header;
        echo "</table>";

        if ($canedit && $number && $generate_form) {
            $massiveactionparams['ontop'] = false;
            Html::showMassiveActions($massiveactionparams);
            Html::closeForm();
        }

        echo "</td><td>";
        if ($canedit) {
            echo "<input type='submit' name='remove_intorder' value='" . __('Delete') . "' class='submit' style='margin: 2px;'><br>";
        }

        echo "</td></tr>";
        echo "</table>";

        return $number;
    }

    function prepareInputForAdd($input)
    {
        return parent::prepareInputForAdd($input);
    }

    function post_addItem()
    {
        parent::post_addItem();

        if ($_SESSION['plugin']['iservice']['importInProgress'] ?? false) {
            return;
        }

        $extorder = new PluginIserviceExtOrder();
        $extorder->getFromDB($this->input['plugin_iservice_extorders_id']);
        $intorder = new PluginIserviceIntOrder();
        $intorder->update(
            [
                'id' => $this->input['plugin_iservice_intorders_id'],
                '_no_message' => true,
                'plugin_iservice_orderstatuses_id' => $extorder->fields['plugin_iservice_orderstatuses_id'],
            ]
        );
    }

    function post_deleteItem()
    {
        parent::post_deleteItem();
        $intorder = new PluginIserviceIntOrder();
        $intorder->update(
            [
                'id' => $this->input['plugin_iservice_intorders_id'],
                '_no_message' => true,
                'plugin_iservice_orderstatuses_id' => PluginIserviceOrderStatus::getIdFromWeight(PluginIserviceOrderStatus::WEIGHT_STARTED),
            ]
        );
    }

}

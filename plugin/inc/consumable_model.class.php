<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Consumable_Ticket Class
 *
 *  Relation between Tickets and Consumables
 * */
class PluginIserviceConsumable_Model extends CommonDBRelation {

    // From CommonDBRelation
    static public $itemtype_1 = 'Printermodel';
    static public $items_id_1 = 'printermodels_id';
    static public $itemtype_2 = 'PluginIserviceConsumable';
    static public $items_id_2 = 'plugin_iservice_consumables_id';
    static public $checkItem_2_Rights = self::HAVE_VIEW_RIGHT_ON_ITEM;

    function getForbiddenStandardMassiveAction() {

        $forbidden = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }

    function getForConsumable($id) {
        $query = "SELECT cm.*, pm.name FROM " . $this->getTable() . " cm LEFT JOIN glpi_printermodels pm ON pm.id = cm.printermodels_id WHERE " . self::$items_id_2 . " = '$id' ORDER BY id";
        $result_data = PluginIserviceCommon::getQueryResult($query);
        return empty($result_data) ? [] : $result_data;
    }

    /**
     * Print the HTML array for Items linked to a ticket
     *
     * @param $ticket Ticket object
     *
     * @return Nothing (display)
     * */
    static function showForConsumable($consumable_id, $printermodels=null, $return = false) {
        global $CFG_PLUGIN_ISERVICE;

        if (empty($consumable_id)) {
            return '';
        }

        if ($printermodels === null) {
            $consumable_models = new self();
            return self::showForConsumable($consumable_id, array_column($consumable_models->getForConsumable($consumable_id), 'name', 'printermodels_id'), $return);
        }

        $sanitized_consumable_id = PluginIserviceCommon::getHtmlSanitizedValue($consumable_id);
        $url_encoded_consumable_id = urlencode($consumable_id);
        $result = "<div class='consumable-models-cell consumable-models-$sanitized_consumable_id'>";
        $result .= "<div class='consumable-models'>";

        foreach ($printermodels as $printermodel_id => $printermodel_name) {
            $result .= "$printermodel_name <span class='fa fa-times-circle clickable' style='color:red' title='Șterge' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageConsumable.php?id=$url_encoded_consumable_id&operation=remove_printer_model&value=$printermodel_id\", \"Sigur vreți să ștergeți modelul?\", function(message) { $(\".consumable-models-$sanitized_consumable_id\").each(function() { $(this).parent().html(message); }); });'></span><br>";
        }

        $result .= "</div>";
        $result .= "<div class='consumable-model-add-button fa fa-plus' title='" . __('Add') . "' onclick='var reference_element = $(this).parent(); ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageConsumable.php?id=$url_encoded_consumable_id&operation=get_dropdown\", \"\", function(message) { reference_element.find(\".consumable-model-add-$sanitized_consumable_id\").html(message); });'></div>";
        $result .= "<div class='consumable-model-add consumable-model-add-$sanitized_consumable_id'></div>";
        $result .= "</div>";

        if (!$return) {
            echo $result;
        }
        return $result;
    }

}

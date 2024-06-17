<?php

// Imported from iService2, needs refactoring.
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

// Direct access to file
if (strpos($_SERVER['PHP_SELF'], "manageConsumable.php")) {
    include '../inc/includes.php';
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkLoginUser();

$operations = [
    'set_min_stock' => 'set_min_stock',
    'add_printer_model' => 'add_printer_model',
    'remove_printer_model' => 'remove_printer_model',
    'get_dropdown' => 'get_dropdown',
];

$id        = IserviceToolBox::getInputVariable('id');
$value     = IserviceToolBox::getInputVariable('value');
$operation = IserviceToolBox::getInputVariable('operation');

if (!array_key_exists($operation, $operations)) {
    die(sprintf(__("Invalid operation: %s", "iservice"), $operation));
}

switch ($operations[$operation]) {
case 'get_dropdown':
    global $CFG_PLUGIN_ISERVICE;
    $consumable_model  = new PluginIserviceConsumable_Model();
    $consumable_models = $consumable_model->find(['plugin_iservice_consumables_id' => $id]);
    $sanitized_id      = IserviceToolBox::getHtmlSanitizedValue($id);
    $url_encoded_id    = urlencode($id);
    echo Dropdown::show('PrinterModel', ['display' => false, 'used' => array_column($consumable_models, 'printermodels_id')]);
    echo "&nbsp;<input type='button' value='" . __('Add') . "' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageConsumable.php?id=$url_encoded_id&operation=add_printer_model&value=\" + $(this).parent().find(\"[name=printermodels_id]\").val(), \"\", function(message) { $(\".consumable-models-$sanitized_id\").each(function() { $(this).parent().html(message); }); });'/>";
    die;
case 'set_min_stock':
    $consumable = new PluginIserviceConsumable();
    $consumable->getFromDB($id);
    if ($consumable->setMinimumStock($value)) {
        die($value);
    }
    break;
case 'add_printer_model':
    $consumable_model = new PluginIserviceConsumable_Model();
    if ($consumable_model->add(['add' => 'add', '_no_message' => true, 'plugin_iservice_consumables_id' => $id, 'printermodels_id' => $value])) {
        PluginIserviceConsumable_Model::showForConsumable($id);
        die;
    }
    break;
case 'remove_printer_model':
    $consumable_model = new PluginIserviceConsumable_Model();
    $models_to_delete = $consumable_model->find("plugin_iservice_consumables_id = '$id' and printermodels_id = $value");
    $success          = true;
    foreach (array_keys($models_to_delete) as $cm_id) {
        $success &= $consumable_model->delete([$consumable_model->getIndexName() => $cm_id]);
    }

    if ($success) {
        PluginIserviceConsumable_Model::showForConsumable($id);
        die;
    }
    break;
default:
    die(sprintf(__("Operation not implemented: %s", "iservice"), $operations[$operation]));
}

die(sprintf(__("Could not complete operation %s for consumable %d", "iservice"), $operations[$operation], $id));

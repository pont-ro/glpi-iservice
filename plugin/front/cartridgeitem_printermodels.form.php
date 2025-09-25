<?php
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

require "../inc/includes.php";

Session::checkRight("plugin_iservice_printer", ALLSTANDARDRIGHT);

$cartridgeitems_id = (int) IserviceToolBox::getInputVariable('cartridgeitems_id', 0);
if ($cartridgeitems_id <= 0) {
    Session::addMessageAfterRedirect(_t('Invalid cartridge item!'), true, ERROR);
    Html::back();
}

$printermodels = IserviceToolBox::getArrayInputVariable('printermodels', []);
$posted_ids = [];
if (is_array($printermodels)) {
    foreach ($printermodels as $pid) {
        if ((int) $pid > 0) {
            $posted_ids[] = (int) $pid;
        }
    }
}

$cartridgeitemPrintermodel = new CartridgeItem_PrinterModel();
$currentPrinterModelIds = array_column($cartridgeitemPrintermodel->find(['cartridgeitems_id' =>  $cartridgeitems_id]), 'printermodels_id');
$toAdd    = array_values(array_diff($posted_ids, $currentPrinterModelIds));
$toRemove = array_values(array_diff($currentPrinterModelIds, $posted_ids));

global $DB, $CFG_GLPI;
$errors = false;

if (!empty($toRemove)) {
    $sql = "DELETE FROM glpi_cartridgeitems_printermodels WHERE cartridgeitems_id = $cartridgeitems_id AND printermodels_id IN (" . implode(',', $toRemove) . ")";
    if (PluginIserviceDB::getQueryResult($sql) === false) {
        $errors = true;
    }
}

if (!empty($toAdd)) {
    $sql = "INSERT INTO glpi_cartridgeitems_printermodels (cartridgeitems_id, printermodels_id) VALUES ";
    $values = [];
    foreach ($toAdd as $pid) {
        $pid  = (int) $pid;
        $values[]  = "($cartridgeitems_id, $pid)";
    }
    if (PluginIserviceDB::getQueryResult($sql . implode(',', $values)) === false) {
        $errors = true;
    }
}

if ($errors) {
    Session::addMessageAfterRedirect(_t('An error occurred while saving associations.'), true, ERROR);
} else {
    Session::addMessageAfterRedirect(_t('Saved successfully.'));
}

Html::back();
//Html::redirect($CFG_GLPI['root_doc'] . '/front/cartridgeitem.form.php?id=' . $cartridgeitems_id);

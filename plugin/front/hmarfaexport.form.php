<?php

// Imported from iService2, needs refactoring. Original file: "hmarfaexport.form.php".
require "../inc/includes.php";

Session::checkRight('plugin_iservice_hmarfa', READ);

$id   = filter_input(INPUT_GET, 'id');
$mode = filter_input(INPUT_GET, 'mode');
if (empty($mode)) {
    $mode = PluginIserviceHmarfa::EXPORT_MODE_PRINTER;
}

$self = filter_input(INPUT_SERVER, 'PHP_SELF');

Html::header(
    __("hMarfa export", "iservice"),
    $_SERVER['PHP_SELF']
);

if (empty($id)) {
    switch ($mode) {
    case PluginIserviceHmarfa::EXPORT_MODE_MASS_INVOICE:
        break;
    case PluginIserviceHmarfa::EXPORT_MODE_PRINTER:
        Html::displayErrorAndDie(__('Printer Id is missing!'));
    case PluginIserviceHmarfa::EXPORT_MODE_TICKET:
        Html::displayErrorAndDie(__('Ticket Id is missing!'));
    default:
        Html::displayErrorAndDie(__('Something is wrong!'));
    }
}

PluginIserviceHmarfa::showExportForm($id, $mode);

Html::footer();

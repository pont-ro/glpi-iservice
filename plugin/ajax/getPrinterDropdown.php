<?php

// Imported from iService2, needs refactoring. Original file: "getPrinterDropdown.php".
// Direct access to file
if (strpos($_SERVER['PHP_SELF'], "getPrinterDropdown.php")) {
    include '../inc/includes.php';
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkLoginUser();

global $CFG_PLUGIN_ISERVICE;

$cartridge_id = filter_input(INPUT_GET, 'cartridge_id');
$cartridge    = new PluginIserviceCartridge();
if (!$cartridge->getFromDB($cartridge_id)) {
    echo "<span style='color:red'>IntError: Not found cartridge # $cartridge_id</span>";
    exit;
}

$input_id = PluginIserviceCartridgeItem::dropdownPrintersForCartridge($cartridge);

// $link = "$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageCartridge.php?id=$cartridge_id&operation=install_on_printer&printer_id=";
// $onclick = "ajaxCall(\"$link\" + \$(\"#dropdown_printers_id$input_id\").val(), \"\", function(message) {if(message !== \"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {\$(\"form\").submit();}})";
// echo " <img src='$CFG_PLUGIN_ISERVICE[root_doc]/pics/app_go_green.png' style='cursor: pointer;' title='" . __('Save') . "' onclick='$onclick'/>";
$cartridgeitem_id = $cartridge->fields['cartridgeitems_id'];
if (!empty($cartridge->fields['locations_id_field'])) {
    $cartridgeitem_id .= "l" . $cartridge->fields['locations_id_field'];
}

$href    = PluginIserviceTicket::getFormModeUrl(PluginIserviceTicket::MODE_READCOUNTER) . "&_cartridgeitem_id=$cartridgeitem_id&_cartridge_id=$cartridge_id&items_id[Printer][0]=";
$onclick = "if (\$(\"#dropdown_plugin_iservice_printers_id$input_id\").val() == 0) {alert(\"Selectați un aparat\");return false;} $(this).attr(\"href\", $(this).attr(\"href\") + \$(\"#dropdown_plugin_iservice_printers_id$input_id\").val());";
echo " <a href='$href' onclick='$onclick'><img src='$CFG_PLUGIN_ISERVICE[root_doc]/pics/app_go_green.png'></a>";

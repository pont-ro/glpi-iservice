<?php

// Imported from iService2, needs refactoring. Original file: "getLocationDropdown.php".
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

// Direct access to file
if (strpos($_SERVER['PHP_SELF'], "getLocationDropdown.php")) {
    include '../../../inc/includes.php';
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkLoginUser();

global $CFG_GLPI;

$supplier_id  = filter_input(INPUT_GET, 'supplier_id');
$cartridge_id = filter_input(INPUT_GET, 'cartridge_id');
$location_id  = filter_input(INPUT_GET, 'location_id');

echo "<table class='tab_cadre no-hover'>";
echo "<tr><td style='border:0;'>";
echo "Schimbă locația";
echo "</td><td style='border:0;vertical-align:top;text-align:right;'>";
echo "<a href='#' onclick='$(\"#popup_{$cartridge_id}_\").html(\"\");return false;'><img src='$CFG_GLPI[root_doc]/pics/delete.png' style='width:inherit'></a>";
echo "</td></tr>";
echo "<tr><td style='border:0;width:100%;vertical-align:top;'>";
// echo "<span class='dropdown_wrapper'>";
/**
$supplier_input_id = Dropdown::show('Supplier', [
    'width' => '100%',
    'comments' => false,
    'value' => $supplier_id,
    'on_change' => "ajaxCall(\"$CFG_GLPI[root_doc]/plugins/iservice/ajax/getLocationDropdown.php?cartridge_id=$cartridge_id&location_id=0&supplier_id=\" + $(this).val(), \"\", function(message) {\$(\"#popup_{$cartridge_id}_\").html(message);});",
]);
/**/
echo "</span>";
// echo "</span></span><br><span class='dropdown_wrapper'>";
$location_condition_select = "
    SELECT distinct(p.locations_id)
    FROM glpi_infocoms ic 
    JOIN glpi_printers p ON p.id = ic.items_id AND itemtype = 'Printer'  AND p.is_deleted = 0
    WHERE ic.suppliers_id = $supplier_id";
$location_input_id         = Dropdown::show(
    'Location', [
        'width' => '100%',
        'comments' => false,
        'value' => $location_id,
        'condition' => ["glpi_locations.id IN ($location_condition_select)"],
    ]
);

echo "</span></td><td style='border:0;padding:0;'>";
$link            = "$CFG_GLPI[root_doc]/plugins/iservice/ajax/manageCartridge.php?id=$cartridge_id&operation=change_location&location_id=";
$ajax_call_param = "\"$link\" + \$(\"#dropdown_locations_id$location_input_id\").val() + \"&supplier_id=$supplier_id\"";
$onclick         = "ajaxCall($ajax_call_param, \"\", function(message) {if(message !== \"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {\$(\"form\").submit();}})";
echo "<img src='$CFG_GLPI[root_doc]/plugins/iservice/pics/app_go_green.png' style='cursor: pointer;' title='" . __('Save') . "' onclick='$onclick'/>";
echo "</td></tr></table>";

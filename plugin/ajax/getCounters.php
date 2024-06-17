<?php

// Imported from iService2, needs refactoring. Original file: "getCounters.php".
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

// Direct access to file
if (strpos($_SERVER['PHP_SELF'], "getCounters.php")) {
    include '../inc/includes.php';
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkLoginUser();

global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;

$cartridge_id    = filter_input(INPUT_GET, 'cartridge_id');
$pages_use       = filter_input(INPUT_GET, 'pages_use_field');
$pages_color_use = filter_input(INPUT_GET, 'pages_color_use_field');

echo "<table class='no-border'><tr><td style='text-align:right;'>";
$date_field_id = Html::showDateField('date', ['value' => date('Y-m-d')]);
echo "<br>Contor black: <input type='text' id='counter_black_$cartridge_id' name='counter_black_$cartridge_id' value='$pages_use' style='width:4em;'>";
echo "<br>Contor color: <input type='text' id='counter_color_$cartridge_id' name='counter_color_$cartridge_id' value='$pages_color_use' style='width:4em;'>";
echo "</td><td>";
$success_function = 'function(message) {if(message !== "' . IserviceToolBox::RESPONSE_OK . '") {alert(message);} else {alert(message);$("form").submit();}}';
$gather_data      = "counter_black=$(\"#counter_black_$cartridge_id\").val();counter_color=$(\"#counter_color_$cartridge_id\").val();install_date=$(\"#hiddendate$date_field_id\").val()";
$ajax_call        = "ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageCartridge.php?id=$cartridge_id&operation=use";
$ajax_call       .= "&counter_black=\" + counter_black + \"";
$ajax_call       .= "&counter_color=\" + counter_color + \"";
$ajax_call       .= "&install_date=\" + install_date";
$ajax_call       .= ", \"\", $success_function)";
echo " <a href='#'><img src='$CFG_PLUGIN_ISERVICE[root_doc]/pics/app_go_green.png' title='" . __('Uninstall from printer', 'iservice') . "' onclick='$gather_data;$ajax_call;return false;'/></a><br>";
echo " <a href='#' onclick='$(\"#ajax_selector_$cartridge_id\").html(\"\");' title='Renunță'><img src='$CFG_GLPI[root_doc]/pics/delete.png' style='width:inherit'></a>&nbsp;";
echo "</td></tr></table>";

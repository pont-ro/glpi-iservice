<?php
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

// Imported from iService2, needs refactoring. Original file: "getPrinterCartidges.php".
// Direct access to file
if (strpos($_SERVER['PHP_SELF'], "getPrinterCartridgesPopup.php")) {
    include '../inc/includes.php';
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkLoginUser();

global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;

$uid = IserviceToolBox::getInputVariable('uid');
if (empty($uid)) {
    $uid = IserviceToolBox::getInputVariable('ticket_id');
}

$printer_id    = IserviceToolBox::getInputVariable('printer_id');
$supplier_id   = IserviceToolBox::getInputVariable('supplier_id');
$supplier_name = IserviceToolBox::getInputVariable('supplier_name');

$ticket                                   = new PluginIserviceTicket();
$ticket->fields['items_id']['Printer'][0] = $printer_id;
$ticket->fields['_suppliers_id_assign']   = $supplier_id;



echo "<table class='tab_cadre no-hover'><tr><td>";

echo '<ul>';
$cartridges = PluginIserviceCartridgeItem::getChangeablesForTicket($ticket);
if (count($cartridges) > 0) {
    foreach ($cartridges as $cartridge) {
        $cartridge_name = $cartridge["name"];
        if (!empty($cartridge['location_completename'])) {
            $cartridge_name .= " din locația $cartridge[location_completename]";
        }

        echo sprintf("<li>%s (%s)</li>", $cartridge_name, $cartridge['cpt']);
    }
} else {
    echo _t('You have no compatible cartridges!');
}

echo '</ul>';

echo "</td><td style='vertical-align:top'>";

echo "<a href='#' onclick='$(\"form#cartriges_link_{$printer_id}_$uid\").submit();return false;'>";
echo "<img src='$CFG_PLUGIN_ISERVICE[root_doc]/pics/toolbox.png' alt='Cartușe' title='Vizualizează carușele partenerului'>";
echo "</a> ";

echo "</td><td style='vertical-align:top'>";

echo "<a href='#' onclick='$(\"#cartriges_link_{$printer_id}_$uid\").remove();$(\"#popup_{$printer_id}_$uid\").html(\"\");return false;'><i class='ti ti-x' style='width:inherit'></i></a>";

echo "</td></tr></table>";

$link  = "views.php?view=Cartridges&cartridges0[partner_name]=$supplier_name&cartridges0[filter_description]=$supplier_name";
$form  = "<form id='cartriges_link_{$printer_id}_$uid' action='$link' method='post'>";
$form .= "<input type='hidden' name='cartridges0[date_use]' value='1980-01-01'/>";
$form .= "<input type='hidden' name='cartridges0[date_out]' value='1980-01-01'/>";
$form .= "<input type='hidden' name='cartridges0[date_use_null]' value='1'/>";
$form .= "<input type='hidden' name='cartridges0[date_out_null]' value='1'/>";
$form .= "<input type='hidden' name='cartridges0[order_by]' value='date_in'/>";
$form .= "<input type='hidden' name='cartridges0[order_dir]' value='ASC'/>";
$form .= "<input type='hidden' name='_glpi_csrf_token' value='" . Session::getNewCSRFToken() . "'/>";
$form .= "</form>";
echo "\n<script>\n$('#page').append(\"$form\");\n</script>\n";

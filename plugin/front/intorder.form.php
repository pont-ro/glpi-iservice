<?php

// Imported from iService2, needs refactoring.
use Glpi\Event;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

require "../inc/includes.php";

Session::checkRight("plugin_iservice_intorder", READ);

$id                        = IserviceToolBox::getInputVariable('id');
$add                       = IserviceToolBox::getInputVariable('add');
$update                    = IserviceToolBox::getInputVariable('update');
$withtemplate              = IserviceToolBox::getInputVariable('withtemplate');
$mass_action_order_again   = IserviceToolBox::getInputVariable('mass_action_order_again');
$mass_action_change_status = IserviceToolBox::getInputVariable('mass_action_change_status');

$post = filter_var_array($_POST);

$intOrder = new PluginIserviceIntOrder();
global $CFG_PLUGIN_ISERVICE;

if (!empty($mass_action_change_status)) {
    $items = IserviceToolBox::getArrayInputVariable('item', []);
    if (!isset($items['intorder']) || !is_array($items['intorder'])) {
        Session::addMessageAfterRedirect("Invalid massive action call", false, ERROR);
        Html::back();
    }

    if (!isset($post['intorder']['new_status']) || $post['intorder']['new_status'] < 1) {
        Session::addMessageAfterRedirect("Invalid new status", false, ERROR);
        Html::back();
    }

    $success = 0;
    $fail    = 0;
    foreach (array_keys($items['intorder']) as $intorder_id) {
        $input = [
            'id' => $intorder_id,
            'plugin_iservice_orderstatuses_id' => $post['intorder']['new_status'],
        ];
        if ($intOrder->update($input)) {
            $success++;
        } else {
            $fail++;
        }
    }

    Session::addMessageAfterRedirect("Starea a $success comenzi a fost schimbată." . ($fail > 0 ? " Eroare la schimbarea stării a $fail comenzi" : ""));
    Html::back();
} elseif (!empty($mass_action_order_again)) {
    $items = IserviceToolBox::getArrayInputVariable('item', []);
    if (!isset($items['intorder']) || !is_array($items['intorder'])) {
        Session::addMessageAfterRedirect("Invalid massive action call", false, ERROR);
        Html::back();
    }

    $success = 0;
    $fail    = 0;
    foreach (array_keys($items['intorder']) as $intorder_id) {
        $intOrder->getFromDB($intorder_id);
        $input = [
            'add' => 'add',
            '_no_message' => true,
            'plugin_iservice_consumables_id' => $intOrder->fields['plugin_iservice_consumables_id'],
            'amount' => $intOrder->fields['amount'],
            'users_id' => $_SESSION['glpiID'],
            'deadline' => date("Y-m-d", strtotime("+7 days")),
            'plugin_iservice_orderstatuses_id' => PluginIserviceOrderStatus::getIdStarted(),
            'content' => 'Comandă reînnoită automat',
        ];
        if ($intOrder->add($input)) {
            $success++;
        } else {
            $fail++;
        }
    }

    Session::addMessageAfterRedirect("$success piese/consumabile comandate din nou." . ($fail > 0 ? " Eroare la comandarea a $fail piese/consumabile" : ""));
    Html::back();
} elseif (!empty($add)) {
    $intOrder->check(-1, CREATE, $post);

    if (($newID = $intOrder->add($post)) !== false) {
        Event::log($newID, "intorders", 4, "plugin_iservice_order", sprintf(__('%1$s adds the item %2$s'), $_SESSION["glpiname"], $post["name"]));
        Html::redirect("$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Intorders");
    }

    Html::back();
} elseif (!empty($update)) {
    $intOrder->check($id, UPDATE);

    $intOrder->update($post);
    Event::log(
        $id, "intorders", 4, "plugin_iservice_order",
        // TRANS: %s is the user login
        sprintf(__('%s updates an item'), $_SESSION["glpiname"])
    );
    Html::redirect("$CFG_PLUGIN_ISERVICE[root_doc]/front/intorder.form.php?id=$id");
} else {
    Html::header(PluginIserviceIntOrder::getTypeName(1), $_SERVER['PHP_SELF']);
    $intOrder->display(
        [
            'id' => $id,
            'withtemplate' => $withtemplate,
        ]
    );
    Html::footer();
}

<?php

// Imported from iService2, needs refactoring.
use Glpi\Event;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

require "../inc/includes.php";

Session::checkRight("plugin_iservice_extorder", READ);

$id                          = IserviceToolBox::getInputVariable('id');
$add                         = IserviceToolBox::getInputVariable('add');
$update                      = IserviceToolBox::getInputVariable('update');
$intorders                   = explode(',', IserviceToolBox::getInputVariable('intorders', ''));
$add_intorder                = IserviceToolBox::getInputVariable('add_intorder');
$withtemplate                = IserviceToolBox::getInputVariable('withtemplate');
$remove_intorder             = IserviceToolBox::getInputVariable('remove_intorder');
$mass_action_create_extorder = IserviceToolBox::getInputVariable('mass_action_create_extorder');
$mass_action_change_status   = IserviceToolBox::getInputVariable('mass_action_change_status');

$post = filter_input_array(INPUT_POST);

$extOrder = new PluginIserviceExtOrder();

if (!empty($mass_action_change_status)) {
    $items = IserviceToolBox::getArrayInputVariable('item', []);
    if (!isset($items['extorder']) || !is_array($items['extorder'])) {
        Session::addMessageAfterRedirect("Invalid massive action call", false, ERROR);
        Html::back();
    }

    if (!isset($post['extorder']['new_status']) || $post['extorder']['new_status'] < 1) {
        Session::addMessageAfterRedirect("Invalid new status", false, ERROR);
        Html::back();
    }

    $success = 0;
    $fail    = 0;
    foreach (array_keys($items['extorder']) as $extorder_id) {
        $input = [
            'id' => $extorder_id,
            'plugin_iservice_orderstatuses_id' => $post['extorder']['new_status'],
        ];
        if ($extOrder->update($input)) {
            $success++;
        } else {
            $fail++;
        }
    }

    Session::addMessageAfterRedirect("Starea a $success comenzi externe a fost schimbată." . ($fail > 0 ? " Eroare la schimbarea stării a $fail comenzi" : ""));
    Html::back();
} elseif (!empty($mass_action_create_extorder)) {
    $items = IserviceToolBox::getArrayInputVariable('item', []);
    if (!isset($items['intorder']) || !is_array($items['intorder'])) {
        Session::addMessageAfterRedirect("Invalid massive action call", false, ERROR);
        Html::back();
    }

    $input = [
        'add' => 'add',
        '_no_message' => true,
        'users_id' => $_SESSION['glpiID'],
        'plugin_iservice_orderstatuses_id' => PluginIserviceOrderStatus::getIdProcessed(),
    ];
    if (($newID = $extOrder->add($input)) === false) {
        Session::addMessageAfterRedirect('Eroare la crearea comenzii externe');
        Html::back();
    }

    $fail              = 0;
    $cannot            = 0;
    $success           = 0;
    $intOrder          = new PluginIserviceIntOrder();
    $intorder_extorder = new PluginIserviceIntOrder_ExtOrder();
    foreach (array_keys($items['intorder']) as $intorder_id) {
        $intOrder->getFromDB($intorder_id);
        if (!PluginIserviceIntOrder_ExtOrder::canHaveIntOrder($intOrder)) {
            $cannot++;
            continue;
        }

        $input = [
            'add' => 'add',
            '_no_message' => true,
            'plugin_iservice_intorders_id' => $intorder_id,
            'plugin_iservice_extorders_id' => $newID,
        ];
        if ($intorder_extorder->add($input)) {
            $success++;
        } else {
            $fail++;
        }
    }

    if ($cannot > 0) {
        Session::addMessageAfterRedirect("$cannot comenzi interne nu au putut fi adăugate.", false, ERROR);
    }

    if ($fail > 0) {
        Session::addMessageAfterRedirect("Eroare la adăugarea a $fail comenzi interne.", false, ERROR);
    }

    Html::redirect($extOrder->getFormURL() . "?id=" . $newID);
} elseif (!empty($add)) {
    $extOrder->check(-1, CREATE, $post);

    if (($newID = $extOrder->add($post)) !== false) {
        Event::log($newID, "extorders", 4, "plugin_iservice_order", sprintf(__('%1$s adds the item %2$s'), $_SESSION["glpiname"], $post["name"]));
        Html::redirect($extOrder->getFormURL() . "?id=" . $newID);
    }

    Html::back();
} elseif ($id > 0 && (!empty($update) || !empty($add_intorder) || !empty($remove_intorder))) {
    $extOrder->check($id, UPDATE);

    $extOrder->update($post);
    Event::log(
        $id, "extorders", 4, "plugin_iservice_order",
        // TRANS: %s is the user login
                    sprintf(__('%s updates an item'), $_SESSION["glpiname"])
    );

    if (!empty($add_intorder)) {
        if (!empty($post['_plugin_iservice_intorder']) && !empty($post['_plugin_iservice_intorder']['plugin_iservice_intorders_id'])) {
            $plugin_iservice_intorders_extorders_data                                 = $post['_plugin_iservice_intorder'];
            $plugin_iservice_intorders_extorders_data['plugin_iservice_extorders_id'] = $id;

            $plugin_iservice_intorders_extorders = new PluginIserviceIntOrder_ExtOrder();
            $plugin_iservice_intorders_extorders->add($plugin_iservice_intorders_extorders_data);
        } else {
            Session::addMessageAfterRedirect('Selectați un consumabil / o piesă', false, ERROR);
        }
    } elseif (!empty($remove_intorder)) {
        $plugin_iservice_intorders_extorders = new PluginIserviceIntOrder_ExtOrder();
        foreach (array_keys($post['_plugin_iservice_intorders_extorders']) as $id_to_delete) {
            $plugin_iservice_intorders_extorders->delete(['id' => $id_to_delete]);
        }
    }

    Html::back();
} else {
    Html::header(PluginIserviceExtOrder::getTypeName(1), $_SERVER['PHP_SELF']);
    $extOrder->display(
        [
            'id' => $id,
            'withtemplate' => $withtemplate,
            'intorders' => $intorders,
        ]
    );
    Html::footer();
}

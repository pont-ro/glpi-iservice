<?php

// Imported from iService2, needs refactoring.
use Glpi\Event;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

require "../inc/includes.php";

Session::checkRight("plugin_iservice_contract", READ);

$contract_id     = IserviceToolBox::getInputVariable('contract_id');
$add_contract    = IserviceToolBox::getInputVariable('add_contract');
$modify_contract = IserviceToolBox::getInputVariable('modify_contract');
$update_contract = IserviceToolBox::getInputVariable('update');

global $DB;

$post         = filter_input_array(INPUT_POST);
$special_keys = ['contract', '_customfields'];
foreach ($special_keys as $special_key) {
    if ($special_key[0] === '_') {
        continue;
    }

    if (isset($post[$special_key]) && is_array($post[$special_key])) {
        $post_data[$special_key] = $post[$special_key];
        foreach ($post as $key => $value) {
            if (!in_array($key, $special_keys)) {
                $post_data[$special_key][$key] = $value;
            }
        }
    } else {
        $post_data[$special_key] = null;
    }
}

$contract = new  PluginIserviceContract();

if (!empty($add_contract)) {
    if (empty($post_data['contract']['name'])) {
        Session::addMessageAfterRedirect('Introduceți un nume', true, ERROR);
    } else {
        $contract->check(-1, CREATE, $post_data['contract']);
        $fields_to_check = [
            'name' => 'numele',
            'num' => 'numărul',
        ];
        foreach ($fields_to_check as $field_name_to_check => $field_to_check_label) {
            if (!empty($post_data['contract'][$field_name_to_check]) && PluginIserviceDB::populateByQuery($contract, "WHERE $field_name_to_check = '{$post_data['contract'][$field_name_to_check]}' LIMIT 1")) {
                Session::addMessageAfterRedirect("Contractul cu $field_to_check_label {$post_data['contract'][$field_name_to_check]} există deja!", true, ERROR);
                $error = true;
            }
        }

        if (empty($error)) {
            unset($post_data['contract']['id']);
            if (!isset($post_data['contract']['is_active'])) {
                $post_data['contract']['is_active'] = 1;
            }

            if (($contract_id = $contract->add($post_data['contract'])) != false) {
                $contract_customfields = new PluginFieldsContractcontractcustomfield();
                if (!PluginIserviceDB::populateByItemsId($contract_customfields, $contract_id)) {
                    $post['_customfields']['contract']['add']      = 'add';
                    $post['_customfields']['contract']['items_id'] = $contract_id;
                    if ($contract_customfields->add($post['_customfields']['contract'])) {
                        Session::addMessageAfterRedirect('Contract adăugat cu succes', true, INFO, true);
                        Html::redirect($contract->getFormURL() . "?contract_id=$contract_id");
                    } else {
                        Session::addMessageAfterRedirect('Eroare la salvarea customfields contract', true, ERROR);
                    }
                } else {
                    $post['_customfields']['contract'][$contract_customfields->getIndexName()] = $contract_customfields->getId();
                    if ($contract_customfields->update($post['_customfields']['contract'])) {
                        Session::addMessageAfterRedirect('Contract adăugat cu succes', true, INFO, true);
                        Html::redirect($contract->getFormURL() . "?contract_id=$contract_id");
                    } else {
                        Session::addMessageAfterRedirect('Eroare la salvarea customfields contract', true, ERROR);
                    }
                }
            } else {
                Session::addMessageAfterRedirect('Eroare la crearea contractului', true, ERROR);
            }
        }
    }
} elseif (!empty($update_contract) && $post_data['contract'] !== null) {
    $contract->check($post_data['contract']['id'], UPDATE);
    $contract->update($post_data['contract']);
    $contract_customfields = new PluginFieldsContractcontractcustomfield();
    PluginIserviceDB::populateByItemsId($contract_customfields, $post_data['contract']['id']);
    $post['_customfields']['contract'][$contract_customfields->getIndexName()] = $contract_customfields->getID();
    $contract_customfields->update($post['_customfields']['contract']);
    Event::log($contract_id, "contracts", 4, "inventory", sprintf(__('%s updates an item'), $_SESSION["glpiname"]));
    Html::back();
}

Html::header(PluginIservicePrinter::getTypeName(Session::getPluralNumber()), $_SERVER['PHP_SELF'], null, null);
$contract->showForm($contract_id);
Html::footer();

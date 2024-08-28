<?php
// Import from iService2, needs refactoring.
use Glpi\Event;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

require "../inc/includes.php";

Session::checkRight("plugin_iservice_printer", READ);

$error           = null;
$popup           = null;
$id              = IserviceToolBox::getInputVariable('id');
$partner_id      = IserviceToolBox::getInputVariable('supplier_id');
$contract_id     = IserviceToolBox::getInputVariable('contract_id');
$add             = IserviceToolBox::getInputVariable('add');
$update          = IserviceToolBox::getInputVariable('update');
$add_supplier    = IserviceToolBox::getInputVariable('add_supplier');
$modify_supplier = IserviceToolBox::getInputVariable('modify_supplier');
$update_supplier = IserviceToolBox::getInputVariable('update_supplier');
$add_contract    = IserviceToolBox::getInputVariable('add_contract');
$modify_contract = IserviceToolBox::getInputVariable('modify_contract');
$update_contract = IserviceToolBox::getInputVariable('update_contract');

global $DB;

$post         = filter_var_array($_POST);
$special_keys = ['printer', 'supplier', 'contract', '_customfields'];
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

$printer               = new Printer();
$supplier              = new Supplier();
$contract              = new Contract();
$iservice_printer      = new PluginIservicePrinter();
$printer_customfields  = new PluginFieldsPrinterprintercustomfield();
$supplier_customfields = new PluginFieldsSuppliersuppliercustomfield();
$contract_customfields = new PluginFieldsContractcontractcustomfield();

if (!empty($add) && $post_data['printer'] !== null && !empty($post_data['printer']['name'])) {
    $printer->check(-1, CREATE, $post_data['printer']);
    $input = array_merge($post_data['printer'] ?? [], $post['_customfields']['printer'] ?? []);
    IserviceToolBox::preprocessInputValuesForCustomFields(get_Class($printer), $input);

    if (($id = $printer->add($input)) !== false) {
        if (!PluginIserviceDB::populateByItemsId($printer_customfields, $id)) {
            $input['add']      = 'add';
            $input['items_id'] = $id;
            $printer_customfields->add($input);
        } elseif (empty($printer->plugin_fields_data)) {
            $input[$printer_customfields->getIndexName()] = $printer_customfields->getID();
            $printer_customfields->update($input);
        }

        Event::log($id, "printers", 4, "inventory", sprintf(__('%1$s adds the item %2$s'), $_SESSION["glpiname"], $post_data['printer']["name"]));
        Html::redirect($iservice_printer->getFormURL() . "?id=$id");
    }
} elseif (!empty($update) && $post_data['printer'] !== null && !empty($post_data['printer']['name'])) {
    $printer->check($id, UPDATE);
    IserviceToolBox::preprocessInputValuesForCustomFields(get_Class($printer), $post_data['printer']);
    $printer->update($post_data['printer']);
    PluginIserviceDB::populateByItemsId($printer_customfields, $id);
    $post['_customfields']['printer'][$printer_customfields->getIndexName()] = $printer_customfields->getID();
    $printer_customfields->update($post['_customfields']['printer']);
    Event::log($id, "printers", 4, "inventory",    sprintf(__('%s updates an item'), $_SESSION["glpiname"]));
} elseif (!empty($add_supplier)) {
    if (empty($post_data['supplier']['name'])) {
        Session::addMessageAfterRedirect('Introduceți un nume', true, ERROR);
    } else {
        $supplier->check(-1, CREATE, $post_data['supplier']);
        if (PluginIserviceDB::populateByQuery($supplier, "WHERE name = '{$post_data['supplier']['name']}' LIMIT 1")) {
            Session::addMessageAfterRedirect("Partenerul cu numele {$post_data['supplier']['name']} există deja!", true, ERROR);
        } else {
            $fields_to_check = [
                'uic_field' => 'Cod Fiscal',
                'crn_field' => 'Număr Registru Comerț',
                'hmarfa_code_field' => 'Cod Partener hMarfa',
            ];
            foreach ($fields_to_check as $field_name_to_check => $field_to_check_label) {
                if (!empty($post['_customfields']['supplier'][$field_name_to_check]) && PluginIserviceDB::populateByQuery($supplier_customfields, "WHERE $field_name_to_check = '{$post['_customfields']['supplier'][$field_name_to_check]}' LIMIT 1")) {
                    Session::addMessageAfterRedirect("Partenerul cu $field_to_check_label {$post['_customfields']['supplier'][$field_name_to_check]} există deja!", true, ERROR);
                    $error = true;
                }
            }

            if (!$error) {
                unset($post_data['supplier']['id']);
                if (!isset($post_data['supplier']['is_active'])) {
                    $post_data['supplier']['is_active'] = 1;
                }

                if (!isset($post_data['supplier']['entities_id'])) {
                    $post_data['supplier']['entities_id'] = 0;
                }

                if (($partner_id = $supplier->add($post_data['supplier'])) != false) {
                    if (!PluginIserviceDB::populateByItemsId($supplier_customfields, $partner_id)) {
                        $post['_customfields']['supplier']['add']      = 'add';
                        $post['_customfields']['supplier']['items_id'] = $partner_id;
                        if ($supplier_customfields->add($post['_customfields']['supplier'])) {
                            Session::addMessageAfterRedirect('Partener adăugat cu succes');
                            Html::redirect($iservice_printer->getFormURL() . "?supplier_id=$partner_id");
                        } else {
                            Session::addMessageAfterRedirect('Eroare la salvarea customfields', true, ERROR);
                        }
                    } else {
                        $post['_customfields']['supplier'][$supplier_customfields->getIndexName()] = $supplier_customfields->getID();
                        if ($supplier_customfields->update($post['_customfields']['supplier'])) {
                            Session::addMessageAfterRedirect('Partener adăugat cu succes');
                            Html::redirect($iservice_printer->getFormURL() . "?supplier_id=$partner_id");
                        } else {
                            Session::addMessageAfterRedirect('Eroare la salvarea customfields', true, ERROR);
                        }
                    }
                } else {
                    Session::addMessageAfterRedirect('Eroare la crearea partenerului', true, ERROR);
                }
            }
        }
    }
} elseif (!empty($modify_supplier)) {
    $printer->check($id, UPDATE);
    $infocom = new Infocom();
    if (!$infocom->getFromDBforDevice('Printer', $id)) {
        $infocom->add(['add' => 'add','itemtype' => 'Printer','items_id' => $id, 'suppliers_id' => $post['supplier']['id']]);
    } elseif (empty($infocom->fields['suppliers_id'])) {
        $infocom->update([$infocom->getIndexName() => $infocom->getID(),'suppliers_id' => $post['supplier']['id']]);
    } else {
        $popup = "movement.form.php?itemtype=Printer&items_id=$id&suppliers_id={$post['supplier']['id']}";
        Html::redirect($popup);
    }
} else if (!empty($update_supplier) && $post_data['supplier'] !== null && !empty($post_data['supplier']['name'])) {
    $supplier->check($post_data['supplier']['id'], UPDATE);
    $supplier->update($post_data['supplier']);
    PluginIserviceDB::populateByItemsId($supplier_customfields, $post_data['supplier']['id']);
    $post['_customfields']['supplier'][$supplier_customfields->getIndexName()] = $supplier_customfields->getID();
    $supplier_customfields->update($post['_customfields']['supplier']);
    Event::log($id, "suppliers", 4, "inventory", sprintf(__('%s updates an item'), $_SESSION["glpiname"]));
} elseif (!empty($add_contract)) {
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

        if (!$error) {
            unset($post_data['contract']['id']);
            if (!isset($post_data['contract']['is_active'])) {
                $post_data['contract']['is_active'] = 1;
            }

            if (($contract_id = $contract->add($post_data['contract'])) != false) {
                if (!PluginIserviceDB::populateByItemsId($contract_customfields, $contract_id)) {
                    $post['_customfields']['contract']['add']      = 'add';
                    $post['_customfields']['contract']['items_id'] = $contract_id;
                    $contract_customfields->add($post['_customfields']['contract']);
                } else {
                    $post['_customfields']['contract'][$contract_customfields->getIndexName()] = $contract_customfields->getId();
                    if ($contract_customfields->update($post['_customfields']['contract'])) {
                        Session::addMessageAfterRedirect('Contract adăugat cu succes');
                        Html::redirect($iservice_printer->getFormURL() . "?contract_id=$contract_id");
                    } else {
                        Session::addMessageAfterRedirect('Eroare la salvarea customfields contract', true, ERROR);
                    }
                }
            } else {
                Session::addMessageAfterRedirect('Eroare la crearea contractului', true, ERROR);
            }
        }
    }
} elseif (!empty($modify_contract)) {
    $printer->check($id, UPDATE);
    // Toroljuk azokat a szerzodeseket a jelenlegi partnerrol, amelyek a jelenlegi gepet tartamazzak,
    // de csak akkor ha a szerzodes nem tartalmaz ugyanehez a partnerhez tartozo masik gepet.
    $DB->query(
        "DELETE FROM glpi_contracts_suppliers
							WHERE contracts_id IN (
										SELECT ci.contracts_id
										FROM glpi_contracts_items ci
										LEFT JOIN glpi_contracts_suppliers cs ON cs.contracts_id = ci.contracts_id
										WHERE itemtype = 'Printer'
											AND items_id = $id
											AND NOT EXISTS (SELECT * FROM glpi_contracts_items ci2
																			LEFT JOIN glpi_infocoms ic ON ic.itemtype = 'Printer' AND ic.items_id = ci2.items_id
																			WHERE ci.contracts_id = ci2.contracts_id
																				AND NOT ci.items_id = ci2.items_id
																				AND ic.suppliers_id = cs.suppliers_id)
							)"
    );
    // Leszedjuk a gepet a jelenlegi szerzodeserol. Ezen a ponton a szerzodes mar le van szedve a partnerrol ha szukseges.
    $DB->query("DELETE FROM glpi_contracts_items WHERE itemtype = 'Printer' AND items_id = $id");
    if ($post['contract']['id'] > 0) {
        $contract_item = new Contract_Item();
        // $DB->query("DELETE FROM glpi_contracts_items where itemtype = 'Printer' AND contracts_id = " . $post['contract']['id']);
        $contract_item->add(['add' => 'add','itemtype' => 'Printer','items_id' => $id,'contracts_id' => $post['contract']['id']]);
        $contract_supplier = new Contract_Supplier();
        $contract_supplier->add(['add' => 'add','contracts_id' => $post['contract']['id'],'suppliers_id' => $post['supplier']['id']]);
    }
} elseif (!empty($update_contract) && $post_data['contract'] !== null) {
    $contract->check($post_data['contract']['id'], UPDATE);
    $contract->update($post_data['contract']);
    PluginIserviceDB::populateByItemsId($contract_customfields, $post_data['contract']['id']);
    $post['_customfields']['contract'][$contract_customfields->getIndexName()] = $contract_customfields->getID();
    $contract_customfields->update($post['_customfields']['contract']);
    Event::log($id, "contracts", 4, "inventory", sprintf(__('%s updates an item'), $_SESSION["glpiname"]));
}

Html::header(PluginIservicePrinter::getTypeName(Session::getPluralNumber()), $_SERVER['PHP_SELF'], null, $popup);
$iservice_printer->showForm($id, $partner_id, $contract_id);
Html::footer();

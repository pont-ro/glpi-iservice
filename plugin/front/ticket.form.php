<?php

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

require "../inc/includes.php";

Session::checkLoginUser();
$ticket = new PluginIserviceTicket();

global $CFG_PLUGIN_ISERVICE;
$id     = IserviceToolBox::getInputVariable('id', 0);
$add    = IserviceToolBox::getInputVariable('add');
$update = IserviceToolBox::getInputVariable('update');

if (($addConsumable = IserviceToolBox::getInputVariable('add_consumable'))
    ||($removeConsumable = IserviceToolBox::getInputVariable('remove_consumable'))
    || ($updateConsumable = IserviceToolBox::getInputVariable('update_consumable'))
    || ($addCartridge     = IserviceToolBox::getInputVariable('add_cartridge'))
    || ($removeCartridge  = IserviceToolBox::getInputVariable('remove_cartridge'))
    || ($updateCartridge  = IserviceToolBox::getInputVariable('update_cartridge'))
    || ($export = IserviceToolBox::getInputVariable('export'))
    || ($deleteDocument = IserviceToolBox::getInputVariable('delete_document'))
) {
    $update                      = true;
    $noRedirectAfterTicketUpdate = true;
}

$global_readcounter                     = IserviceToolBox::getInputVariable('global_readcounter');
$add_cartridges_as_negative_consumables = IserviceToolBox::getInputVariable('add_cartridges_as_negative_consumables');

$post                 = filter_input_array(INPUT_POST) ?: [];
$get                  = filter_input_array(INPUT_GET) ?: [];
$input                = array_merge((is_array($get) ? $get : []), (is_array($post) ? $post : []));
$options['partnerId'] = IserviceToolBox::getInputVariable('suppliers_id') ?? IserviceToolBox::getValueFromInput('_suppliers_id_assign', $get);
$options['printerId'] = IserviceToolBox::getInputVariable('printer_id') ?? IserviceToolBox::getItemsIdFromInput($get, 'Printer');
$options['mode']      = IserviceToolBox::getInputVariable('mode');

$errorMessage = '';

if (!empty($input)) {
    $input = PluginIserviceTicket::preProcessPostData($input);
}

if ($id > 0) {
    $header_title = Ticket::getTypeName();
} else {
    $header_title = __('New ticket');
    unset($_REQUEST['id']);
}

if (empty($id) && (!empty($addConsumable) || !empty($addCartridge))) {
    $add = true;
}

if (!empty($add)) {
    $ticket->check(-1, CREATE, $input);
    $input['status']                 = Ticket::INCOMING;
    $input['_do_not_compute_status'] = true; // This is needed to avoid ticket status change in CommonITILObject.php:prepareInputForUpdate method, line 1780.
    $id                              = $ticket->add($input);

    if (empty($id)) {
        Session::addMessageAfterRedirect(__('Could not create ticket!', 'iservice'), true, ERROR);
        Html::back();
    }

    $input         = $ticket->updateEffectiveDate($input);
    $ticket->input = array_merge($ticket->input, $input);
    $ticket->updateItem($id, $input, true);

    if (empty($addConsumable) && empty($addCartridge)) {
        // Redirect only if we are not adding consumables or cartridges.
        Html::redirect($ticket->getFormURL() . '?id=' . $id);
    }

    $delayedRedirect = $ticket->getFormURL() . '?id=' . $id;
} elseif (!empty($update)) {
    $input         = $ticket->updateEffectiveDate($input);
    $ticket->input = array_merge($ticket->input, $input);
    $ticket->updateItem($id, $input);

    if (empty($noRedirectAfterTicketUpdate)) {
        Html::redirect($ticket->getFormURL() . '?id=' . $id);
    }
}

$partnerPrinterIds = [
    'suppliers_id' => IserviceToolBox::getInputVariable('suppliers_id') ?? IserviceToolBox::getValueFromInput('_suppliers_id_assign', $get),
    'printer_id' => IserviceToolBox::getInputVariable('printer_id') ?? IserviceToolBox::getItemsIdFromInput($get, 'Printer'),
];

if (!empty($addConsumable) && !empty($id)) {
    $ticket->update($input);
    $ticket->addConsumable($id, $input);
    Html::redirect($ticket->getFormURL() . '?id=' . $id);
} elseif (!empty($removeConsumable) && !empty($id)) {
    $ticket->removeConsumable($id, $input);
} elseif (!empty($updateConsumable) && !empty($id)) {
    $ticket->updateConsumable($id, $input);
} elseif (!empty($addCartridge) && !empty($id)) {
    $ticket->addCartridge($id, array_merge($input, $partnerPrinterIds), $errorMessage);
    Session::addMessageAfterRedirect($errorMessage, false, ERROR);
} elseif (!empty($removeCartridge) && !empty($id)) {
    $ticket->removeCartridge($id, array_merge($input, $partnerPrinterIds));
} elseif (!empty($updateCartridge) && !empty($id)) {
    $ticket->updateCartridge($id, array_merge($input, $partnerPrinterIds));
} elseif (!empty($export)) {
    Html::redirect($CFG_PLUGIN_ISERVICE['root_doc'] . "/front/hmarfaexport.form.php?id=$id&mode=" . PluginIserviceHmarfa::EXPORT_MODE_TICKET);
} elseif (!empty($add_cartridges_as_negative_consumables)) {
    add_cartridges_as_negative_consumables();
} elseif (!empty($deleteDocument)) {
    $ticket->deleteDocument($input);
} else {
    $options = array_merge($options, $get ?? [], $input ?? [], $partnerPrinterIds);
}

if (!empty($global_readcounter) && ($globalreadcounter0 = IserviceToolBox::getArrayInputVariable('globalreadcounter0', []))) {
    $success = PluginIserviceTicket::createGlobalReadCounterTickets($globalreadcounter0);
}

if (!empty($delayedRedirect)) {
    Html::redirect($delayedRedirect);
}

Html::header($header_title);

if ($global_readcounter) {
    $ticket->displayResult('global_readcounter', $success);
} else {
    $options['getParams'] = $get ?? [];
    Html::requireJs('fileupload');
    $ticket->showForm($id, $options);
}

Html::footer();

function add_cartridges_as_negative_consumables(): void
{
    $track = new PluginIserviceTicket();
    $track->prepareDataForMovement(Html::cleanPostForTextArea(filter_input_array(INPUT_GET)));
    $track->processFieldsByInput();
    $track->fields['add']    = 'add';
    $track->fields['status'] = Ticket::WAITING;
    if (($newTicketId = $track->add($track->fields)) !== false) {
        $printer = new PluginIservicePrinter();
        $printer->getFromDB(filter_input(INPUT_GET, 'items_id') ?: IserviceToolBox::getItemsIdFromInput($track->fields, 'Printer'));
        $plugin_iservice_consumable_ticket = new PluginIserviceConsumable_Ticket();
        $cartridge_counts                  = filter_input(INPUT_GET, 'cartridge-count', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        $cartridgeitem                     = new PluginIserviceCartridgeItem();
        $added_cartridges                  = [];
        foreach ($cartridge_counts as $location_id => $location_data) {
            foreach ($location_data as $cartridge_item_ref => $amount) {
                if (array_key_exists($cartridge_item_ref, $added_cartridges)) {
                    global $DB;
                    $DB->update(
                        'glpi_plugin_fields_cartridgecartridgecustomfields',
                        ['locations_id_field' => empty($added_cartridges[$cartridge_item_ref]['location']) ? '0' : $added_cartridges[$cartridge_item_ref]['location']],
                        ['locations_id_field ' => $location_id, 'printers_id' => 0,  'c.date_use' => null, 'c.date_out' => null, 'suppliers_id_field' => filter_input(INPUT_GET, 'suppliers_id_old')],
                        [
                            'JOIN' => [
                                'glpi_cartridges as c' => [
                                    'ON' => [
                                        'glpi_plugin_fields_cartridgecartridgecustomfields.items_id' => 'glpi_cartridges.id',
                                    ]
                                ],
                            ]
                        ],
                    );
                    $added_cartridges[$cartridge_item_ref]['amount'] -= $amount;
                    $plugin_iservice_consumable_ticket->update(
                        [
                            'id' => $added_cartridges[$cartridge_item_ref]['ct_id'],
                            'amount' => $added_cartridges[$cartridge_item_ref]['amount'],
                        ]
                    );
                } else {
                    $cartridgeitem->getFromDBByRef($cartridge_item_ref);
                    $ct_id                                 = $plugin_iservice_consumable_ticket->add(
                        [
                            'add'                                 => 'add',
                            'tickets_id'                          => $newTicketId,
                            'locations_id'                        => $location_id,
                            'plugin_iservice_consumables_id'      => $cartridge_item_ref,
                            'plugin_fields_cartridgeitemtypedropdowns_id' => $cartridgeitem->getSupportedTypes()[0],
                            'create_cartridge'                    => 1,
                            'amount'                              => -$amount,
                        ], ['printer' => $printer]
                    );
                    $added_cartridges[$cartridge_item_ref] = [
                        'ct_id' => $ct_id,
                        'location' => $location_id,
                        'amount' => -$amount,
                    ];
                }
            }
        }

        Html::redirect($track->getFormURL() . "?_allow_buttons=1&id=$newTicketId");
    }
}

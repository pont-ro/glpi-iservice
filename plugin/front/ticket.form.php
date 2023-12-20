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
) {
    $update                      = true;
    $noRedirectAfterTicketUpdate = true;
}

$global_readcounter = IserviceToolBox::getInputVariable('global_readcounter');

$post                 = filter_input_array(INPUT_POST);
$get                  = filter_input_array(INPUT_GET);
$options['partnerId'] = IserviceToolBox::getInputVariable('suppliers_id') ?? IserviceToolBox::getValueFromInput('_suppliers_id_assign', $get);
$options['printerId'] = IserviceToolBox::getInputVariable('printer_id') ?? IserviceToolBox::getItemsIdFromInput($get, 'Printer');
$options['mode']      = IserviceToolBox::getInputVariable('mode');

$errorMessage = '';

if (!empty($post)) {
    $post = PluginIserviceTicket::preProcessPostData($post);
}

if ($id > 0) {
    $header_title = Ticket::getTypeName();
} else {
    $header_title = __('New ticket');
    unset($_REQUEST['id']);
}

if (!empty($add)) {
    $ticket->check(-1, CREATE, $post);

    $ticketId = $ticket->add($post);

    if (empty($ticketId)) {
        Session::addMessageAfterRedirect(__('Could not create ticket!', 'iservice'), true, ERROR);
        Html::back();
    }

    $ticket->updateItem($ticketId, $post, true);

    Html::redirect($ticket->getFormURL() . '?mode=' . PluginIserviceTicket::MODE_CLOSE . '&id=' . $ticketId);
} elseif (!empty($update)) {
    $ticket->updateItem($id, $post);

    if (empty($noRedirectAfterTicketUpdate)) {
        Html::redirect($ticket->getFormURL() . '?mode=' . PluginIserviceTicket::MODE_CLOSE . '&id=' . $id);
    }
}

$partnerPrinterIds = [
    'suppliers_id' => IserviceToolBox::getInputVariable('suppliers_id'),
    'printer_id' => IserviceToolBox::getInputVariable('printer_id'),
];

if (!empty($addConsumable) && !empty($id)) {
    $ticket->addConsumable($id, $post);
} elseif (!empty($removeConsumable) && !empty($id)) {
    $ticket->removeConsumable($id, $post);
} elseif (!empty($updateConsumable) && !empty($id)) {
    $ticket->updateConsumable($id, $post);
} elseif (!empty($addCartridge) && !empty($id)) {
    $ticket->addCartridge($id, array_merge($post, $partnerPrinterIds), $errorMessage);
    Session::addMessageAfterRedirect($errorMessage, false, ERROR);
} elseif (!empty($removeCartridge) && !empty($id)) {
    $ticket->removeCartridge($id, array_merge($post, $partnerPrinterIds));
} elseif (!empty($updateCartridge) && !empty($id)) {
    $ticket->updateCartridge($id, array_merge($post, $partnerPrinterIds));
} elseif (!empty($export)) {
    Html::redirect($CFG_PLUGIN_ISERVICE['root_doc'] . "/front/hmarfaexport.form.php?id=$id&mode=" . PluginIserviceHmarfa::EXPORT_MODE_TICKET);
}

if (!empty($global_readcounter) && ($globalreadcounter0 = IserviceToolBox::getArrayInputVariable('globalreadcounter0', []))) {
    $success = PluginIserviceTicket::createGlobalReadCounterTickets($globalreadcounter0);
}

Html::header($header_title);

if ($global_readcounter) {
    $ticket->displayResult('global_readcounter', $success);
} else {
    $ticket->showForm($id, $options);
}

Html::footer();

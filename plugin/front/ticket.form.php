<?php

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

require "../inc/includes.php";

Session::checkLoginUser();
$ticket = new PluginIserviceTicket();

$id               = IserviceToolBox::getInputVariable('id', 0);
$add              = IserviceToolBox::getInputVariable('add');
$update           = IserviceToolBox::getInputVariable('update');
$addConsumable    = IserviceToolBox::getInputVariable('add_consumable');
$removeConsumable = IserviceToolBox::getInputVariable('remove_consumable');
$updateConsumable = IserviceToolBox::getInputVariable('update_consumable');
$addCartridge     = IserviceToolBox::getInputVariable('add_cartridge');
$removeCartridge  = IserviceToolBox::getInputVariable('remove_cartridge');
$updateCartridge  = IserviceToolBox::getInputVariable('update_cartridge');

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

    $ticket->updateItem($ticketId, $post);

    Html::redirect($ticket->getFormURL() . '?mode=' . PluginIserviceTicket::MODE_CLOSE . '&id=' . $ticketId);
} elseif (!empty($update)) {
    $ticket->check($id, UPDATE, $post);

    $ticket->update($post);

    $ticket->updateItem($id, $post);

    Html::redirect($ticket->getFormURL() . '?mode=9999&id=' . $id);
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
}

Html::header($header_title);

$ticket->showForm($id, $options);

Html::footer();

<?php

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

require "../inc/includes.php";

Session::checkLoginUser();
$ticket = new PluginIserviceTicket();

$id                   = IserviceToolBox::getInputVariable('id', 0);
$add                  = IserviceToolBox::getInputVariable('add');
$update               = IserviceToolBox::getInputVariable('update');
$post                 = filter_input_array(INPUT_POST);
$options['partnerId'] = IserviceToolBox::getInputVariable('suppliers_id');
$options['printerId'] = IserviceToolBox::getInputVariable('printer_id');
$options['mode']      = IserviceToolBox::getInputVariable('mode');

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

    $post = $ticket->preProcessPostData($post);

    $ticket->update($post);

    $ticket->updateItem($id, $post);

    Html::redirect($ticket->getFormURL() . '?mode=9999&id=' . $id);
}

Html::header($header_title);

$ticket->showForm($id, $options);

Html::footer();

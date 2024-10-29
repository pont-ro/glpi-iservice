<?php

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

// Direct access to file.
if (strpos($_SERVER['PHP_SELF'], "manageTicket.php")) {
    include '../inc/includes.php';
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkLoginUser();

// enumerate the parameter the operation expects
$operations = [
    'clear_export_type' => [],
];

$id = IserviceToolBox::getInputVariable('id');
$ticket = new PluginIserviceTicket();
if (!$ticket->getFromDB($id)) {
    die(sprintf(_t('Invalid ticket id: %d'), $id));
}
$ticket->check($id, UPDATE);

$operation = IserviceToolBox::getInputVariable('operation');
if (!array_key_exists($operation, $operations)) {
    die(sprintf(_t('Invalid operation: %s'), $operation));
}

switch ($operation) {
    case 'clear_export_type':
        if ($ticket->update([
            PluginIserviceTicket::getIndexName() => $id,
            'plugin_fields_ticketexporttypedropdowns_id' => 0,
        ])) {
            die(IserviceToolBox::RESPONSE_OK);
        }
        break;
    default:
        die(sprintf(_t('Operation not implemented: %s'), $operation));
}

die(sprintf(_t('Could not complete operation %s for ticket %d'), $operation, $id));

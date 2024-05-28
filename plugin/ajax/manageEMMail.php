<?php

// Imported from iService2, needs refactoring.
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

// Direct access to file.
if (strpos($_SERVER['PHP_SELF'], "manageEMMail.php")) {
    include '../inc/includes.php';
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkLoginUser();

$operations = [
    'invalidate' => 'invalidate',
    'mark_read' => 'mark_read',
    'mark_unread' => 'mark_unread',
    'toggle_read' => 'toggle_read',
];

$id  = IserviceToolBox::getInputVariable('id');
$eme = new PluginIserviceEMEmail();
if (!$eme->getFromDB($id)) {
    die(sprintf(__("Invalid id: %d", "iservice"), $id));
}

$operation = IserviceToolBox::getInputVariable('operation');
if (!array_key_exists($operation, $operations)) {
    die(sprintf(__("Invalid operation: %s", "iservice"), $operation));
}

switch ($operations[$operation]) {
case 'invalidate':
    $eme->update(['id' => $id, 'suggested' => 'refresh']);
    die(IserviceToolBox::RESPONSE_OK);
case 'mark_read':
    $eme->update(['id' => $id, 'read' => 1]);
    die(IserviceToolBox::RESPONSE_OK);
case 'mark_unread':
    $eme->update(['id' => $id, 'read' => 0]);
    die(IserviceToolBox::RESPONSE_OK);
case 'toggle_read':
default:
    die(sprintf(__("Operation not implemented: %s", "iservice"), $operations[$operation]));
}

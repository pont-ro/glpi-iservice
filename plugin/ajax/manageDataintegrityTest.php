<?php

// Imported from iService2, needs refactoring.
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

// Direct access to file.
if (strpos($_SERVER['PHP_SELF'], "manageDataintegrityTest.php")) {
    include '../inc/includes.php';
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkLoginUser();

$operations = ['snooze', 'unsnooze', 'delete_last_result'];

$operation = IserviceToolBox::getInputVariable('operation');
$id        = IserviceToolBox::getInputVariable('id');
if (!in_array($operation, $operations)) {
    die(sprintf(_t('Invalid operation: %s'), $operation));
}

$dit = new PluginIserviceTask_DataIntegrityTest();
switch ($operation) {
case 'snooze':
    $snooze = IserviceToolBox::getInputVariable('snooze');

    $snooze_data = explode(' ', $snooze, 2);
    if (is_numeric($snooze_data[0])) {
        $snooze_time = intval($snooze_data[0]);
    } else {
        $snooze_time = 1;
    }

    $snooze_unit = count($snooze_data) > 1 ? $snooze_data[1] : $snooze;
    $multiplier  = [
        'seconds' => 1,
        'minutes' => 60,
        'hours'   => 3600,
        'days'    => 86400
    ][$snooze_unit] ?? 0;

    $dit->snoozeTestCase($id, $snooze_time * $multiplier);
    die;
case 'unsnooze':
    $dit->unSnoozeTestCase($id);
    die;
case 'delete_last_result':
    $dit->deleteLastResult($id);
    die;
default:
    die(sprintf(_t('Operation not implemented: %s'), $operations[$operation]));
}

die(sprintf(_t('Could not complete operation %s for item %d'), $operations[$operation], $id));

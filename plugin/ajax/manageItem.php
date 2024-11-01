<?php
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

// Direct access to file.
if (strpos($_SERVER['PHP_SELF'], "manageItem.php")) {
    include '../inc/includes.php';
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkLoginUser();

$operations = [
    'PluginIservicePartner' => [
        'add' => [],
    ]
];

$itemType = IserviceToolBox::getInputVariable('itemtype');
if (!class_exists($itemType)) {
    die(sprintf(_t('Invalid item type: %s'), $itemType));
}

$operation = IserviceToolBox::getInputVariable('operation');
if (!method_exists($itemType, "ajax$operation")) {
    die(sprintf(_t('Invalid operation: %s'), $operation));
}

$method = "ajax$operation";
echo $itemType::$method();


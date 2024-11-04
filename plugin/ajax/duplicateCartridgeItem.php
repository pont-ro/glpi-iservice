<?php

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

// Direct access to file.
if (strpos($_SERVER['PHP_SELF'], "duplicateCartridgeItem.php")) {
    include '../inc/includes.php';
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkLoginUser();

$cartridgeItemId = IserviceToolBox::getInputVariable('cartridgeItemId');
$cartridgeItem   = new PluginIserviceCartridgeItem();
$cartridgeItem->getFromDB($cartridgeItemId);

$data = array_merge($cartridgeItem->fields ?? [], $cartridgeItem->customfields->fields ?? []);
unset($data['id'], $data['date_mod'], $data['date_creation']);

if ($newCartidgeItemId = $cartridgeItem->add($data)) {
    Session::addMessageAfterRedirect(_t('Cartridge item was duplicated!'), true, INFO, true);
    echo json_encode(
        [
            'success' => true,
            'newCartridgeItemId' => $newCartidgeItemId
        ]
    );
} else {
    echo json_encode(
        [
            'success' => false,
        ]
    );
}

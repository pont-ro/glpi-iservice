<?php
require '../inc/includes.php';

// Send UTF8 Headers.
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();
Session::checkLoginUser();

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

$input = IserviceToolBox::getInputVariables(
    [
        'oldDBHost',
        'oldDBName',
        'oldDBUser',
        'oldDBPassword',
        'itemType',
    ]
);

$configFileName = PLUGIN_ISERVICE_DIR . '/config/import/' . strtolower("$input[itemType].php");
if (empty($input['itemType']) || !file_exists($configFileName)) {
    return "Invalid item type: $input[itemType]";
}

$importConfig = include $configFileName;
if (empty($importConfig)) {
    return "Invalid import config for item type $input[itemType], it must return an array";
}

$oldItems = IserviceToolBox::getQueryResult(
    "SELECT * FROM $importConfig[oldTable]",
    'id',
    new PluginIserviceDB($input['oldDBHost'], $input['oldDBName'], $input['oldDBUser'], $input['oldDBPassword'])
);

$itemTypeClass = $importConfig['itemTypeClass'];
$item          = new $itemTypeClass();
$itemMap       = new PluginIserviceImportMapping();

foreach ($oldItems as $oldItem) {
    $map      = $itemMap->findForOldItemID($itemTypeClass, $oldItem['id']);
    $itemData = $oldItem;
    unset($itemData['id']);

    if (empty($map)) {
        $item->add($itemData);
        $itemMap->add(
            [
                'itemtype' => $itemTypeClass,
                'items_id' => $item->getID(),
                'old_id'   => $oldItem['id'],
            ]
        );
    } else {
        $itemData['id'] = $map['items_id'];
        $item->update($itemData);
    }

    break;
}

echo "OK";

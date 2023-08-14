<?php
require '../inc/includes.php';

// Send UTF8 Headers.
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();
Session::checkLoginUser();

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

$input = IserviceToolBox::getInputVariables(
    [
        'itemType',
    ]
);

$configFileName = PLUGIN_ISERVICE_DIR . '/config/import/' . strtolower("$input[itemType].php");
if (empty($input['itemType']) || !file_exists($configFileName)) {
    die("Invalid item type: $input[itemType]");
}

$importConfig = include $configFileName;
if (empty($importConfig)) {
    die("Invalid import config for item type $input[itemType], it must return an array");
}

$itemTypeClass = $importConfig['itemTypeClass'];

/* @var CommonDBTM $item */
$item = new $itemTypeClass();

if (empty($importConfig['clearCondition'])) {
    $deleteQuery = "truncate table {$item->getTable()}";
} else {
    $deleteQuery = "delete from {$item->getTable()} where {$importConfig['clearCondition']}";
}

if (!empty($importConfig['clearRelatedTable'])) {
    if (empty($importConfig['clearCondition'])) {
        $deleteRelatedQuery = "delete from {$importConfig['clearRelatedTable']} where itemtype = '$itemTypeClass'";
    }

    if (!empty($importConfig['clearCondition'])) {
        $deleteRelatedQuery = "delete rt from {$importConfig['clearRelatedTable']} rt left join $item->getTable() it on rt.items_id = it.id AND rt.itemtype = '$itemTypeClass'";
    }
}

if (!empty($importConfig['clearRelatedTable']) && !PluginIserviceDB::getQueryResult($deleteRelatedQuery)) {
    die("Could not clear related table {$importConfig['clearRelatedTable']} for $itemTypeClass object");
}

if (!PluginIserviceDB::getQueryResult($deleteQuery)) {
    die("Could not clear table {$item->getTable()} for $itemTypeClass object");
}

$importMappingsTable = PluginIserviceImportMapping::getTable();
if (!PluginIserviceDB::getQueryResult("delete from $importMappingsTable where itemtype = '$input[itemType]' and items_id not in (select id from {$item->getTable()})")) {
    die("Could not clear records from mapping table for $itemTypeClass object");
}

echo IserviceToolBox::RESPONSE_OK;

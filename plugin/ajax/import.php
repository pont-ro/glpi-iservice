<?php
require '../inc/includes.php';

// Send UTF8 Headers.
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();
Session::checkLoginUser();

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

function getForeignKeyData(array $importConfig): array
{
    if (empty($importConfig['foreignKeys']) || empty($importConfig['preloadForeignKeys'])) {
        return [];
    }

    $result        = [];
    $foreignKeyMap = new PluginIserviceImportMapping();

    foreach ($importConfig['foreignKeys'] as $itemType) {
        if (empty($result[$itemType])) {
            $map = $foreignKeyMap->find(['itemtype' => $itemType]);
            foreach ($map as $item) {
                $result[$itemType][$item['old_id']] = $item['new_id'];
            }
        }
    }

    return $result;
}

function processItemData(array $oldItemData, array $importConfig, array &$foreignKeyData, array &$errors): array
{
    $result = $oldItemData;
    unset($result['id']);

    if (isset($importConfig['forceValues'])) {
        foreach ($importConfig['forceValues'] as $fieldName => $value) {
            $result[$fieldName] = $value;
        }
    }

    if (isset($importConfig['checkValues'])) {
        foreach ($importConfig['checkValues'] as $fieldName => $value) {
            if ($result[$fieldName] !== $value) {
                $errors[] = "Invalid value for field $fieldName: $result[$fieldName], expected $value";
            }
        }
    }

    if (!empty($importConfig['foreignKeys'])) {
        $foreignKeyMap = new PluginIserviceImportMapping();
        foreach ($importConfig['foreignKeys'] as $fieldName => $itemType) {
            if ($result[$fieldName] < 0) {
                $result[$fieldName] = 0;
            }

            if (empty($result[$fieldName])) {
                continue;
            }

            if (empty($foreignKeyData[$itemType][$result[$fieldName]])) {
                if ($foreignKeyMap->getFromDBByCrit(['itemtype' => $itemType,'old_id'   => $result[$fieldName]])) {
                    $foreignKeyData[$itemType][$result[$fieldName]] = $foreignKeyMap->getField('items_id');
                }
            }

            if (empty($foreignKeyData[$itemType][$result[$fieldName]])) {
                $errors[] = "Cannot find new value for $itemType object with id {$result[$fieldName]}. Was it imported?";
                continue;
            }

            $result[$fieldName] = $foreignKeyData[$itemType][$result[$fieldName]];
        }
    }

    if (!empty($importConfig['selfReferences'])) {
        foreach ($importConfig['selfReferences'] as $fieldName) {
            if ($result[$fieldName] < 0) {
                $result[$fieldName] = 0;
            }

            if (empty($result[$fieldName])) {
                continue;
            }

            if (empty($foreignKeyData[$importConfig['itemTypeClass']][$result[$fieldName]])) {
                // $errors[] = "Cannot find new value for self referenced $importConfig[itemTypeClass] object with id {$result[$fieldName]}. Would it be imported later?";
                $errors['retry'][$oldItemData['id']] = $oldItemData;
                continue;
            }

            $result[$fieldName] = $foreignKeyData[$importConfig['itemTypeClass']][$result[$fieldName]];
        }
    }

    return $result;
}

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

$foreignKeyData = getForeignKeyData($importConfig);

$oldItems = IserviceToolBox::getQueryResult(
    "SELECT * FROM $importConfig[oldTable]",
    'id',
    new PluginIserviceDB($input['oldDBHost'], $input['oldDBName'], $input['oldDBUser'], $input['oldDBPassword'])
);

$errors        = [];
$itemTypeClass = $importConfig['itemTypeClass'];

/* @var CommonDBTM $item */
$item    = new $itemTypeClass();
$itemMap = new PluginIserviceImportMapping();

do {
    unset($errors['retry']);
    $oldItemsCount = count($oldItems);

    foreach ($oldItems as $oldItem) {
        $foundId  = false;
        $map      = $itemMap->findForOldItemID($itemTypeClass, $oldItem['id']);
        $itemData = processItemData($oldItem, $importConfig, $foreignKeyData, $errors);

        if (!empty($map)) {
            $foundId = $map['items_id'];
        } elseif (!empty($importConfig['identifierField'])
            && $item->getFromDBByCrit([$importConfig['identifierField'] => $itemData[$importConfig['identifierField']]])
        ) {
            $foundId = $item->getID();
            $itemMap->add(
                [
                    'itemtype' => $itemTypeClass,
                    'items_id' => $item->getID(),
                    'old_id'   => $oldItem['id'],
                ]
            );
        }

        if ($foundId === false) {
            if (!$item->add($itemData)) {
                $errors[] = "Could not add $itemTypeClass object with data: " . json_encode($itemData);
            };

            $itemMap->add(
                [
                    'itemtype' => $itemTypeClass,
                    'items_id' => $item->getID(),
                    'old_id'   => $oldItem['id'],
                ]
            );
        } else {
            $itemData['id'] = $foundId;
            $item->update($itemData);
        }

        if (!empty($importConfig['selfReferences'])) {
            $foreignKeyData[$itemTypeClass][$oldItem['id']] = $item->getID();
        }
    }
} while (!empty($errors['retry']) && $oldItemsCount > count($errors['retry']) && $oldItems = $errors['retry']);

echo empty($errors) ? "OK" : json_encode($errors);

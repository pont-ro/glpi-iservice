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

    $result = mapFields($result, $importConfig['fieldMap'] ?? []);
    $result = forceValues($result, $importConfig['forceValues'] ?? []);
    $result = checkValues($result, $importConfig['checkValues'] ?? [], $errors);
    $result = mapForeignKeys($result, $importConfig['foreignKeys'] ?? [], $foreignKeyData, $errors);
    $result = mapSelfReferences($result, $importConfig['selfReferences'] ?? [], $oldItemData, $foreignKeyData, $errors);

    return escapeValues($result);
}

function mapFields(array $input, array $fieldMap): array
{
    if (empty($fieldMap)) {
        return $input;
    }

    $result = [];
    foreach ($fieldMap as $fieldMapData) {
        if (empty($fieldMapData['name'])) {
            continue;
        }

        $result[$fieldMapData['name']] = $input[$fieldMapData['old_name'] ?? $fieldMapData['name']] ?? null;
    }

    return $result;
}

function forceValues(array $result, array $forceValues): array
{
    if (empty($forceValues)) {
        return $result;
    }

    foreach ($forceValues as $fieldName => $value) {
        $result[$fieldName] = $value;
    }

    return $result;
}

function checkValues(array $result, array $checkValues, array &$errors): array
{
    if (empty($checkValues)) {
        return $result;
    }

    foreach ($checkValues as $fieldName => $value) {
        if ($result[$fieldName] !== $value) {
            $errors[] = "Invalid value for field $fieldName: $result[$fieldName], expected $value";
        }
    }

    return $result;
}

function mapForeignKeys(array $result, array $foreignKeys, array &$foreignKeyData, array &$errors): array
{
    if (empty($foreignKeys)) {
        return $result;
    }

    $foreignKeyMap = new PluginIserviceImportMapping();
    foreach ($foreignKeys as $fieldName => $itemType) {
        if ($result[$fieldName] < 0) {
            $result[$fieldName] = 0;
        }

        if (empty($result[$fieldName])) {
            continue;
        }

        if (empty($foreignKeyData[$itemType][$result[$fieldName]])) {
            if ($foreignKeyMap->getFromDBByCrit(['itemtype' => $itemType, 'old_id' => $result[$fieldName]])) {
                $foreignKeyData[$itemType][$result[$fieldName]] = $foreignKeyMap->getField('items_id');
            }
        }

        if (empty($foreignKeyData[$itemType][$result[$fieldName]])) {
            $errors[] = "Cannot find new id for $itemType object with id {$result[$fieldName]}. Was it imported?";
            continue;
        }

        $result[$fieldName] = $foreignKeyData[$itemType][$result[$fieldName]];
    }

    return $result;
}

function mapSelfReferences(array $result, array $selfReferences, array $oldItemData, array $foreignKeyData, array &$errors): array
{
    if (empty($selfReferences)) {
        return $result;
    }

    foreach ($selfReferences as $fieldName) {
        if ($result[$fieldName] < 0) {
            $result[$fieldName] = 0;
        }

        if (empty($result[$fieldName])) {
            continue;
        }

        if (empty($foreignKeyData['self'][$result[$fieldName]])) {
            $errors['retry'][$oldItemData['id']] = $oldItemData;
            continue;
        }

        $result[$fieldName] = $foreignKeyData['self'][$result[$fieldName]];
    }

    return empty($errors['retry'][$oldItemData['id']]) ? $result : [];
}

function escapeValues(array $result): array
{
    global $DB;
    foreach ($result as $fieldName => $value) {
        $result[$fieldName] = $DB->escape($value);
    }

    return $result;
}

// -------------------
/* End of functions */

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
    die("Invalid item type: $input[itemType]");
}

$importConfig = include $configFileName;
if (empty($importConfig)) {
    die("Invalid import config for item type $input[itemType], it must return an array");
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

        if (empty($itemData)) {
            continue;
        }

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
            $foreignKeyData['self'][$oldItem['id']] = $item->getID();
        }
    }
} while (!empty($errors['retry']) && $oldItemsCount > count($errors['retry']) && $oldItems = $errors['retry']);

if ($oldItemsCount === count($errors['retry'] ?? [])) {
    $errors[] = "Cannot find new values for self referenced $importConfig[itemTypeClass] objects with the following ids:";
    $errors[] = implode(', ', array_keys($errors['retry']));
    unset($errors['retry']);
}

echo empty($errors) ? IserviceToolBox::RESPONSE_OK : json_encode($errors);

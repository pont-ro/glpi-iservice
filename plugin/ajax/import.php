<?php
require '../inc/includes.php';

ini_set('memory_limit', '1024M');

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

    $result = mapFields($result, $importConfig['fieldMap'] ?? []);
    $result = changeEmptyStringToNull($result, $importConfig['fieldMap'] ?? []);
    $result = forceValues($result, $importConfig['forceValues'] ?? []);
    $result = checkValues($result, $importConfig['checkValues'] ?? [], $errors);
    $result = mapForeignKeys(
        $result,
        $importConfig['foreignKeys'] ?? [],
        $foreignKeyData,
        $errors, $importConfig['handleMissingForeignKeys'] ?? [],
        $importConfig['itemTypeClass']
    );

    unset($result['id']);
    $result = mapSelfReferences($result, $importConfig['selfReferences'] ?? [], $oldItemData, $foreignKeyData, $errors);

    if (!empty($result['new_cartridge_ids'])) {
        $result['new_cartridge_ids'] = mapNewCartridgeIds($result['new_cartridge_ids'], $errors);
    }

    if (!empty($result['group_field'])) {
        $result['group_field'] = mapGroupField($result['group_field'], $errors);
    }

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

        if (!empty($fieldMapData['valueMap'])) {
            $result[$fieldMapData['name']] = $fieldMapData['valueMap'][$input[$fieldMapData['old_name'] ?? $fieldMapData['name']]] ?? $fieldMapData['default'] ?? null;
        } else {
            $result[$fieldMapData['name']] = $input[$fieldMapData['old_name'] ?? $fieldMapData['name']] ?? null;
        }
    }

    return $result;
}

function changeEmptyStringToNull(array $input, array $fieldMap): array
{
    if (empty($fieldMap)) {
        return $input;
    }

    $result = [];
    foreach ($fieldMap as $fieldMapData) {
        if (empty($fieldMapData['name'])) {
            continue;
        }

        if (!empty($fieldMapData['type'])
            && in_array($fieldMapData['type'], ['number', 'date', 'datetime', 'yesno'])
            && $input[$fieldMapData['name']] === ''
        ) {
            $result[$fieldMapData['name']] = null;
        } else {
            $result[$fieldMapData['name']] = $input[$fieldMapData['name']];
        }
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

function mapForeignKeys(array $result, array $foreignKeys, array &$foreignKeyData, array &$errors, array $handleMissingForeignKeys, string $importModelItemTypeClass): array
{
    if (empty($foreignKeys)) {
        return $result;
    }

    $foreignKeyMap = new PluginIserviceImportMapping();
    foreach ($foreignKeys as $fieldName => $fieldData) {
        if ($result[$fieldName] < 0) {
            $result[$fieldName] = 0;
        }

        if (empty($result[$fieldName])) {
            continue;
        }

        $itemType = mapItemType($result, $fieldData);

        if (empty($itemType)) {
            continue;
        }

        if (empty($foreignKeyData[$itemType][$result[$fieldName]])) {
            if ($foreignKeyMap->getFromDBByCrit(['itemtype' => $itemType, 'old_id' => $result[$fieldName]])) {
                $foreignKeyData[$itemType][$result[$fieldName]] = $foreignKeyMap->getField('items_id');
            }
        }

        if (empty($foreignKeyData[$itemType][$result[$fieldName]])) {
            if (!empty($handleMissingForeignKeys[$fieldName]['add'])) {
                $foreignKeyData[$itemType][$result[$fieldName]] = $result[$fieldName] + $handleMissingForeignKeys[$fieldName]['add'];
            } elseif (isset($handleMissingForeignKeys[$fieldName]['force'])) {
                $foreignKeyData[$itemType][$result[$fieldName]] = $handleMissingForeignKeys[$fieldName]['force'];
            } else {
                $errors[] = "Cannot find new id for $itemType object with id {$result[$fieldName]}. Was it imported?";
                $errors[$importModelItemTypeClass]['missingIds'][$itemType][] = $result[$fieldName];
                continue;
            }
        }

        $result[$fieldName] = $foreignKeyData[$itemType][$result[$fieldName]];
    }

    return $result;
}

function mapItemType(array $result, mixed $itemType): string
{
    if (!is_array($itemType)) {
        return $itemType;
    }

    if (isset($itemType['dependsFrom']) && !empty($result[$itemType['dependsFrom']])) {
        if (empty($itemType['itemTypes'])) {
            return $result[$itemType['dependsFrom']];
        }

        return $itemType['itemTypes'][$result[$itemType['dependsFrom']]] ?? '';
    }

    return '';
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
        $result[$fieldName] = $result[$fieldName] !== null ? str_replace('&#039;', '\&#039;', $result[$fieldName]) : null;
    }

    return $result;
}

function calculateExecutionTime($itemsCount): int
{
    return $itemsCount * 0.05 > ini_get('max_execution_time') ? $itemsCount * 0.05 : ini_get('max_execution_time');
}

function getCriteria(array $importConfig, array $itemData): array
{
    if (!is_array($importConfig['identifierField'])) {
        return [$importConfig['identifierField'] => $itemData[$importConfig['identifierField']]];
    }

    $criteria = [];
    foreach ($importConfig['identifierField'] as $fieldName) {
        $criteria[$fieldName] = $itemData[$fieldName];
    }

    return $criteria;
}

function mapNewCartridgeIds(string $cartridgeIds, array &$errors): string
{
    $cartridgeIdsToMap = explode(',', $cartridgeIds);
    $foreignKeyMap     = new PluginIserviceImportMapping();

    foreach ($cartridgeIdsToMap as &$cartridgeId) {
        // Remove | from the string.
        $cartridgeId = str_replace('|', '', $cartridgeId);

        if ($foreignKeyMap->getFromDBByCrit(['itemtype' => 'Cartridge', 'old_id' => $cartridgeId ])) {
            $newCartridgeId = $foreignKeyMap->getField('items_id');
        }

        if (empty($newCartridgeId) || $newCartridgeId == 'N/A') {
            $cartridgeId = '|old_' . $cartridgeId . '|';
        } else {
            $cartridgeId = '|' . $newCartridgeId . '|';
        }
    }

    return implode(',', $cartridgeIdsToMap);
}

function mapGroupField(string $groupField, array &$errors): string
{
    $supplierIdsToMap = explode(',', $groupField);
    $foreignKeyMap    = new PluginIserviceImportMapping();

    foreach ($supplierIdsToMap as &$supplierId) {
        if ($foreignKeyMap->getFromDBByCrit(['itemtype' => 'Supplier', 'old_id' => $supplierId ])) {
            $newSupplierId = $foreignKeyMap->getField('items_id');
        }

        if (empty($newSupplierId) || $newSupplierId == 'N/A') {
            $supplierId = 'old_' . $newSupplierId;
        } else {
            $supplierId = $newSupplierId;
        }
    }

    return implode(',', $supplierIdsToMap);
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
$select         = $importConfig['select'] ?? '*';

$oldItems = PluginIserviceDB::getQueryResult(
    "SELECT $select FROM $importConfig[oldTable] ORDER BY id ASC",
    'id',
    new PluginIserviceDB($input['oldDBHost'], $input['oldDBName'], $input['oldDBUser'], $input['oldDBPassword'])
);

$errors        = [];
$itemTypeClass = $importConfig['itemTypeClass'];

/* @var CommonDBTM $item */
$item    = new $itemTypeClass();
$itemMap = new PluginIserviceImportMapping();
set_time_limit(calculateExecutionTime(count($oldItems)));

$messagesFromSessionInitial                         = $_SESSION['MESSAGE_AFTER_REDIRECT'] ?? [];
$_SESSION['plugin']['iservice']['importInProgress'] = true;

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
            && $item->getFromDBByCrit(getCriteria($importConfig, $itemData))
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
                $errors[]                                            = "Could not add $itemTypeClass object with data: " . json_encode($itemData);
                $errors['itemsNotAdded'][$itemTypeClass]['old_id'][] = $oldItem['id'];
                continue;
            };

            if (!empty($importConfig['updateAfterCreate']) && !$item->update(array_merge($itemData, ['id' => $item->getID()]))) {
                $errors[] = "Could not update newly created $itemTypeClass object with data: " . json_encode($itemData);

                $errors['newItemsNotUpdated'][$itemTypeClass]['old_id'][] = $oldItem['id'];
            }

            $itemMap->add(
                [
                    'itemtype' => $itemTypeClass,
                    'items_id' => $item->getID(),
                    'old_id'   => $oldItem['id'],
                ]
            );
        } else {
            $itemData['id'] = $foundId;
            if (!$item->update($itemData)) {
                // NOTE: Not all items can be updated, for example glpi_items_tickets that belong to a closed ticket.
                $errors[]                                              = "Could not update $itemTypeClass object with data: " . json_encode($itemData);
                $errors['itemsNotUpdated'][$itemTypeClass]['old_id'][] = $oldItem['id'];
            };
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

$_SESSION['plugin']['iservice']['importInProgress'] = false;

if (!empty($errors)) {
    $errors['messagesFromSession'] = $_SESSION['MESSAGE_AFTER_REDIRECT'] ?? [];
    trigger_error(json_encode($errors, JSON_PRETTY_PRINT), E_USER_WARNING);
    $_SESSION['MESSAGE_AFTER_REDIRECT'] = $messagesFromSessionInitial;
}

echo empty($errors) ? IserviceToolBox::RESPONSE_OK : json_encode($errors);

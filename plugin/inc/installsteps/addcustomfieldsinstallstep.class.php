<?php

namespace GlpiPlugin\Iservice\InstallSteps;

use Glpi\Toolbox\Sanitizer;
use PluginFieldsContainer;
use PluginFieldsField;
use PluginFieldsLabelTranslation;
use Session;

class AddCustomFieldsInstallStep
{
    const CLEANUP_ON_UNINSTALL = false;

    const CONTAINERS = [
        [
            'name'      => 'customfield',
            'label'     => 'Printer Custom Fields',
            'itemtypes' => ['Printer'],
            'type'      => 'tab',
            'subtype'   => null,
            'is_active' => '1',
            'fields'    => PLUGIN_ISERVICE_DIR . '/install/customfields/printer_customfields.json',
            'old_id'    => 1,
        ],
        [
            'name'      => 'customfield',
            'label'     => 'Ticket Custom Fields',
            'itemtypes' => ['Ticket'],
            'type'      => 'tab',
            'subtype'   => null,
            'is_active' => '1',
            'fields'    => PLUGIN_ISERVICE_DIR . '/install/customfields/ticket_customfields.json',
            'old_id'    => 2,
        ],
        [
            'name'      => 'customfield',
            'label'     => 'Supplier Custom Fields',
            'itemtypes' => ['Supplier'],
            'type'      => 'tab',
            'subtype'   => null,
            'is_active' => '1',
            'fields'    => PLUGIN_ISERVICE_DIR . '/install/customfields/supplier_customfields.json',
            'old_id'    => 3,
        ],
        [
            'name'      => 'customfield',
            'label'     => 'Contract Custom Fields',
            'itemtypes' => ['Contract'],
            'type'      => 'tab',
            'subtype'   => null,
            'is_active' => '1',
            'fields'    => PLUGIN_ISERVICE_DIR . '/install/customfields/contract_customfields.json',
            'old_id'    => 4,
        ],
        [
            'name'      => 'customfield',
            'label'     => 'Cartridge Item Custom Fields',
            'itemtypes' => ['CartridgeItem'],
            'type'      => 'tab',
            'subtype'   => null,
            'is_active' => '1',
            'fields'    => PLUGIN_ISERVICE_DIR . '/install/customfields/cartridgeitem_customfields.json',
            'old_id'    => 5,
        ],
        [
            'name'      => 'customfield',
            'label'     => 'Cartridge Custom Fields',
            'itemtypes' => ['Cartridge'],
            'type'      => 'tab',
            'subtype'   => null,
            'is_active' => '1',
            'fields'    => PLUGIN_ISERVICE_DIR . '/install/customfields/cartridge_customfields.json',
            'old_id'    => null,
        ],
        [
            'name'      => 'customfield',
            'label'     => 'Printer Model Custom Fields',
            'itemtypes' => ['PrinterModel'],
            'type'      => 'tab',
            'subtype'   => null,
            'is_active' => '1',
            'fields'    => PLUGIN_ISERVICE_DIR . '/install/customfields/printermodel_customfields.json',
            'old_id'    => 6,
        ]
    ];

    public static function do(): bool
    {
        $result = true;

        foreach (self::CONTAINERS as $container) {
            $result = $result && self::addOrUpdateContainer($container);
        }

        if ($result) {
            Session::addMessageAfterRedirect(__('Custom fields updated', 'iservice'), true, INFO, true);
        } else {
            Session::addMessageAfterRedirect(__('Error while updating custom fields', 'iservice'), true, ERROR, true);
        }

        return $result;
    }

    public static function undo(): void
    {
        if (!self::CLEANUP_ON_UNINSTALL) {
            return;
        }

        foreach (self::CONTAINERS as $container) {
            self::removeContainer($container);
        }
    }

    private static function addOrUpdateContainer(array $containerData): bool
    {
        $result                       = true;
        $containerData['_no_message'] = true;

        $mapping    = new \PluginIserviceImportMapping();
        $container  = new PluginFieldsContainer();
        $containers = $container->find(
            [
                'itemtypes' => self::encodeItemTypes($containerData['itemtypes']),
            ]
        );

        if (count($containers) === 0) {
            $containerData['id'] = $container->add($containerData);
            if (!empty($containerData['old_id'])) {
                $mapping->add(
                    [
                        'itemtype' => PluginFieldsContainer::class,
                        'items_id' => $containerData['id'],
                        'old_id'   => $containerData['old_id'],
                    ]
                );
            }
        } else {
            $containerData['id']        = array_shift($containers)['id'];
            $containerData['itemtypes'] = self::encodeItemTypes($containerData['itemtypes']);
            $result                     = $container->update($containerData);
        }

        $result = $result && self::addOrUpdateContainerFields($containerData);
        return $result && self::optimizeContainerTableFieldTypes($containerData);
    }

    private static function optimizeContainerTableFieldTypes($containerData)
    {
        $result    = true;
        $container = PluginFieldsContainer::getById($containerData['id']);

        foreach (json_decode($container->fields['itemtypes']) as $itemtype) {
            $classname   = $container::getClassname($itemtype, $container->fields['name']);
            $table       = getTableForItemType($classname);
            $tableConfig = self::getColumnsSql(self::getFieldsData($containerData['fields']));

            $result = $result && \PluginIserviceDB::alterTable($table, $tableConfig);
        }

        return $result;

    }

    public static function getColumnsSql($fieldsData): array
    {
        $fields = [];
        foreach ($fieldsData as $fieldData) {
            $field_name = $fieldData['name'];
            $field_type = $fieldData['type'];
            $default    = $fieldData['default_value'] !== '' ? $fieldData['default_value'] : null;
            $mandatory  = $fieldData['mandatory'] === '1' ? 'not null' : '';

            switch (true) {
            case $field_type === 'yesno':
                $fields[$field_name] = "tinyint $mandatory" . ($default === null && $mandatory == '' ? 'NULL DEFAULT NULL' : ($default ? " default $default" : ''));
                break;
            case $field_type === 'date':
                $fields[$field_name] = "date $mandatory" . ($default === null && $mandatory == '' ? 'NULL DEFAULT NULL' : ($default ? " default '$default'" : ''));
                break;
            case $field_type === 'datetime':
                $fields[$field_name] = "timestamp $mandatory" . ($default === null && $mandatory == '' ? 'NULL DEFAULT NULL' : ($default ? " default '$default'" : ''));
                break;
            case $field_type === 'number':
                $fields[$field_name] = "decimal(15,2) $mandatory" . ($default === null && $mandatory == '' ? 'NULL DEFAULT NULL' : ($default ? " default $default" : ''));
                break;
            default:
                break;
            }
        }

        return [
            'columns' => $fields,
        ];
    }

    private static function removeContainer(array $containerData): void
    {
        $container  = new PluginFieldsContainer();
        $containers = $container->find(
            [
                'itemtypes' => self::encodeItemTypes($containerData['itemtypes']),
            ]
        );
        foreach ($containers as $con) {
            $container->delete(
                [
                    '_no_message' => true,
                    'purge'       => 1,
                    'id'          => $con['id']
                ],
                1
            );
        }
    }

    private static function encodeItemTypes(string|array $itemTypes): string
    {
        if (!is_array($itemTypes)) {
            $itemTypes = [$itemTypes];
        }

        return Sanitizer::dbEscape(json_encode($itemTypes));
    }

    private static function updateFieldLabelTranslation(mixed $fieldData): bool
    {
        $translation  = new PluginFieldsLabelTranslation();
        $translations = $translation->find(
            [
                'itemtype' => 'PluginFieldsField',
                'items_id' => $fieldData['id'],
            ],
            [
                'id',
            ]
        );

        return $translation->update(
            [
                'id'    => array_shift($translations)['id'],
                'label' => $fieldData['label'],
            ]
        );
    }

    private static function updateFiledLabelTranslations(array $labelTranslations, int $id): bool
    {
        $result        = true;
        $translation   = new PluginFieldsLabelTranslation();
        $labelLanguage = 'ro_RO';

        foreach ($labelTranslations as $labelTranslation) {
            $translations = $translation->find(
                [
                    'itemtype' => 'PluginFieldsField',
                    'items_id' => $id,
                    'language' => $labelLanguage,
                ]
            );

            if (count($translations) === 0) {
                $result = $result && $translation->add(
                    [
                        'itemtype' => 'PluginFieldsField',
                        'items_id' => $id,
                        'language' => $labelLanguage,
                        'label'    => $labelTranslation,
                    ]
                );
            } else {
                $result = $result && $translation->update(
                    [
                        'id'    => array_shift($translations)['id'],
                        'label' => $labelTranslation,
                    ]
                );
            }
        }

        return $result;
    }

    private static function addOrUpdateContainerFields(array $containerData): bool
    {
        $result = true;
        $field  = new PluginFieldsField();

        $fieldsData = self::getFieldsData($containerData['fields']);
        foreach ($fieldsData as $fieldData) {
            $fieldData['_no_message']                 = true;
            $fieldData['plugin_fields_containers_id'] = $containerData['id'];

            if (!is_array($fieldData['label'])) {
                $fieldData['label'] = [$fieldData['label']];
            }

            $labelTranslations  = $fieldData['label'];
            $fieldData['label'] = array_shift($labelTranslations);

            $fields = $field->find(
                [
                    'name' => $fieldData['name'],
                ]
            );

            if (count($fields) === 0) {
                $fieldData['id'] = $field->add($fieldData);
            } else {
                $fieldData['id'] = array_shift($fields)['id'];
                $result          = $result && $field->update($fieldData);
                $result          = $result && self::updateFieldLabelTranslation($fieldData);
            }

            $result = $result && self::updateFiledLabelTranslations($labelTranslations, $fieldData['id']);
            $result = $result && self::addOrUpdateFieldDropdownValues($fieldData['dropdown_values'] ?? [], $fieldData['name']);
        }

        return $result;
    }

    private static function getFieldsData(string|array $fieldsData): array
    {
        if (is_array($fieldsData)) {
            return $fieldsData;
        }

        if (is_file($fieldsData)) {
            return json_decode(file_get_contents($fieldsData), true) ?? [];
        }

        return [];
    }

    private static function addOrUpdateFieldDropdownValues(array $dropdownValues, string $dropdownName): bool
    {
        if (empty($dropdownValues)) {
            return true;
        }

        $result        = true;
        $dropdownClass = 'PluginFields' . ucfirst($dropdownName) . 'Dropdown';
        $dropdown      = new $dropdownClass();

        foreach ($dropdownValues as $dropdownValueData) {
            if (!is_array($dropdownValueData)) {
                $dropdownValueData = ['name' => $dropdownValueData];
            }

            $dropdownValueData['_no_message'] = true;

            $dropdowns = $dropdown->find(
                [
                    'name' => $dropdownValueData['name'],
                ]
            );

            if (count($dropdowns) === 0) {
                $result = $result && $dropdown->add($dropdownValueData);
            } else {
                $dropdownValueData['id'] = array_shift($dropdowns)['id'];
                $result                  = $result && $dropdown->update($dropdownValueData);
            }
        }

        return $result;
    }

}

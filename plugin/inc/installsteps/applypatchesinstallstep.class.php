<?php

namespace GlpiPlugin\Iservice\InstallSteps;

use PluginIserviceConfig;
use Session;

/**
 * Applies source-code patches to third-party files (currently the GLPI "fields" plugin).
 *
 * Each patch is idempotent and self-healing: the replacement text embeds a unique marker.
 * On every iService install/update this step re-applies any patch whose marker is missing
 * (for example after the target plugin was upgraded and overwrote our change).
 *
 * To add a new patch, append an entry to the PATCHES constant with:
 *   - name    : human readable identifier (used in messages)
 *   - marker  : unique string present in `replace` and absent from `search`; used to detect application
 *   - file    : absolute path of the file to patch
 *   - search  : exact original snippet to look for (must match byte-for-byte, including indentation)
 *   - replace : snippet to write instead (must contain the marker)
 */
class ApplyPatchesInstallStep
{
    const CLEANUP_ON_UNINSTALL = false;

    const PATCHES = [
        [
            // Convert empty-string number-field values to NULL before the fields plugin writes them,
            // so iService custom-field columns can stay DECIMAL/INT (proper indexing, sorting and
            // speed) instead of VARCHAR, without MySQL strict mode rejecting '' on save.
            // Patched in updateFieldsValues() because both write paths converge there:
            // the item-save hook (preItemUpdate -> updateFieldsValues) and the fields plugin's own
            // tab form (front/container.form.php -> updateFieldsValues).
            'name'   => 'fields-number-empty-string-to-null',
            'marker' => 'ISERVICE-PATCH:fields-number-empty-string-to-null',
            'file'   => GLPI_ROOT . '/plugins/fields/inc/container.class.php',
            'search' => <<<'SEARCH'
            } elseif (array_key_exists('_' . $field_name . '_defined', $data)) {
                $data[$field_name] = json_encode([]);
            }
        }

        $container_obj = new PluginFieldsContainer();
        $container_obj->getFromDB($data['plugin_fields_containers_id']);

        $items_id  = $data['items_id'];
        $classname = self::getClassname($itemtype, $container_obj->fields['name']);

        $dbu = new DbUtils();
SEARCH,
            'replace' => <<<'REPLACE'
            } elseif (array_key_exists('_' . $field_name . '_defined', $data)) {
                $data[$field_name] = json_encode([]);
            }
        }

        // ISERVICE-PATCH:fields-number-empty-string-to-null - convert '' to null so DECIMAL/INT number columns accept "no value".
        $number_fields_iterator = $DB->request([
            'FROM'  => PluginFieldsField::getTable(),
            'WHERE' => [
                'is_active'                   => 1,
                'type'                        => 'number',
                'plugin_fields_containers_id' => $data['plugin_fields_containers_id'],
            ],
        ]);
        foreach ($number_fields_iterator as $field_data) {
            $field_name = $field_data['name'];
            if (array_key_exists($field_name, $data) && $data[$field_name] === '') {
                $data[$field_name] = null;
            }
        }

        $container_obj = new PluginFieldsContainer();
        $container_obj->getFromDB($data['plugin_fields_containers_id']);

        $items_id  = $data['items_id'];
        $classname = self::getClassname($itemtype, $container_obj->fields['name']);

        $dbu = new DbUtils();
REPLACE,
        ],
        [
            // Accept the string 'NULL' as a valid value for number custom fields, so number columns
            // can be left "empty" (stored as NULL) without the fields plugin's own validation rejecting it.
            // README > Fields plugin hacks: PluginFieldsContainer::validateValues().
            'name'    => 'fields-number-accept-null-value',
            'marker'  => "strtoupper(\$value) !== 'NULL'",
            'file'    => GLPI_ROOT . '/plugins/fields/inc/container.class.php',
            'search'  => "} elseif (\$field['type'] == 'number' && !empty(\$value) && !is_numeric(\$value)) {",
            'replace' => "} elseif (\$field['type'] == 'number' && !empty(\$value) && strtoupper(\$value) !== 'NULL' && !is_numeric(\$value)) {",
        ],
        [
            // Prevent the fields plugin from renaming our custom fields on every migration (>= 1.9.2),
            // which breaks iService column references. README > Fields plugin hacks: field.class.php.
            'name'   => 'fields-disable-fixfieldsnames-field',
            'marker' => "//        \$toolbox->fixFieldsNames(\$migration, ['NOT' => ['type' => 'dropdown']]);",
            'file'   => GLPI_ROOT . '/plugins/fields/inc/field.class.php',
            'search' => <<<'SEARCH'
        $toolbox = new PluginFieldsToolbox();
        $toolbox->fixFieldsNames($migration, ['NOT' => ['type' => 'dropdown']]);

        //move old types to new format
        $migration->addPostQuery(
            $DB->buildUpdate(
                PluginFieldsField::getTable(),
                ['type' => 'dropdown-User'],
                ['type' => 'dropdownuser'],
            ),
        );

        $migration->addPostQuery(
            $DB->buildUpdate(
                PluginFieldsField::getTable(),
                ['type' => 'dropdown-OperatingSystem'],
                ['type' => 'dropdownoperatingsystems'],
            ),
        );
SEARCH,
            'replace' => <<<'REPLACE'
//        $toolbox = new PluginFieldsToolbox();
//        $toolbox->fixFieldsNames($migration, ['NOT' => ['type' => 'dropdown']]);

        //move old types to new format
//        $migration->addPostQuery(
//            $DB->buildUpdate(
//                PluginFieldsField::getTable(),
//                ['type' => 'dropdown-User'],
//                ['type' => 'dropdownuser'],
//            ),
//        );
//
//        $migration->addPostQuery(
//            $DB->buildUpdate(
//                PluginFieldsField::getTable(),
//                ['type' => 'dropdown-OperatingSystem'],
//                ['type' => 'dropdownoperatingsystems'],
//            ),
//        );
REPLACE,
        ],
        [
            // Same renaming guard for dropdown custom fields. README > Fields plugin hacks: dropdown.class.php.
            'name'   => 'fields-disable-fixfieldsnames-dropdown',
            'marker' => "//        \$toolbox->fixFieldsNames(\$migration, ['type' => 'dropdown']);",
            'file'   => GLPI_ROOT . '/plugins/fields/inc/dropdown.class.php',
            'search' => <<<'SEARCH'
        $toolbox = new PluginFieldsToolbox();
        $toolbox->fixFieldsNames($migration, ['type' => 'dropdown']);

        // Ensure data is update before regenerating files.
        $migration->executeMigration();
SEARCH,
            'replace' => <<<'REPLACE'
//        $toolbox = new PluginFieldsToolbox();
//        $toolbox->fixFieldsNames($migration, ['type' => 'dropdown']);
//
//        // Ensure data is update before regenerating files.
//        $migration->executeMigration();
REPLACE,
        ],
    ];

    public static function do(): bool
    {
        $result = true;
        foreach (self::PATCHES as $patch) {
            $result = self::applyPatch($patch) && $result;
        }

        return $result;
    }

    public static function undo(): void
    {
        if (!PluginIserviceConfig::getConfigValue('plugin.cleanup_on_uninstall', self::CLEANUP_ON_UNINSTALL)) {
            return;
        }

        foreach (self::PATCHES as $patch) {
            self::revertPatch($patch);
        }
    }

    private static function applyPatch(array $patch): bool
    {
        if (!is_file($patch['file']) || !is_readable($patch['file'])) {
            Session::addMessageAfterRedirect(sprintf(_t('Patch "%s": target file not found (%s).'), $patch['name'], $patch['file']), true, WARNING, true);
            return false;
        }

        $content = file_get_contents($patch['file']);

        // Already applied (marker present) -> nothing to do.
        if (str_contains($content, $patch['marker'])) {
            return true;
        }

        // Anchor not found -> the third-party file changed shape; do not corrupt it.
        if (!str_contains($content, $patch['search'])) {
            Session::addMessageAfterRedirect(
                sprintf(_t('Patch "%s": anchor not found in %s. Manual review needed.'), $patch['name'], $patch['file']),
                true,
                WARNING,
                true
            );
            return false;
        }

        // Replace only the first occurrence.
        $pos     = strpos($content, $patch['search']);
        $patched = substr_replace($content, $patch['replace'], $pos, strlen($patch['search']));

        if (file_put_contents($patch['file'], $patched) === false) {
            Session::addMessageAfterRedirect(sprintf(_t('Patch "%s": could not write to %s.'), $patch['name'], $patch['file']), true, ERROR, true);
            return false;
        }

        return true;
    }

    private static function revertPatch(array $patch): void
    {
        if (!is_file($patch['file']) || !is_writable($patch['file'])) {
            return;
        }

        $content = file_get_contents($patch['file']);
        if (!str_contains($content, $patch['replace'])) {
            return;
        }

        $pos      = strpos($content, $patch['replace']);
        $reverted = substr_replace($content, $patch['search'], $pos, strlen($patch['replace']));
        file_put_contents($patch['file'], $reverted);
    }
}

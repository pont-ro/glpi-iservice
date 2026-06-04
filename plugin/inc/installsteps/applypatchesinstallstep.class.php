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

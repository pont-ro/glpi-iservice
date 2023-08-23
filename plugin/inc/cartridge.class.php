<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

class PluginIserviceCartridge extends Cartridge
{
    use PluginIserviceItem;

    /*
     * @var PluginFieldsCartridgecartridgecustomfield
     */
    public $customfields = null;

    public static function dropdownEmptyablesByCartridge($cartridge, array $dropdown_options = [])
    {
        if (!empty($dropdown_options['readonly'])) {
            if (empty($dropdown_options['value'])) {
                $emptyables = [];
            } else {
                $emptyables = PluginIserviceDB::getQueryResult(
                    "
                    select c.id, ci.name, c.date_use, ctd.name type_name
                    from glpi_plugin_iservice_cartridges c
                    left join glpi_plugin_iservice_cartridge_items ci on ci.id = c.cartridgeitems_id
                    left join glpi_plugin_fields_cartridgeitemtypedropdowns ctd ON ctd.id = c.plugin_fields_cartridgeitemtypedropdowns_id
                    where c.id = $dropdown_options[value]
                    ", false
                );
            }
        } else {
            $emptyables = self::getEmptiablesByCartridge($cartridge);
        }

        foreach ($emptyables as $cartridge) {
            $emptyable_cartridges[$cartridge['id']] = "$cartridge[id] - $cartridge[name] ($cartridge[type_name]) [instalat pe $cartridge[date_use]]";
        }

        if (empty($emptyable_cartridges)) {
            echo "<input name='$dropdown_options[name]' type='hidden' value='0' />Nu există cartuș de golit";
        } elseif (count($emptyable_cartridges) === 1) {
            echo "<input name='$dropdown_options[name]' type='hidden' value='" . array_keys($emptyable_cartridges)[0] . "' />";
            echo str_replace(") [", ")<br>[", $emptyable_cartridges[array_keys($emptyable_cartridges)[0]]);
        } else {
            Dropdown::showFromArray($dropdown_options['name'], $emptyable_cartridges, $dropdown_options);
        }
    }

    public static function getFirstEmptiableByCartridge($cartridge)
    {
        $result = self::getEmptiablesByCartridge($cartridge, 1);
        return array_shift($result);
    }

    public static function getEmptiablesByCartridge($cartridge, $limit = 0)
    {
        $mercury_code = ($cartridge instanceof Cartridge) ? $cartridge->fields['mercury_code_field'] : $cartridge['mercury_code_field'] ?? '';
        $type_id      = ($cartridge instanceof Cartridge) ? $cartridge->fields['plugin_fields_cartridgeitemtypedropdowns_id'] : $cartridge['plugin_fields_cartridgeitemtypedropdowns_id'] ?? '';
        $printer_id   = ($cartridge instanceof Cartridge) ? $cartridge->fields['printers_id'] : $cartridge['printers_id'] ?? '';
        return self::getEmptyablesByParams($mercury_code, $type_id, $printer_id, $limit = 0);
    }

    public static function getEmptyablesByParams($mercury_code, $type_id, $printer_id, $limit = 0)
    {
        $query_limit       = empty($limit) ? '' : "LIMIT " . intval($limit);
        $safe_type_id      = intval(trim($type_id));
        $safe_mercury_code = trim($mercury_code);
        return PluginIservicePrinter::getInstalledCartridges(
            $printer_id,
            "AND c.plugin_fields_cartridgeitemtypedropdowns_id = $safe_type_id AND LOCATE(\"'$safe_mercury_code'\", cfc.compatible_mercury_codes_field) > 0 $query_limit"
        );
    }

    public function getFromDB($ID)
    {
        $this->customfields = new PluginFieldsCartridgecartridgecustomfield();
        if (parent::getFromDB($ID)) {
            if (!PluginIserviceDB::populateByItemsId($this->customfields, $ID) && !$this->customfields->add(['add' => 'add', 'items_id' => $ID, '_no_message' => true])) {
                return false;
            }

            // Further code possibility
            self::$item_cache[$ID] = $this;
            return true;
        }

        return false;
    }

}

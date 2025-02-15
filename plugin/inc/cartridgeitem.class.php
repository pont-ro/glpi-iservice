<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

/**
 * CartridgeItem Class
 * This class is used to manage the various types of cartridges.
 * \see Cartridge
 * */
class PluginIserviceCartridgeItem extends CartridgeItem
{
    use PluginIserviceItem;
    /*
     *
     * @var PluginFieldsCartridgeitemcartridgeitemcustomfield
     */
    public $customfields = null;

    public static $customFieldsModelName = 'PluginFieldsCartridgeitemcartridgeitemcustomfield';

    public function getSupportedTypes(): array
    {
        $customfields = new PluginFieldsCartridgeitemcartridgeitemcustomfield();
        if (!PluginIserviceDB::populateByItemsId($customfields, $this->getID())) {
            return [''];
        }

        $supported_types = explode(',', $customfields->fields['supported_types_field']);
        array_walk($supported_types, 'trim');
        return $supported_types;
    }

    /**
     * Gets cartridge item by ref
     *
     * Note getFromDBByCrit will return false if multiple results are found.
     *
     * @param string  $ref The object to convert
     *
     * @return array|bool
     */
    public function getFromDBByRef($ref)
    {
        return $this->getFromDBByCrit(["ref = '$ref' AND is_deleted = 0"]);
    }

    public static function getSupportedTypesByRef($ref): array
    {
        $cartridgeitem = new self();
        $cartridgeitem->getFromDBByRef($ref);
        return $cartridgeitem->getSupportedTypes();
    }

    public static function getSupportedTypesById($id): array
    {
        $cartridgeitem = new self();
        $cartridgeitem->getFromDB($id);
        return $cartridgeitem->getSupportedTypes();
    }

    public static function getTable($classname = null): string
    {
        return CartridgeItem::getTable($classname);
    }

    public static function getChangeableDropdownDataForTicket($ticket, array $dropdownOptions = []): array
    {
        $data                 = [];
        $changeableCartridges = self::getChangeablesForTicket($ticket, $dropdownOptions);
        $data['warning']      = '';
        if (empty($changeableCartridges)) {
            if ($dropdownOptions['used']) {
                $unusedChangeableCartridges = self::getChangeablesForTicket($ticket);
                if (empty($unusedChangeableCartridges)) {
                    $data['warning'] = _t('You have no more compatible cartridges');
                } else {
                    $data['notInstalledCartridgesTable'] = [
                        'header' => [
                            'notInstalledCartridges' =>
                                [
                                    'value' => _t('Cartridges available'),
                                ],
                        ]
                    ];
                    foreach ($unusedChangeableCartridges as $unusedCartridge) {
                        $cartridgeName = $unusedCartridge["name"];
                        if (!empty($unusedCartridge['location_name'])) {
                            $cartridgeName .= " din locația $unusedCartridge[location_completename]";
                        }

                        $data['notInstalledCartridgesTable']['rows'][] = [
                            'notInstalledCartridges' => [
                                'value' => "$cartridgeName ($unusedCartridge[cpt])",
                            ],
                        ];
                    }
                }
            } else {
                $data['warning'] = _t('You have no compatible cartridges');
            }

            if (!empty($ticket->customfields->fields['cartridge_install_date_field'])) {
                $data['warning'] .= ' (' . sprintf(_t('delivered before %s and not installed'), date('Y-m-d', strtotime($ticket->customfields->fields['cartridge_install_date_field']))) . ')';
            }

            return $data;
        }

        if (empty($dropdownOptions['used'])) {
            $elements = [0 => 'Nu am înlocuit cartușe'];
        } else {
            $elements = [0 => '---'];
        }

        if (isset($ticket->fields['_cartridge_id']) && isset($ticket->fields['_cartridgeitem_id']) && !in_array($ticket->fields['_cartridgeitem_id'], $dropdownOptions['used'])) {
            $data['hiddenInput'] = [
                'name' => '_cartridge_id',
                'value' => $ticket->fields['_cartridge_id'],
            ];
        }

        foreach ($changeableCartridges as $changeable_cartridge) {
            $cartridgeName = $changeable_cartridge["name"];
            $index         = $changeable_cartridge['id'];
            if (!empty($changeable_cartridge['location_name'])) {
                $cartridgeName .= " din locația $changeable_cartridge[location_completename]";
                $index         .= "l" . $changeable_cartridge['locations_id_field'];
            }

            $elements[$index] = sprintf(__('%1$s (%2$s)'), $cartridgeName, $changeable_cartridge["cpt"]);
        }

        $data['elementsArray'] = $elements;

        return $data;
    }

    public static function tableChangeablesForTicket($ticket): string|bool
    {
        $changeable_cartridges = self::getChangeablesForTicket($ticket);
        if (empty($changeable_cartridges)) {
            return false;
        }

        $table  = "<table id='printer-changeable-cartridges' class='wide80'>";
        $table .= "<tr><td colspan=2><b>Adaugă cartușe cu aviz negativ pe tichet:</b></td></tr>";
        foreach ($changeable_cartridges as $changeable_cartridge) {
            $cartridge_at_printer_location = $ticket->fields['locations_id'] == $changeable_cartridge['locations_id_field'];
            $location_condition            = empty($changeable_cartridge['location_parent_id']) ? "(l.locations_id is null or l.locations_id = 0)" : "l.locations_id = $changeable_cartridge[location_parent_id]";
            $compatible_printers           = PluginIserviceDB::getQueryResult(
                "
                select count(1) cnt, l.id location_id
                from glpi_printers p
                join glpi_infocoms ic on ic.items_id = p.id and ic.itemtype = 'Printer' and ic.suppliers_id = $changeable_cartridge[suppliers_id_field]
                join glpi_cartridgeitems_printermodels cp on cp.printermodels_id = p.printermodels_id and cp.cartridgeitems_id = $changeable_cartridge[id]
                left join glpi_locations l on l.id = p.locations_id
                where p.is_deleted = 0 and p.id != {$ticket->fields['items_id']['Printer'][0]} and $location_condition
                group by l.id
                "
            );
            if (!empty($changeable_cartridge['location_completename'])) {
                $location = " din locația $changeable_cartridge[location_completename]";
            } else {
                $location = "";
            }

            if (!empty($changeable_cartridge['printer_name'])) {
                $printer = " de pe aparatul $changeable_cartridge[printer_name]";
            } else {
                $printer = "";
            }

            $table .= "<tr><td>";
            if (count($compatible_printers) > 0 || !$cartridge_at_printer_location) {
                $checked        = "";
                $force_disabled = "";
            } else {
                $checked        = " checked='true' disabled='true'";
                $force_disabled = 'force-disabled';
            }

            if (empty($checked) && $cartridge_at_printer_location && (!array_key_exists($changeable_cartridge['locations_id_field'], array_column($compatible_printers, 'cnt', 'location_id')))) {
                $checked = " checked='true' disabled='true'";
            }

            $table .= "<input type='checkbox' class='add-cartridge toggler-checkbox $force_disabled' data-group='$changeable_cartridge[id]' data-for='cartridge-count-$changeable_cartridge[locations_id_field]-$changeable_cartridge[id]' data-warning-not='warn-ccount-$changeable_cartridge[locations_id_field]-$changeable_cartridge[id]' $checked/>";
            $table .= "</td><td>";
            if ($cartridge_at_printer_location) {
                $table .= "<b>";
            }

            $table .= "$changeable_cartridge[name] ($changeable_cartridge[ref])$location$printer<br>$changeable_cartridge[cpt].";
            if ($cartridge_at_printer_location) {
                $table .= "</b>";
            }

            if (count($compatible_printers) === 0) {
                $warn_class = "class='prevent-ticket-creation visible'";
            } else {
                $warn_class = "";
            }

            if (count($compatible_printers) === 0 && !$cartridge_at_printer_location) {
                $table .= "<br><span $warn_class id='warn-ccount-$changeable_cartridge[locations_id_field]-$changeable_cartridge[id]'><i class='fa fa-exclamation-circle' style='color:red'></i>cartușul trebuie selectat deoarece mutați ultimul apart compatibil cu el!</span>";
            } elseif (!array_key_exists($changeable_cartridge['locations_id_field'], array_column($compatible_printers, 'cnt', 'location_id'))) {
                $warn_message = $cartridge_at_printer_location ? "mutați ultimul apart compatibil cu acest cartuș de la locație!" : "nu mai aveți aparte compatibile cu acest cartuș la această locație!";
                $table       .= "<br><span $warn_class id='warn-ccount-$changeable_cartridge[locations_id_field]-$changeable_cartridge[id]'><i class='fa fa-exclamation-triangle' style='color:orange'></i>$warn_message</span>";
            }

            $table .= "</td><td>";
            $count  = explode(':', $changeable_cartridge['cpt'])[0];
            $table .= "<input type='text' id='cartridge-count-$changeable_cartridge[locations_id_field]-$changeable_cartridge[id]' data-param-name='cartridge-count[$changeable_cartridge[locations_id_field]][$changeable_cartridge[ref]]' style='display:none; width:2em;text-align:center;' value='$count' $checked/>";
            $table .= "</td></tr>";
        }

        $table .= "</table>";

        return $table;
    }

    public static function getChangeablesForTicket($ticket, array $options = []): array|bool
    {
        if (!$ticket instanceof PluginIserviceTicket) {
            $ticket_id = (($ticket instanceof Ticket) && $ticket->getID() > 0) ? $ticket->getID() : intval($ticket);
            $ticket    = new PluginIserviceTicket();
            if (!$ticket->getFromDB($ticket_id)) {
                return [];
            }
        }

        $printer          = new Printer();
        $printer_location = new Location();
        $printer_id       = $ticket->printer->fields['id'] ?? $ticket->fields['items_id']['Printer'][0] ?? $ticket->getFirstPrinter()->getID();
        $supplier_id      = $ticket->fields['_suppliers_id_assign'] ?? $ticket->getFirstAssignedPartner()->getID() ?: 0;

        if (!$printer->getFromDB($printer_id) || ($printer->fields['locations_id'] > 0 && !$printer_location->getFromDB($printer->fields['locations_id']))) {
            // return [];
        }

        if (!empty($options['used']) && is_array($options['used'])) {
            $mercurycodes = [];
            foreach ($options['used_data'] as $used_data) {
                $mercurycodes = array_merge($mercurycodes, explode(',', $used_data['mercurycodes']));
            }

            $used_condition = "AND ci.id NOT IN (SELECT items_id FROM glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields where mercury_code_field IN (" . implode(',', $mercurycodes) . "))";
        } else {
            $used_condition = "";
        }

        $date_condition = empty($ticket->customfields->fields['cartridge_install_date_field']) ? '' : "AND c.date_in <= '{$ticket->customfields->fields['cartridge_install_date_field']}'";

        if ($printer_id < 1 || !empty($options['ignore_location'])) {
            $location_condition = '';
        } else {
            $printer_location_location = empty($printer_location->fields['locations_id']) ? 0 : $printer_location->fields['locations_id'];
            $location_condition        = "AND COALESCE(l.locations_id, 0) = $printer_location_location";
        }

        if ($printer_id > 0) {
            $model_condition = " AND ci.id IN (SELECT cipm.cartridgeitems_id
                                               FROM glpi_cartridgeitems_printermodels cipm
                                               LEFT JOIN glpi_printers p ON p.printermodels_id = cipm.printermodels_id
                                               WHERE p.id = $printer_id)";
        } else {
            $model_condition = '';
        }

        if ($supplier_id > 0) {
            $supplier_condition = "FIND_IN_SET (c.suppliers_id_field, (SELECT group_field FROM glpi_plugin_fields_suppliersuppliercustomfields WHERE items_id = $supplier_id))";
        } else {
            $supplier_condition = 'c.suppliers_id_field = 0';
        }

        $query = "SELECT CONCAT(COUNT(*), ': ', GROUP_CONCAT(CONCAT('[', c.id, '] ', c.date_in) SEPARATOR ', ')) cpt
                       , ci.id
                       , ci.name
                       , ci.ref
                       , if (ci.ref like 'CTON%' or ci.ref like 'CCA%', 'cartridge', 'consumable') consumable_type
                       , c.locations_id_field
                       , GROUP_CONCAT(c.id SEPARATOR ', ') cartridge_ids
                       , l.name location_name
                       , l.completename location_completename
                       , l.locations_id location_parent_id
                       , c.printers_id
                       , c.suppliers_id_field
                       , c.plugin_fields_cartridgeitemtypedropdowns_id
                  FROM glpi_cartridgeitems ci
                  LEFT JOIN glpi_plugin_iservice_cartridges c ON c.cartridgeitems_id = ci.id $used_condition
                  LEFT JOIN glpi_locations l ON l.id = c.locations_id_field
                  JOIN glpi_plugin_iservice_consumables_tickets ct ON ct.amount > 0 AND ct.new_cartridge_ids LIKE CONCAT('%|', c.id, '|%')
                  WHERE $supplier_condition $location_condition $model_condition $date_condition
                    AND c.date_use IS null AND c.date_out IS null AND c.printers_id = 0
                  GROUP BY c.cartridgeitems_id, COALESCE(c.locations_id_field, 0), c.printers_id
                  ";

        if (empty($options['order_by'])) {
            $query .= "ORDER BY ci.name, ci.ref";
        } else {
            $query .= "ORDER BY $options[order_by]";
        }

        return PluginIserviceDB::getQueryResult($query, '_');
    }

    public static function getCartridgesDropdownOptions($ticket, $options = []): array
    {
        $compatible_cartridges = self::getCompatiblesForTicket($ticket);
        if (empty($compatible_cartridges)) {
            return [];
        }

        $options = [];
        foreach ($compatible_cartridges as $compatible_cartridge) {
            $options[$compatible_cartridge['id']] = sprintf(__('%1$s (%2$s)'), sprintf(__('%1$s - %2$s'), $compatible_cartridge["name"], $compatible_cartridge["ref"]), $compatible_cartridge["cpt"]);
        }

        return $options;
    }

    public static function getCompatiblesForTicket($ticket, array $dropdown_options = []): bool|array
    {
        if (!$ticket instanceof PluginIserviceTicket) {
            $ticket_id = ($ticket instanceof Ticket) ? $ticket->getID() : intval($ticket);
            $ticket    = new PluginIserviceTicket();
            if (!$ticket->getFromDB($ticket_id)) {
                return [];
            }
        }

        if (!isset($ticket->fields['items_id']['Printer'][0])) {
            $ticket->fields['items_id']['Printer'][0] = $ticket->getFirstPrinter()->getID();
        }

        if (!isset($ticket->fields['_suppliers_id_assign'])) {
            $ticket->fields['_suppliers_id_assign'] = $ticket->getFirstAssignedPartner()->getID();
        }

        if ($ticket->fields['items_id']['Printer'][0] > 0) {
            return self::getCompatiblesForPrinterId($ticket->fields['items_id']['Printer'][0], $dropdown_options);
        } else {
            return self::getCompatiblesForSupplierId($ticket->fields['_suppliers_id_assign'], $dropdown_options);
        }
    }

    public static function getCompatiblesForPrinterId($printer_id, $dropdown_options = []): bool|array
    {
        $printer = new Printer();
        if (!$printer->getFromDB($printer_id)) {
            return false;
        }

        if (!empty($dropdown_options['used']) && is_array($dropdown_options['used'])) {
            $used_condition = " AND c.id NOT in (" . implode(',', $dropdown_options['used']) . ")";
        } else {
            $used_condition = '';
        }

        $query = "SELECT COUNT(*) AS cpt
                      , ci.id
                      , ci.name
                      , ci.ref
                  FROM glpi_cartridgeitems ci
                  INNER JOIN glpi_cartridgeitems_printermodels cip ON cip.cartridgeitems_id = ci.id
                  LEFT JOIN glpi_plugin_iservice_cartridges c ON c.cartridgeitems_id = ci.id AND c.suppliers_id_field IS NULL $used_condition
                  WHERE ci.is_deleted = 0 AND cip.printermodels_id = '" . $printer->fields["printermodels_id"] . "'
                  GROUP BY ci.id
                  ";

        if (empty($dropdown_options['order_by'])) {
            $query .= "ORDER BY ci.name, ci.ref";
        } else {
            $query .= "ORDER BY $dropdown_options[order_by]";
        }

        return PluginIserviceDB::getQueryResult($query);
    }

    public static function getCompatiblesForSupplierId($supplier_id, array $dropdown_options = []): bool|array
    {
        if (isset($dropdown_options['used']) && !empty($dropdown_options['used']) && is_array($dropdown_options['used'])) {
            $used_condition = " AND c.id NOT in (" . implode(',', $dropdown_options['used']) . ")";
        } else {
            $used_condition = '';
        }

        $query = "SELECT COUNT(*) AS cpt, ci.ref AS ref, ci.name AS name, ci.id
                  FROM glpi_cartridgeitems ci
                  LEFT JOIN glpi_plugin_iservice_cartridges c ON c.cartridgeitems_id = ci.id AND c.suppliers_id_field IS NULL $used_condition
                  INNER JOIN glpi_cartridgeitems_printermodels cip ON cip.cartridgeitems_id = ci.id
                  INNER JOIN glpi_printers p ON p.printermodels_id = cip.printermodels_id
                  INNER JOIN glpi_infocoms ic ON ic.items_id = p.id AND itemtype = 'Printer'
                  INNER JOIN glpi_suppliers s ON s.id = ic.suppliers_id
                  WHERE ci.is_deleted = 0 AND s.id = $supplier_id
                  GROUP BY ci.id
                  ";

        if (empty($dropdown_options['order_by'])) {
            $query .= "ORDER BY ci.name, ci.ref";
        } else {
            $query .= "ORDER BY $dropdown_options[order_by]";
        }

        return PluginIserviceDB::getQueryResult($query);
    }

    public static function getForPrinterAtSupplier($printer_id, $supplier_id): array|bool
    {
        if (empty($printer_id) || empty($supplier_id)) {
            return [];
        }

        $query = "SELECT COUNT(c.id) AS cpt
                    , ci.id
                    , ci.name
                    , ci.ref
                    , c.date_use
                    , MAX(c.pages_use_field) pages_use_field
                    , MAX(c.pages_color_use_field) pages_color_use_field
                  FROM glpi_plugin_iservice_cartridges c
                  INNER JOIN glpi_cartridgeitems ci ON ci.id = c.cartridgeitems_id
                  INNER JOIN glpi_cartridgeitems_printermodels cipm ON cipm.cartridgeitems_id = ci.id
                  INNER JOIN glpi_printers p ON p.printermodels_id = cipm.printermodels_id AND p.id = $printer_id
                  WHERE date_out IS NULL
                    AND FIND_IN_SET (c.suppliers_id_field, (SELECT group_field FROM glpi_plugin_fields_suppliersuppliercustomfields WHERE items_id = $supplier_id))
                    AND (c.printers_id = $printer_id OR c.printers_id < 1 OR c.printers_id IS NULL)
                  GROUP BY c.date_use, c.cartridgeitems_id
                  ORDER BY ref";

        return PluginIserviceDB::getQueryResult($query, '_');
    }

    public static function searchChangeableById($needle, $haystack): bool|int
    {
        if (false !== ($index = array_search($needle, $haystack))) {
            return $index;
        }

        $cartridgeitem = new PluginIserviceCartridgeItem();
        if (!$cartridgeitem->getFromDB($needle) || count($haystack) < 1) {
            return false;
        }

        $hMarfaCode                 = $cartridgeitem->fields['ref'];
        $possible_cartridgeitem_ids = implode(',', $haystack);
        for ($i = 15; $i > 0; $i--) {
            $cartrdge_items = $cartridgeitem->find("id IN ($possible_cartridgeitem_ids) AND LEFT(ref, $i) = '" . substr($hMarfaCode, 0, $i) . "'");
            if (count($cartrdge_items) > 1) {
                return false;
            } elseif (count($cartrdge_items) === 1) {
                return array_search(array_keys($cartrdge_items)[0], $haystack);
            }
        }

        return false;
    }

    public static function dropdownPrintersForCartridge($cartridge): string|bool|null
    {
        if (!($cartridge instanceof Cartridge)) {
            $cartridge_id = intval($cartridge);
            $cartridge    = new PluginIserviceCartridge();
            if (!$cartridge->getFromDB($cartridge_id)) {
                return null;
            }
        }

        $cartridge_location = new Location();
        $cartridge_location->getFromDB($cartridge->fields['locations_id_field']);

        if (isset($cartridge_location->fields['locations_id']) && $cartridge_location->fields['locations_id'] > 0) {
            $location_condition = 'AND p.locations_id in (SELECT id FROM glpi_locations where locations_id = ' . $cartridge_location->fields['locations_id'] . ')';
        } else {
            $location_condition = 'AND (l.id IS null OR l.locations_id IS null OR l.locations_id = 0)';
        }

        $printer_condition_select = "
            SELECT distinct(p.id)
            FROM glpi_printers p
            LEFT JOIN glpi_locations l ON l.id = p.locations_id
            LEFT JOIN glpi_cartridgeitems_printermodels cp ON cp.printermodels_id = p.printermodels_id
            LEFT JOIN glpi_infocoms ic ON ic.items_id = p.id AND ic.itemtype = 'Printer'
            WHERE cp.cartridgeitems_id = {$cartridge->fields['cartridgeitems_id']} AND FIND_IN_SET (ic.suppliers_id, (SELECT group_field FROM glpi_plugin_fields_suppliersuppliercustomfields WHERE items_id = {$cartridge->fields['suppliers_id_field']})) $location_condition";

        return Dropdown::show(
            'PluginIservicePrinter', [
                'comments' => false,
                'condition' => ["glpi_plugin_iservice_printers.id IN ($printer_condition_select)"],
            ]
        );
    }

    public static function getIdsByMercuryCode($mercury_code): array
    {
        $cartridge_item_ids    = [];
        $cartridge_customfield = new PluginFieldsCartridgeitemcartridgeitemcustomfield();
        foreach ($cartridge_customfield->find(['mercury_code_field' => $mercury_code]) as $ccf) {
            $cartridge_item_ids[] = $ccf['items_id'];
        }

        return $cartridge_item_ids;
    }

    public function getPrinterModelsIds(): array
    {
        $id = $this->fields['id'] ?? null;

        if (empty($id)) {
            return [];
        }

        $ids = PluginIserviceDB::getQueryResult(
            "SELECT printermodels_id
             FROM glpi_cartridgeitems_printermodels
             WHERE cartridgeitems_id = $id"
        ) ?? [];

        return array_column($ids, 'printermodels_id');
    }

}

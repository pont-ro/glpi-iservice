<?php

// Direct access to file
use GlpiPlugin\Iservice\Utils\ToolBox as PluginIserviceToolbox;

if (strpos($_SERVER['PHP_SELF'], "getUsedConsumablesDropdownValuesForTicket.php")) {
    include('../inc/includes.php');
    header("Content-Type: application/json; charset=UTF-8");
    Html::header_nocache();
} elseif (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

Session::checkLoginUser();

$printerId = ($_SESSION['glpicondition'][PluginIserviceToolbox::getInputVariable('condition')] ?? [0])[0];
$searchText = PluginIserviceToolbox::getInputVariable('searchText');
$where = $searchText ? "where c.name like '%$searchText%'" : "";

$consumables = PluginIserviceDB::getQueryResult("
    WITH printer_model AS (
        SELECT printermodels_id FROM glpi_printers WHERE id = $printerId
    ),
    target_consumables AS (
        SELECT DISTINCT ct.plugin_iservice_consumables_id
        FROM glpi_plugin_iservice_consumables_tickets ct
        JOIN glpi_items_tickets it ON it.tickets_id = ct.tickets_id AND it.itemtype = 'Printer' AND it.items_id = $printerId
        WHERE ct.plugin_iservice_consumables_id LIKE 'CCA%' OR ct.plugin_iservice_consumables_id LIKE 'CTO%'
    ),
    compatible_models AS (
        SELECT DISTINCT cipm.printermodels_id
        FROM glpi_cartridgeitems_printermodels cipm
        JOIN glpi_cartridgeitems ci ON ci.id = cipm.cartridgeitems_id
        JOIN target_consumables tc ON tc.plugin_iservice_consumables_id = ci.ref
    )
    SELECT 
        ct.plugin_iservice_consumables_id AS cid,
        c.name,
        GROUP_CONCAT(DISTINCT(CASE WHEN it.items_id <> $printerId THEN CONCAT('  - ', p.serial, '(', pm.name, ')') END) SEPARATOR '\n') AS printers,
        MAX(CASE WHEN p.printermodels_id = (SELECT printermodels_id FROM printer_model) THEN ct.plugin_iservice_consumables_id END) AS used
    FROM glpi_plugin_iservice_consumables_tickets ct
    JOIN glpi_plugin_iservice_consumables c ON c.id = ct.plugin_iservice_consumables_id
    JOIN glpi_plugin_fields_ticketticketcustomfields tcf ON tcf.items_id = ct.tickets_id 
                                                        AND tcf.itemtype = 'Ticket'
                                                        AND tcf.plugin_fields_ticketexporttypedropdowns_id = 1
                                                        AND tcf.effective_date_field > '2018-01-01'
    JOIN glpi_items_tickets it ON it.tickets_id = ct.tickets_id AND it.itemtype = 'Printer'
    JOIN glpi_printers p ON p.id = it.items_id AND p.is_deleted = 0
    JOIN glpi_printermodels pm ON pm.id = p.printermodels_id
    JOIN compatible_models cm ON cm.printermodels_id = p.printermodels_id
    $where
    GROUP BY ct.plugin_iservice_consumables_id
");

$usedConsumables = [];
$compatibleConsumables = [];

foreach ($consumables as $consumable) {
    if ($consumable['used']) {
        $usedConsumables[] = [
            'id' => $consumable['cid'],
            'text' => $consumable['name'],
            'title' => $consumable['name'],
        ];
    } else {
        $compatibleConsumables[] = [
            'id' => $consumable['cid'],
            'text' => $consumable['name'] . ' (' . _t('On similar devices') . ')',
            'title' => $consumable['name'] . "\n" . _t('On similar devices') . ":\n" . $consumable['printers'],
        ];
    }
}

echo json_encode([
    'results' => [
        [
            'text' => _t('On this device type'),
            'children' => $usedConsumables,
        ],
        [
            'text' => _t('On similar devices'),
            'children' => $compatibleConsumables,
        ]
    ],
    'count' => 2,
]);
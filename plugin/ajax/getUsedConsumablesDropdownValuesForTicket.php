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
    select 
        ct.plugin_iservice_consumables_id cid
      , c.name
      , group_concat(case when it.items_id <> $printerId then concat(p.serial, ' [', pm.name, ']') end separator '\n') printers
      , a.cod used
    from glpi_plugin_iservice_consumables c
    join glpi_plugin_iservice_consumables_tickets ct on ct.plugin_iservice_consumables_id = c.id
    join glpi_plugin_fields_ticketticketcustomfields tcf on tcf.itemtype = 'Ticket'
                                                        and tcf.items_id = ct.tickets_id
                                                        and tcf.plugin_fields_ticketexporttypedropdowns_id = 1
                                                        and tcf.effective_date_field > '2018-01-01'
    join glpi_items_tickets it on it.tickets_id = ct.tickets_id and it.itemtype = 'Printer'
    join glpi_printers p on p.id = it.items_id and p.is_deleted = 0 and p.printermodels_id in
    (
        select distinct cipm.printermodels_id
        from glpi_cartridgeitems_printermodels cipm
        join glpi_cartridgeitems ci on ci.id = cipm.cartridgeitems_id and ci.ref in (
            select distinct ct.plugin_iservice_consumables_id
            from glpi_plugin_iservice_consumables_tickets ct
            join glpi_items_tickets it on it.tickets_id = ct.tickets_id and it.itemtype = 'Printer' and it.items_id = $printerId
            where ct.plugin_iservice_consumables_id like 'CCA%' or ct.plugin_iservice_consumables_id like 'CTO%'
        )
    )
    join glpi_printermodels pm on pm.id = p.printermodels_id
    left join (
        select distinct ct.plugin_iservice_consumables_id cod
        from glpi_plugin_iservice_consumables_tickets ct
        join glpi_items_tickets it on it.tickets_id = ct.tickets_id and it.itemtype = 'Printer'
        join glpi_printers p
             on p.id = it.items_id
            and p.is_deleted = 0
            and p.printermodels_id = (select printermodels_id from glpi_printers where id = $printerId)
    ) a on a.cod = ct.plugin_iservice_consumables_id
    $where
    group by ct.plugin_iservice_consumables_id, a.cod
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
            'text' => $consumable['name'] . ' (' . _t('On compatible devices') . ')',
            'title' => $consumable['name'] . ' (' . _t('On compatible devices') . ': ' . $consumable['printers'] . ')',
        ];
    }
}

echo json_encode([
    'results' => [
        [
            'text' => _t('On this device'),
            'children' => $usedConsumables,
        ],
        [
            'text' => _t('On compatible devices'),
            'children' => $compatibleConsumables,
        ]
    ],
    'count' => 2,
]);
<?php

// Imported from iService2, needs refactoring. Original file: "manageCartridge.php".
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

// Direct access to file
if (strpos($_SERVER['PHP_SELF'], "manageCartridge.php")) {
    include '../inc/includes.php';
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkLoginUser();

$operations = [
    'remove_from_partner' => 'remove_from_supplier',
    'force_supplier' => 'force_supplier',
    'force_location' => 'force_location',
    'change_location' => 'change_location',
    'remove_from_location' => 'remove_from_location',
    'remove_from_printer' => 'remove_from_printer',
    'use' => 'use',
    'force_type' => 'force_type',
    'delete_cartridge' => 'delete_cartridge'
];

$id          = IserviceToolBox::getInputVariable('id');
$type_id     = IserviceToolBox::getInputVariable('type_id');
$location_id = IserviceToolBox::getInputVariable('location_id');
$supplier_id = IserviceToolBox::getInputVariable('supplier_id');

$cartridge = new PluginIserviceCartridge();
if (!$cartridge->getFromDB($id)) {
    die(sprintf(_t('Invalid cartridge id: %d'), $id));
}

$operation = IserviceToolBox::getInputVariable('operation');
if (!array_key_exists($operation, $operations)) {
    die(sprintf(_t('Invalid operation: %s'), $operation));
}

switch ($operations[$operation]) {
case 'remove_from_supplier':
    if ($cartridge->fields['printers_id'] > 0) {
        die(sprintf(_t('Cartridge %d is already installed on a printer'), $id));
    }

    $consumable_ticket = new PluginIserviceConsumable_Ticket();
    $consumable_tickets = $consumable_ticket->find(["amount > 0 AND new_cartridge_ids LIKE '%|$id|%'"]);

    if (count($consumable_tickets) > 0) {
        $ticket_id = array_shift($consumable_tickets)['tickets_id'];
        die("Remove cartridge by removing it from ticket $ticket_id");
    }

    if ($cartridge->delete(['id' => $id])) {
        die(IserviceToolBox::RESPONSE_OK);
    }
    break;
case 'force_supplier':
    $update_data = ['id' => $id, "suppliers_id_field" => $supplier_id];
    if ($cartridge->update($update_data)) {
        die(IserviceToolBox::RESPONSE_OK);
    }
    break;
case 'change_location':
    if ($cartridge->fields['printers_id'] > 0) {
        die(sprintf(_t('Cartridge %d is already installed on a printer'), $id));
    }

case 'force_location':
    $update_data = ['id' => $id, "locations_id_field" => empty($location_id) ? '0' : $location_id];
    if (!empty($supplier_id)) {
        $update_data["suppliers_id_field"] = $supplier_id;
    }

    if ($cartridge->update($update_data)) {
        die(IserviceToolBox::RESPONSE_OK);
    }
    break;
case 'remove_from_printer':
    if ($cartridge->fields['printers_id'] < 1) {
        die(sprintf(_t('Cartridge %d is not installed on a printer'), $id));
    }

    if (!empty($cartridge->fields['date_out'])) {
        die(sprintf(_t('Cartridge %d is empty, it is not installed on a printer'), $id));
    }

    if ($cartridge->update(['id' => $id, 'printers_id' => '0', 'date_use' => 'NULL', 'date_out' => 'NULL', 'pages_use_field' => 0, 'pages_color_use_field' => 0])) {
        global $DB;
        if (!$DB->query("delete from glpi_iservice_cartridges_tickets where cartridges_id = $id")) {
            (__("Could not remove cartridge from the installer ticket."));
        }

        die(IserviceToolBox::RESPONSE_OK);
    }
    break;
case 'use':
    if ($cartridge->fields['printers_id'] < 1) {
        die(sprintf(_t('Cartridge %d is not installed on a printer'), $id));
    }

    if (!empty($cartridge->fields['date_out'])) {
        die(sprintf(_t('Cartridge %d is empty, it is not installed on a printer'), $id));
    }

    $counter_black = IserviceToolBox::getInputVariable('counter_black');
    $counter_color = IserviceToolBox::getInputVariable('counter_color');
    $install_date  = IserviceToolBox::getInputVariable('install_date');
    if ($cartridge->update(
        [
            'id' => $id,
            'date_out' => $install_date,
            'pages_out_field' => $counter_black,
            'pages_color_out_field' => $counter_color,
            'printed_pages_field' => $cartridge->fields['printed_pages_field'] + $counter_black - $cartridge->fields['pages_use_field'],
            'printed_pages_color_field' => $cartridge->fields['printed_pages_field'] + $counter_color - $cartridge->fields['pages_color_use_field'],
        ]
    )
    ) {
        die(IserviceToolBox::RESPONSE_OK);
    }
    break;
case 'force_type':
    $update_data = ['id' => $id, "plugin_fields_cartridgeitemtypedropdowns_id" => $type_id];
    if ($cartridge->update($update_data)) {
        die(IserviceToolBox::RESPONSE_OK);
    }
    break;
case 'delete_cartridge':
    if (!$cartridge->delete(['id' => $id])) {
        die(printf(_t('Could not delete cartridge from the database.')));
    }
    die(IserviceToolBox::RESPONSE_OK);
default:
    die(sprintf(_t('Operation not implemented: %s'), $operations[$operation]));
}

die(sprintf(_t('Could not complete operation %s for cartridge %d'), $operations[$operation], $id));

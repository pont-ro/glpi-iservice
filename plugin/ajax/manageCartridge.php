<?php

// Imported from iService2, needs refactoring. Original file: "manageCartridge.php".
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

// Direct access to file
if (strpos($_SERVER['PHP_SELF'], "manageCartridge.php")) {
    include '../../../inc/includes.php';
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
    die(sprintf(__("Invalid cartridge id: %d", "iservice"), $id));
}

$operation = IserviceToolBox::getInputVariable('operation');
if (!array_key_exists($operation, $operations)) {
    die(sprintf(__("Invalid operation: %s", "iservice"), $operation));
}

switch ($operations[$operation]) {
case 'remove_from_supplier':
    if ($cartridge->fields['printers_id'] > 0) {
        die(sprintf(__("Cartridge %d is already installed on a printer", "iservice"), $id));
    }

    if ($cartridge->delete(['id' => $id])) {
        $consumable_ticket = new PluginIserviceConsumable_Ticket();
        $condition         = "amount > 0 AND new_cartridge_ids LIKE '%|$id|%'";
        foreach ($consumable_ticket->find($condition) as $row) {
            $new_cartridge_ids = str_replace(["|$id|,", ",|$id|"], "", $row['new_cartridge_ids']);
            if ($row['new_cartridge_ids'] === "|$id|") {
                $new_cartridge_ids = 'NULL';
            }

            $new_amount = $row['amount'] > 0 ? $row['amount'] - 1 : $row['amount'] + 1;
            $consumable_ticket->update(['id' => $row['id'], 'amount' => $new_amount, 'new_cartridge_ids' => $new_cartridge_ids]);
        }

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
        die(sprintf(__("Cartridge %d is already installed on a printer", "iservice"), $id));
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
        die(sprintf(__("Cartridge %d is not installed on a printer", "iservice"), $id));
    }

    if (!empty($cartridge->fields['date_out'])) {
        die(sprintf(__("Cartridge %d is empty, it is not installed on a printer", "iservice"), $id));
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
        die(sprintf(__("Cartridge %d is not installed on a printer", "iservice"), $id));
    }

    if (!empty($cartridge->fields['date_out'])) {
        die(sprintf(__("Cartridge %d is empty, it is not installed on a printer", "iservice"), $id));
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
        die(printf(__("Could not delete cartridge from the database.", "iservice")));
    }
    die(IserviceToolBox::RESPONSE_OK);
default:
    die(sprintf(__("Operation not implemented: %s", "iservice"), $operations[$operation]));
}

die(sprintf(__("Could not complete operation %s for cartridge %d", "iservice"), $operations[$operation], $id));

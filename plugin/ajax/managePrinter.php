<?php

// Imported from iService2, needs refactoring.
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

// Direct access to file.
if (strpos($_SERVER['PHP_SELF'], "managePrinter.php")) {
    include '../inc/includes.php';
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkLoginUser();

$operations = [
    'set_dba' => 'set_dba',
    'set_dca' => 'set_dca',
    'set_ucbk' => 'set_ucbk',
    'set_ucc' => 'set_ucc',
    'set_ucm' => 'set_ucm',
    'set_ucy' => 'set_ucy',
    'set_usageaddressfield' => 'set_usageaddressfield',
    'enable_em' => 'enable_em',
    'exclude_from_em' => 'exclude_from_em',
    'snooze_read_check' => 'snooze_read_check',
    'get_last_invoices_dropdown' => 'get_last_invoices_dropdown',
    'clear_color_coefficients' => 'clear_color_coefficients',
    'set_color_coefficients' => 'set_color_coefficients',
    'set_no_invoice' => 'set_no_invoice',
];

$id      = IserviceToolBox::getInputVariable('id');
$value   = IserviceToolBox::getInputVariable('value');
$average = IserviceToolBox::getInputVariable('average');

$printer              = new Printer();
$printer_customfields = new PluginFieldsPrinterprintercustomfield();
if (!PluginIserviceDB::populateByItemsId($printer_customfields, $id)) {
    die(sprintf(__("Invalid printer id: %d", "iservice"), $id));
}

$operation = IserviceToolBox::getInputVariable('operation');
if (!array_key_exists($operation, $operations)) {
    die(sprintf(__("Invalid operation: %s", "iservice"), $operation));
}

switch ($operations[$operation]) {
case 'set_dba':
case 'set_dca':
    $printer->check($id, UPDATE);
    $update_field = ($operations[$operation] == 'set_dca') ? 'daily_color_average_field' : 'daily_bk_average_field';
    if ($printer_customfields->update(
        [
            $printer_customfields->getIndexName() => $printer_customfields->getID(),
            $update_field => $average,
        ]
    )
    ) {
        die($average);
    }
    break;
case 'set_ucbk':
case 'set_ucc':
case 'set_ucm':
case 'set_ucy':
    $printer->check($id, UPDATE);
    $update_fields = [
        'set_ucbk' => 'uc_bk_field',
        'set_ucc' => 'uc_cyan_field',
        'set_ucm' => 'uc_magenta_field',
        'set_ucy' => 'uc_yellow_field',
    ];
    if ($printer_customfields->update(
        [
            $printer_customfields->getIndexName() => $printer_customfields->getID(),
            $update_fields[$operations[$operation]] => $average,
        ]
    )
    ) {
        die($average);
    }
    break;
case 'clear_color_coefficients':
    $printer->check($id, UPDATE);
    if ($printer_customfields->update(
        [
            $printer_customfields->getIndexName() => $printer_customfields->getID(),
            'daily_color_average_field' => 0,
            'uc_cyan_field' => 0,
            'uc_magenta_field' => 0,
            'ucyfield' => 0,
        ]
    )
    ) {
        die(IserviceToolBox::RESPONSE_OK);
    }
    break;
case 'set_color_coefficients':
    $printer->check($id, UPDATE);
    if ($printer_customfields->update(
        [
            $printer_customfields->getIndexName() => $printer_customfields->getID(),
            'daily_color_average_field' => $printer_customfields->fields['daily_bk_average_field'] ?: 100,
            'uc_cyan_field' => 1,
            'uc_magenta_field' => 1,
            'uc_yellow_field' => 1,
        ]
    )
    ) {
        die(IserviceToolBox::RESPONSE_OK);
    }
    break;
case 'set_usageaddressfield':
    if ($printer_customfields->update(
        [
            $printer_customfields->getIndexName() => $printer_customfields->getID(),
            'usage_address_field' => $value,
        ]
    )
    ) {
        die(IserviceToolBox::RESPONSE_OK);
    }
    break;
case 'enable_em':
case 'set_no_invoice':
case 'exclude_from_em':
case 'snooze_read_check':
    $update_field = [
        'enable_em' => 'em_field',
        'set_no_invoice' => 'no_invoice_field',
        'exclude_from_em' => 'disable_em_field',
        'snooze_read_check' => 'snooze_read_check_field',
    ][$operations[$operation]];
    $update_value = [
        'enable_em' => 1,
        'set_no_invoice' => $value,
        'exclude_from_em' => 1,
        'snooze_read_check' => date('Y-m-d', strtotime('+' . intval(IserviceToolBox::getInputVariable('snooze', 1)) . 'days')),
    ][$operations[$operation]];
    if ($printer_customfields->update(
        [
            $printer_customfields->getIndexName() => $printer_customfields->getID(),
            $update_field => $update_value,
        ]
    )
    ) {
        die(IserviceToolBox::RESPONSE_OK);
    }
    break;
case 'get_last_invoices_dropdown':
    $last_invoices_data = PluginIserviceDB::getQueryResult(
        "
            select distinct fr.nrfac, fa.datafac 
            from glpi_printers p 
            join hmarfa_facrind fr on fr.descr like CONCAT('%', p.serial, '%') and fr.codmat like 'S%'
            join hmarfa_facturi fa on fa.nrfac = fr.nrfac
            where p.id = $id
            order by fr.nrfac desc
            limit " . IserviceToolBox::getInputVariable('limit', 5)
    );
    $result             = "<select id='last-invoices-$id'>";
    $search_dir         = PluginIservicePendingEmailUpdater::getInvoiceSearchFolder();
    $selected           = IserviceToolBox::getInputVariable('selected');
    foreach ($last_invoices_data as $last_invoice_data) {
        foreach (glob($search_dir . "/I$last_invoice_data[nrfac]*.*") as $invoice) {
            $attachment = basename($invoice);
            $isSelected = ($selected === "$last_invoice_data[nrfac]-$attachment") ? 'selected' : '';
            $result    .= "<option value='$last_invoice_data[nrfac]-$attachment' $isSelected>$last_invoice_data[datafac] - $attachment</option>";
            break;
        }
    }

    $result .= "</select>";
    echo $result;
    die;
default:
    die(sprintf(__("Operation not implemented: %s", "iservice"), $operations[$operation]));
}

die(sprintf(__("Could not complete operation %s for printer %d", "iservice"), $operations[$operation], $id));

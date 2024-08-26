<?php

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

// Direct access to file
if (strpos($_SERVER['PHP_SELF'], "setContractSupplier.php")) {
    include '../inc/includes.php';
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkLoginUser();

$contract_id = IserviceToolBox::getInputVariable('contract_id');
$supplier_id = IserviceToolBox::getInputVariable('supplier_id');

if (!$contract_id || !$supplier_id) {
    die(sprintf(_t('Invalid contract id: %d or supplier id: %d'), $contract_id, $supplier_id));
}

$contract_supplier = new Contract_Supplier();
if ($contract_supplier->add(
    [
        'add' => 'add',
        'contracts_id' => $contract_id,
        'suppliers_id' => $supplier_id
    ]
)
) {
    die(IserviceToolBox::RESPONSE_OK);
} else {
    die(sprintf(_t('Could not add supplier %d to contract %d'), $supplier_id, $contract_id));
}

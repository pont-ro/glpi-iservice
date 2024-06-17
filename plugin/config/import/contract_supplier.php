<?php
return [
    'itemTypeClass' => Contract_Supplier::class,
    'oldTable'      => 'glpi_contracts_suppliers',
    'foreignKeys'   => [
        'suppliers_id' => 'Supplier',
        'contracts_id' => 'Contract',
    ],
];

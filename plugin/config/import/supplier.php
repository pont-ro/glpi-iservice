<?php
return [
    'itemTypeClass'   => Supplier::class,
    'oldTable'        => 'glpi_suppliers',
    'checkValues'     => [
        'entities_id' => 0,
    ],
    'foreignKeys'     => [
        'suppliertypes_id' => 'SupplierType'
    ]
];

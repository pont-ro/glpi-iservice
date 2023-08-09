<?php
return [
    'itemTypeClass' => Contact_Supplier::class,
    'oldTable'      => 'glpi_contacts_suppliers',
    'foreignKeys'   => [
        'suppliers_id' => 'Supplier',
        'contacts_id'  => 'Contact',
    ],
];

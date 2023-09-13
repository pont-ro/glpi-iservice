<?php
return [
    'itemTypeClass'   => CartridgeItem::class,
    'oldTable'        => 'glpi_cartridgeitems',
    'identifierField' => ['name', 'ref', 'is_deleted'],
    'foreignKeys'     => [
        'locations_id'          => 'Location',
        'cartridgeitemtypes_id' => 'CartridgeItemType',
        'manufacturers_id'      => 'Manufacturer',
        'users_id_tech'         => 'User',
        'groups_id_tech'        => 'Group',
    ],
];

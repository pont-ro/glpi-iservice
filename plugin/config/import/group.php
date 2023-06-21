<?php
return [
    'itemTypeClass' => Group::class,
    'oldTable'      => 'glpi_groups',
    'selfReferences' => [
        'groups_id'
    ],
    'checkValues'   => [
        'entities_id' => 0,
    ],
];

<?php
return [
    'itemTypeClass' => Location::class,
    'oldTable'      => 'glpi_locations',
    'selfReferences' => [
        'locations_id'
    ],
    'checkValues'   => [
        'entities_id' => 0
    ],
];

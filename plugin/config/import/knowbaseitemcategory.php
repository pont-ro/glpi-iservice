<?php
return [
    'itemTypeClass'   => KnowbaseItemCategory::class,
    'oldTable'        => 'glpi_knowbaseitemcategories',
    'identifierField' => 'name',
    'selfReferences'  => [
        'knowbaseitemcategories_id'
    ],
    'checkValues'     => [
        'entities_id' => 0,
    ],
];

<?php
return [
    'itemTypeClass'  => ITILCategory::class,
    'oldTable'       => 'glpi_itilcategories',
    'checkValues'    => [
        'entities_id' => 0,
    ],
    'selfReferences' => [
        'itilcategories_id'
    ],
    'foreignKeys'    => [
        'knowbaseitemcategories_id'   => 'KnowbaseItemCategory',
        'tickettemplates_id_incident' => 'TicketTemplate',
        'tickettemplates_id_demand'   => 'TicketTemplate',
    ]
];

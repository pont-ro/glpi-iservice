<?php
return [
    'itemTypeClass'   => State::class,
    'oldTable'        => 'glpi_states',
    'identifierField' => 'name',
    'selfReferences'  => [
        'states_id',
    ],
];

<?php
return [
    'itemTypeClass' => Contract::class,
    'oldTable'      => 'glpi_contracts',
    'foreignKeys'   => [
        'contracttypes_id' => 'ContractType',
        'states_id'        => 'State',
    ],
];

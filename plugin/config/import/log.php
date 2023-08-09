<?php
return [
    'itemTypeClass'            => Log::class,
    'oldTable'                 => 'glpi_logs',
    'foreignKeys'              => [
        'items_id' => [
            'dependsFrom' => 'itemtype',
        ],
    ],
    'handleMissingForeignKeys' => [
        'items_id' => ['add' => 100000000],
    ],
];

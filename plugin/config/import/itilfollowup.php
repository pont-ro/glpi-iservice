<?php
return [
    'itemTypeClass' => ITILFollowup::class,
    'oldTable'      => 'glpi_itilfollowups where content != ""',
    'forceValues'   => [
        'requesttypes_id' => 0,
        '_disablenotif'   => 1,
    ],
    'foreignKeys'   => [
        'items_id'        => [
            'dependsFrom' => 'itemtype',
        ],
        'users_id'        => 'User',
        'users_id_editor' => 'User',
    ]
];

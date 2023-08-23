<?php
return [
    'itemTypeClass'   => UserEmail::class,
    'oldTable'        => 'glpi_useremails',
    'clearCondition'  => "users_id not in (select id from glpi_users where name = 'glpi')",
    'foreignKeys'     => [
        'users_id'    => 'User',
    ],
];

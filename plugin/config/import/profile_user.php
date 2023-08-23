<?php
return [
    'itemTypeClass'   => Profile_User::class,
    'oldTable'        => 'glpi_profiles_users',
    'clearCondition'  => "users_id not in (select id from glpi_users where name = 'glpi')",
    'foreignKeys'     => [
        'profiles_id' => 'Profile',
        'users_id'    => 'User',
    ],
];

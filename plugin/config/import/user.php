<?php
return [
    'itemTypeClass' => User::class,
    'oldTable'      => 'glpi_users',
    'clearCondition'  => "name != 'glpi'",
    'identifierField' => 'name',
    'foreignKeys'   => [
        'locations_id' => 'Location',
        'profiles_id' => 'Profile',
        'groups_id' => 'Group',
    ],
    'selfReferences' => [
        'users_id_supervisor'
    ],
    'checkValues'   => [
        'entities_id' => 0,
        'usertitles_id' => 0,
        'usercategories_id' => 0,
    ],
];

<?php
return [
    'itemTypeClass' => Reminder::class,
    'oldTable'      => 'glpi_reminders',
    'foreignKeys'   => [
        'users_id' => 'User',
    ]
];

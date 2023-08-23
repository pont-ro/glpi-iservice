<?php
return [
    'itemTypeClass' => Entity_Reminder::class,
    'oldTable'      => 'glpi_entities_reminders',
    'foreignKeys'   => [
        'reminders_id' => 'Reminder',
    ],
];

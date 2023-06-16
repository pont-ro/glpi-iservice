<?php
return [
    'itemTypeClass' => Reminder::class,
    'oldTable'      => 'glpi_reminders',
    'fields'        => [
        'uuid',
        'date',
        'users_id',
        'name',
        'text',
        'begin',
        'end',
        'is_planned',
        'date_mod',
        'state',
        'begin_view_date',
        'end_view_date',
        'date_creation'
    ]
];

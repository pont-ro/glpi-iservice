<?php
return [
    'Various data' => [
        'icon' => 'fa-fw ti ti-settings',
        'title' => __('Various data', 'iservice'),
        'items' => [
            'profile' => [
                'itemtype' => 'Profile',
                'label' => _n('Profile', 'Profiles', Session::getPluralNumber()),
            ],
            'group' => [
                'itemtype' => 'Group',
                'label' => _n('Group', 'Groups', Session::getPluralNumber()),
            ],
            'location' => [
                'itemtype' => 'Location',
                'label' => _n('Location', 'Locations', Session::getPluralNumber()),
            ],
            'user' => [
                'itemtype' => 'User',
                'label' => _n('User', 'Users', Session::getPluralNumber()),
            ],
            'reminder' => [
                'itemtype' => 'Reminder',
                'label' => _n('Reminder', 'Reminders', Session::getPluralNumber()),
            ],
        ]
    ],
    /*
    'printers' => [
        'icon' => 'fa-fw ti ti-printer',
        'title' => _n('Printer', 'Printers', Session::getPluralNumber()),
        'items' => [
            'printermodel' => [
                'itemtype' => 'PrinterModel',
                'label' => _n('Printer model', 'Printer models', Session::getPluralNumber()),
            ],
            'printer' => [
                'itemtype' => 'Printer',
                'label' => _n('Printer', 'Printers', Session::getPluralNumber()),
            ],
        ]
    ]
    /**/
];

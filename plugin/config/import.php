<?php
return [
    'various' => [
        'icon' => 'ti ti-settings',
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
    'suppliers' => [
        'icon' => 'ti ti-building-skyscraper',
        'title' => __('Supplier data', 'iservice'),
        'items' => [
            'supplier_types' => [
                'itemtype' => 'SupplierType',
                'label' => _n('Supplier type', 'Supplier types', Session::getPluralNumber()),
            ],
            'supplier' => [
                'itemtype' => 'Supplier',
                'label' => _n('Supplier', 'Suppliers', Session::getPluralNumber()),
            ],
            'supplier_customfields' => [
                'itemtype' => 'SupplierCustomfield',
                'label' => _n('Supplier Custom Field', 'Supplier Custom Fields', Session::getPluralNumber(), 'iservice'),
            ],
        ]
    ],
    'customfields' => [
        'icon' => 'fas fa-tasks',
        'title' => _n('Custom field', 'Custom fields', Session::getPluralNumber()),
        'items' => [
        ]
    ]
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

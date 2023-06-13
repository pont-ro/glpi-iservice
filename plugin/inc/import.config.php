<?php
return [
    'Various data' => [
        'icon' => 'fa-fw ti ti-settings',
        'title' => __('Various data', 'iservice'),
        'items' => [
            'location' => [
                'itemtype' => 'Location',
                'label' => _n('Location', 'Locations', Session::getPluralNumber()),
            ],
        ]
    ],
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
];

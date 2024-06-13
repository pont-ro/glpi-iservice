<?php

global $CFG_PLUGIN_ISERVICE;

return [
    'views' => [
        'title' => _n('View', 'Views', Session::getPluralNumber()),
        'icon'  => 'ti ti-columns',
        'classes' => [
            'GlpiPlugin\Iservice\Views\UnpaidInvoices',
            'GlpiPlugin\Iservice\Views\SkippedPayment',
            'GlpiPlugin\Iservice\Views\OutboundLots',
            'GlpiPlugin\Iservice\Views\Stock',
            'GlpiPlugin\Iservice\Views\StockLots',
            'GlpiPlugin\Iservice\Views\InboundLots',
            'GlpiPlugin\Iservice\Views\RouteManifest',
            'GlpiPlugin\Iservice\Views\PriceList',
        ],
    ],
    'specialViews' => [
        'title' => _n('Special View', 'Special Views', Session::getPluralNumber(), 'iservice'),
        'icon'  => 'ti ti-eye',
        'classes' => [
            'GlpiPlugin\Iservice\Views\Emaintenance',
            'GlpiPlugin\Iservice\Views\Evaluation',
            'GlpiPlugin\Iservice\Views\Extorders',
            'GlpiPlugin\Iservice\Views\GlobalReadCounter',
            'GlpiPlugin\Iservice\Views\Operations',
            'GlpiPlugin\Iservice\Views\Partners',
            'GlpiPlugin\Iservice\Views\PendingEmails',
            'GlpiPlugin\Iservice\Views\Reminders',
        ],
    ],
    'iService' => [
        'title'   => __('iService', 'iservice'),
        'icon'    => 'fas fa-cogs',
        'content' => [
            'printer' => [
                'title' => __('Inquiry', 'iservice'),
                'icon'  => 'fa-fw ti ti-plus',
                'page'  => "$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php",
                'roles' => ['super-admin','admin', 'tehnician', 'subtehnician'],
                'options' => [
                    'sortOrder' => 10,
                ],
            ],
            'aa' => [
                'title' => 'AA',
                'icon'  => 'fa-solid fa-file-contract',
                'page'  => "$CFG_PLUGIN_ISERVICE[root_doc]/front/printer.form.php",
                'roles' => ['super-admin','admin'],
                'options' => [
                    'sortOrder' => 30,
                ],
            ],
            'location' => [
                'title' => __('Location'),
                'icon'  => 'ti ti-map-pin',
                'page'  => "/front/location.php",
                'roles' => ['admin', 'tehnician'],
                'options' => [
                    'sortOrder' => 120,
                ],
            ],
            'location_add' => [
                'title' => '+ ' . __('Location'),
                'icon'  => 'ti ti-map-pin',
                'page'  => "/front/location.form.php",
                'roles' => ['admin', 'tehnician'],
                'options' => [
                    'sortOrder' => 130,
                ],
            ],
            'state' => [
                'title' => __('State'),
                'icon'  => 'ti ti-subtask',
                'page'  => "/front/state.php",
                'roles' => ['admin', 'tehnician'],
                'options' => [
                    'sortOrder' => 140,
                ],
            ],
            'state_add' => [
                'title' => '+ ' . __('Status'),
                'icon'  => 'ti ti-subtask',
                'page'  => "/front/state.form.php",
                'roles' => ['admin', 'tehnician'],
                'options' => [
                    'sortOrder' => 150,
                ],
            ],
        ],
        'classes' => [
            'GlpiPlugin\Iservice\Views\Tickets',
            'GlpiPlugin\Iservice\Views\Printers',
            'GlpiPlugin\Iservice\Views\Movements',
            'GlpiPlugin\Iservice\Views\Partners',
            'GlpiPlugin\Iservice\Views\Contracts',
            'GlpiPlugin\Iservice\Views\Intorders',
            'GlpiPlugin\Iservice\Views\Cartridges',
            'GlpiPlugin\Iservice\Views\Reminders',
            'PluginIserviceMonthlyPlan',
        ],
    ],
];

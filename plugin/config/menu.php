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
            'GlpiPlugin\Iservice\Views\HighTurnoverLots',
            'GlpiPlugin\Iservice\Views\RouteManifest',
            'GlpiPlugin\Iservice\Views\PriceList',
            'GlpiPlugin\Iservice\Views\Qrs',
            'GlpiPlugin\Iservice\Views\Vehicles',
            'GlpiPlugin\Iservice\Views\VehicleExpirables',
        ],
    ],
    'specialViews' => [
        'title' => _tn('Special View', 'Special Views', Session::getPluralNumber()),
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
        'title'   => _t('iService'),
        'icon'    => 'fas fa-cogs',
        'content' => [
            'printer' => [
                'title' => _t('Inquiry'),
                'icon'  => 'fa-fw ti ti-plus',
                'page'  => "$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php",
                'roles' => ['super-admin','admin', 'tehnician', 'subtehnician'],
                'options' => [
                    'sortOrder' => 10,
                ],
            ],
            'aa' => [
                'title' => 'AA',
                'icon'  => 'ti ti-file-code-2',
                'page'  => "$CFG_PLUGIN_ISERVICE[root_doc]/front/printer.form.php",
                'roles' => ['super-admin','admin', 'tehnician'],
                'options' => [
                    'sortOrder' => 30,
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

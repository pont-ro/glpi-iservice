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
            'GlpiPlugin\Iservice\SpecialViews\Emaintenance',
            'GlpiPlugin\Iservice\SpecialViews\Evaluation',
            'GlpiPlugin\Iservice\SpecialViews\Extorders',
            'GlpiPlugin\Iservice\SpecialViews\GlobalReadCounter',
            'GlpiPlugin\Iservice\SpecialViews\Operations',
            'GlpiPlugin\Iservice\SpecialViews\Partners',
            'GlpiPlugin\Iservice\SpecialViews\PendingEmails',
            'GlpiPlugin\Iservice\SpecialViews\Reminders',
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
                'roles' => ['super-admin','admin'],
            ],
        ],
        'classes' => [
            'GlpiPlugin\Iservice\SpecialViews\Printers',
            'GlpiPlugin\Iservice\SpecialViews\Cartridges',
            'GlpiPlugin\Iservice\SpecialViews\Tickets',
            'GlpiPlugin\Iservice\SpecialViews\Contracts',
            'GlpiPlugin\Iservice\SpecialViews\Movements',
            'PluginIserviceMonthlyPlan',
            'GlpiPlugin\Iservice\SpecialViews\Intorders',
        ],
    ],
];

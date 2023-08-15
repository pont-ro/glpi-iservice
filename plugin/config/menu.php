<?php

return [
    'views' => [
        'title' => _n('View', 'Views', Session::getPluralNumber()),
        'icon'  => 'ti ti-columns',
        'classes' => [
            'PluginIserviceMonthlyPlan',
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
        'icon'  => 'ti ti-layout-grid2',
        'classes' => [
            'GlpiPlugin\Iservice\SpecialViews\Emaintenance',
            'GlpiPlugin\Iservice\SpecialViews\Evaluation',
            'GlpiPlugin\Iservice\SpecialViews\Extorders',
            'GlpiPlugin\Iservice\SpecialViews\Intorders',
            'GlpiPlugin\Iservice\SpecialViews\GlobalReadCounter',
            'GlpiPlugin\Iservice\SpecialViews\Movements',
            'GlpiPlugin\Iservice\SpecialViews\Operations',
            'GlpiPlugin\Iservice\SpecialViews\Partners',
            'GlpiPlugin\Iservice\SpecialViews\PendingEmails',
        ],
    ],
];

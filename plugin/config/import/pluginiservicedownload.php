<?php
return [
    'itemTypeClass' => PluginIserviceDownload::class,
    'oldTable'      => 'glpi_plugin_iservice_downloads',
    'foreignKeys'     => [
        'items_id' => [
            'dependsFrom' => 'downloadtype',
            'itemTypes' => [
                PluginIserviceDownload::DOWNLOAD_TYPE_INVOICE => '',
                PluginIserviceDownload::DOWNLOAD_TYPE_INVOICE_CONFIRMED => '',
                PluginIserviceDownload::DOWNLOAD_TYPE_MAGIC_LINK => 'Supplier',
                PluginIserviceDownload::DOWNLOAD_TYPE_PARTNER_CONTACTED => 'Supplier',
            ]
        ]
    ]
];

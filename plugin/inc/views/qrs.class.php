<?php

namespace GlpiPlugin\Iservice\Views;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use PluginIserviceQr;

class Qrs extends View
{

    public static $rightname = 'plugin_iservice_view_qrs';

    public static $icon = 'ti ti-qrcode';

    public static function getName(): string
    {
        return _tn('QR Code', 'QR Codes', 2);
    }

    protected function getSettings(): array
    {
        if (IserviceToolBox::getInputVariable('mass_action_download') && !empty(IserviceToolBox::getArrayInputVariable('item')['Qrs'])) {
             PluginIserviceQr::downloadQrCodes(IserviceToolBox::getArrayInputVariable('item')['Qrs']);
        }

        if (IserviceToolBox::getInputVariable('mass_action_generate')) {
            PluginIserviceQr::downloadQrCodes(PluginIserviceQr::generateQrCodes(IserviceToolBox::getInputVariable('number_of_codes_to_generate', 1)));
        }

        if (IserviceToolBox::getInputVariable('mass_action_disconnect') && !empty(IserviceToolBox::getArrayInputVariable('item')['Qrs'])) {
             PluginIserviceQr::disconnectQrCodes(IserviceToolBox::getArrayInputVariable('item')['Qrs']);
        }

        if (IserviceToolBox::getInputVariable('mass_action_delete') && !empty(IserviceToolBox::getArrayInputVariable('item')['Qrs'])) {
            PluginIserviceQr::deleteQrCodes(IserviceToolBox::getArrayInputVariable('item')['Qrs']);
        }

        return [
            'name'          => self::getName(),
            'query'         => "
						SELECT
						    qrs.id
						    , qrs.create_date
						    , qrs.modify_date
						    , p.name_and_location as name
							, p.serial
							, qrs.code
						FROM glpi_plugin_iservice_qrs qrs
						LEFT JOIN glpi_plugin_iservice_printers p ON p.id = qrs.items_id
						WHERE 1
                            AND CAST( qrs.id AS CHAR) LIKE '[id]'
							AND qrs.create_date <= '[date]'
                            AND ((p.name_and_location is null AND '[name]' = '%%') OR p.name_and_location LIKE '[name]')                                              
                            AND ((p.serial  is null AND '[serial]' = '%%') OR p.serial LIKE '[serial]')
						    AND qrs.itemtype = 'Printer'
						",
            'default_limit' => 50,
            'filters'       => [
                'id' => [
                    'type' => self::FILTERTYPE_INT,
                    'caption' => 'Număr',
                    'format' => '%%%s%%',
                    'header' => 'id',
                ],
                'date'       => [
                    'type'           => self::FILTERTYPE_DATE,
                    'caption'        => '',
                    'format'         => 'Y-m-d 23:59:59',
                    'empty_value'    => date('Y-m-d'),
                    'header'         => 'date',
                    'header_caption' => '< ',
                ],
                'name' => [
                    'type'           => self::FILTERTYPE_TEXT,
                    'caption'        => 'Printer',
                    'format' => '%%%s%%',
                    'header'         => 'name',
                ],
                'serial' => [
                    'type'           => self::FILTERTYPE_TEXT,
                    'caption'        => 'Serial',
                    'format' => '%%%s%%',
                    'header'         => 'serial',
                ],
            ],
            'columns'       => [
                'id'       => [
                    'title'        => 'ID',
                    'default_sort' => 'DESC',
                    'link' => [
                        'title' => '[code]',
                        'href' => 'qr.form.php?code=[code]',
                        'target' => '_blank',
                    ],
                ],
                'name'      => [
                    'title'  => _t('Printer'),
                ],
                'serial'      => [
                    'title'  => _t('Serial'),
                ],
            ],
            'mass_actions' => [
                'download' => [
                    'caption' => _t('Download QR Codes'),
                    'action' => 'views.php?view=Qrs',
                    'new_tab' => false
                ],
                'generate' => [
                    'caption' => _t('Generate and download QR Codes'),
                    'action' => 'views.php?view=Qrs',
                    'new_tab' => false,
                    'prefix' => "<input type='number' name='number_of_codes_to_generate' value='50' min='1' max='1000'>",
                ],
                'disconnect' => [
                    'caption' => _t('Disconnect QR Codes'),
                    'action' => 'views.php?view=Qrs',
                    'new_tab' => false
                ],
                'delete' => [
                    'caption' => _t('Delete QR Codes'),
                    'action' => 'views.php?view=Qrs',
                    'new_tab' => false
                ],
            ],
        ];
    }

}

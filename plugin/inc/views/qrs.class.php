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
            PluginIserviceQr::generateQrCodes();
        }

        if (IserviceToolBox::getInputVariable('mass_action_disconnect') && !empty(IserviceToolBox::getArrayInputVariable('item')['Qrs'])) {
             PluginIserviceQr::disconnectQrCodes(IserviceToolBox::getArrayInputVariable('item')['Qrs']);
        }

        if (IserviceToolBox::getInputVariable('mass_action_delete') && !empty(IserviceToolBox::getArrayInputVariable('item')['Qrs'])) {
            PluginIserviceQr::deleteQrCodes(IserviceToolBox::getArrayInputVariable('item')['Qrs']);
        }

        global $CFG_GLPI;
        $iservice_front = $CFG_GLPI['root_doc'] . "/plugins/iservice/front/";

        return [
            'name'          => self::getName(),
            'query'         => "
						SELECT
						    qrs.id
						    , qrs.create_date
						    , qrs.modify_date
							, qrs.items_id
							, qrs.code
						FROM glpi_plugin_iservice_qrs qrs
						WHERE 1
                            AND CAST(id AS CHAR) LIKE '[id]'
							AND create_date <= '[date]'
                            AND (CAST(items_id AS CHAR) LIKE '[items_id]' OR items_id IS NULL)
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
                'items_id' => [
                    'type'           => self::FILTERTYPE_INT,
                    'caption'        => '',
                    'format' => '%%%s%%',
                    'header'         => 'items_id',
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
                'create_date'       => [
                    'title' => _t('Create Date'),
                ],
                'items_id'      => [
                    'title'  => _t('Printer'),
                ]
            ],
            'mass_actions' => [
                'download' => [
                    'caption' => _t('Download QR Codes'),
                    'action' => 'views.php?view=Qrs',
                    'new_tab' => false
                ],
                'generate' => [
                    'caption' => _t('Generate QR Codes'),
                    'action' => 'views.php?view=Qrs',
                    'new_tab' => false
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

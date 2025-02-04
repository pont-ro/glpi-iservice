<?php

namespace GlpiPlugin\Iservice\Views;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use PluginIserviceHtml;
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

        if (IserviceToolBox::getInputVariable('mass_action_assign_to_technician') && !empty(IserviceToolBox::getArrayInputVariable('item')['Qrs'])) {
            PluginIserviceQr::assignQrCodesToTechnician(IserviceToolBox::getArrayInputVariable('item')['Qrs'], IserviceToolBox::getInputVariable('technician_id'));
        }

        $techDropdown = (new PluginIserviceHtml())->generateField(
            PluginIserviceHtml::FIELDTYPE_DROPDOWN,
            'technician_id',
            0,
            false,
            ['values' => IserviceToolBox::getUsersByProfiles(['tehnician'])]
        );

        return [
            'name'          => self::getName(),
            'query'         => "
                    SELECT
                        qrs.id
                        , qrs.is_deleted
                        , p.name_and_location as name
                        , p.serial
                        , qrs.code
                        , p.supplier_name as partner
                        , u.name as technician
                    FROM glpi_plugin_iservice_qrs qrs
                    LEFT JOIN glpi_plugin_iservice_printers p ON p.id = qrs.items_id
                    LEFT JOIN glpi_users u ON u.id = qrs.technician_id
                    WHERE (qrs.is_deleted = 0 OR qrs.is_deleted IS NULL)
                        AND CAST( qrs.id AS CHAR) LIKE '[id]'
                        AND ((p.name_and_location is null AND '[name]' = '%%') OR p.name_and_location LIKE '[name]')                                              
                        AND ((p.serial  is null AND '[serial]' = '%%') OR p.serial LIKE '[serial]')
                        AND qrs.itemtype = 'Printer'
                        AND ((p.supplier_name is null AND '[partner]' = '%%') OR p.supplier_name LIKE '[partner]')
                        AND ((u.name is null AND '[technician]' = '%%') OR u.name LIKE '[technician]')
                ",
            'default_limit' => 50,
            'filters'       => [
                'id' => [
                    'type' => self::FILTERTYPE_INT,
                    'caption' => 'Număr',
                    'format' => '%%%s%%',
                    'header' => 'id',
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
                'partner' => [
                    'type'           => self::FILTERTYPE_TEXT,
                    'caption'        => 'Partner',
                    'format' => '%%%s%%',
                    'header'         => 'partner',
                ],
                'technician' => [
                    'type'           => self::FILTERTYPE_TEXT,
                    'caption'        => __('Technician'),
                    'format' => '%%%s%%',
                    'header'         => 'technician',
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
                'partner'      => [
                    'title'  => _t('Partner'),
                ],
                'technician'      => [
                    'title'  => _t('Technician'),
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
                'assign_to_technician' => [
                    'caption' => _t('Assign to technician'),
                    'action' => 'views.php?view=Qrs',
                    'suffix' => $techDropdown,
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

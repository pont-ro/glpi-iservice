<?php

namespace GlpiPlugin\Iservice\Views;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use PluginIserviceHtml;
use PluginIserviceVehicle;
use PluginIserviceVehicleExpirable;

class Vehicles extends View
{

    public static $rightname = 'plugin_iservice_view_vehicles';

    public static $icon = 'ti ti-car';

    public static function getName(): string
    {
        return _tn('Vehicle', 'Vehicles', 2);
    }

    public static function getNextExpirationDisplay($row_data): string
    {
        return !empty($row_data['expired_expiration']) ? "<span class='text-danger'>$row_data[expired_expiration]</span>" : $row_data['next_expiration'] ?? '';
    }

    protected function getSettings(): array
    {
        if (IserviceToolBox::getInputVariable('mass_action_delete') && !empty(IserviceToolBox::getArrayInputVariable('item')['Vehicles'])) {
            PluginIserviceVehicle::deleteVehicles(IserviceToolBox::getArrayInputVariable('item')['Vehicles']);
        }

        if (IserviceToolBox::getInputVariable('mass_action_create_expirable') && !empty(IserviceToolBox::getArrayInputVariable('item')['Vehicles'])) {
            $expirableType  = IserviceToolBox::getInputVariable('expirable_type');
            $expirationDate = IserviceToolBox::getInputVariable('expiration_date');

            if (!empty($expirableType)) {
                PluginIserviceVehicle::createExpirables(
                    IserviceToolBox::getArrayInputVariable('item')['Vehicles'],
                    $expirableType,
                    $expirationDate
                );
            }
        }

        $expirableTypesDropdown = (new PluginIserviceHtml())->generateField(
            PluginIserviceHtml::FIELDTYPE_DROPDOWN,
            'expirable_type',
            '',
            false,
            [
                'values' => PluginIserviceVehicleExpirable::getExpirableTypes(),
            ]
        );

        $defaultDate = date('Y-m-d H:i:s', strtotime('+1 year'));

        $expirationDatePicker = (new PluginIserviceHtml())->generateField(
            PluginIserviceHtml::FIELDTYPE_DATETIME,
            'expiration_date',
            $defaultDate,
            false,
            [
                'placeholder' => _t('Expiration Date and Time')
            ]
        );

        $this->loadRequestVariables();

        return [
            'name'          => self::getName(),
            'query'         => "SELECT 
                        v.id
                        , v.name
                        , v.description
                        , v.users_id
                        , v.license_plate
                        , v.vin
                        , COUNT(ve.id) as expirables_count
                        , MIN(CASE WHEN ve.expiration_date >= NOW() THEN ve.expiration_date END) as next_expiration
                        , MIN(CASE WHEN ve.expiration_date <= NOW() THEN ve.expiration_date END) as expired_expiration
                    from glpi_plugin_iservice_vehicles v
                    left join glpi_plugin_iservice_vehicle_expirables ve on ve.vehicle_id = v.id
                    where 1
                        and cast(v.id as char) like '[id]'
                        and ((v.name is null and '[name]' = '%%') or v.name like '[name]')
                        and ((v.description is null and '[description]' = '%%') or v.description like '[description]')
                        and ((v.license_plate is null and '[license_plate]' = '%%') or v.license_plate like '[license_plate]')
                        and ((v.vin is null and '[vin]' = '%%') or v.vin like '[vin]')
                    group by v.id                ",
            'default_limit' => 50,
            'row_class' => 'function:\GlpiPlugin\Iservice\Views\Vehicles::getRowBackgroundClass($row_data);',
            'filters'       => [
                'id' => [
                    'type' => self::FILTERTYPE_INT,
                    'caption' => _t('ID'),
                    'format' => '%%%s%%',
                    'header' => 'id',
                ],                'name' => [
                    'type'           => self::FILTERTYPE_TEXT,
                    'caption'        => _t('Name'),
                    'format' => '%%%s%%',
                    'header'         => 'name',
                ],
                'description' => [
                    'type'           => self::FILTERTYPE_TEXT,
                    'caption'        => _t('Description'),
                    'format' => '%%%s%%',
                    'header'         => 'description',
                ],
                'license_plate' => [
                    'type'           => self::FILTERTYPE_TEXT,
                    'caption'        => _t('License Plate'),
                    'format' => '%%%s%%',
                    'header'         => 'license_plate',
                ],
                'vin' => [
                    'type'           => self::FILTERTYPE_TEXT,
                    'caption'        => _t('VIN'),
                    'format' => '%%%s%%',
                    'header'         => 'vin',
                ],
            ],
            'columns'       => [
                'id'       => [
                    'title'        => 'ID',
                    'link' => [
                        'title' => '[id]',
                        'href' => 'vehicle.form.php?id=[id]',
                    ],
                ],                'name'      => [
                    'title'  => __('Name'),
                    'link' => [
                        'title' => '[id]',
                        'href' => 'vehicle.form.php?id=[id]',
                    ],
                ],
                'description'      => [
                    'title'  => __('Description'),
                ],
                'license_plate'      => [
                    'title'  => __('License Plate', 'iservice'),
                    'link' => [
                        'title' => '[id]',
                        'href' => 'vehicle.form.php?id=[id]',
                    ],
                ],
                'vin'      => [
                    'title'  => _t('VIN number'),
                ],
                'expirables_count'      => [
                    'title'  => _t('Expirables Count'),
                    'link' => [
                        'title' => '[expirables_count]',
                        'href' => 'views.php?view=VehicleExpirables&vehicleexpirables0[vehicle_id]=[id]',
                    ],
                ],
                'next_expiration'      => [
                    'title'  => _t('Next Expiration'),
                    'format' => 'function:\GlpiPlugin\Iservice\Views\Vehicles::getNextExpirationDisplay($row);',
                ],
            ],
            'mass_actions' => [
                'create_expirable' => [
                    'caption' => _t('Create Expirable'),
                    'action' => 'views.php?view=Vehicles',
                    'suffix' => $expirableTypesDropdown . ' ' . $expirationDatePicker,
                    'new_tab' => false,
                    'visible' => self::inProfileArray('super-admin', 'admin'),
                ],
                'create_vehicle' => [
                    'caption' => _t('Create Vehicle'),
                    'action' => 'vehicle.form.php',
                    'class' => 'btn btn-primary',
                    'icon' => 'ti ti-plus',
                    'visible' => self::inProfileArray('super-admin', 'admin'),
                ],
                'delete' => [
                    'caption' => _t('Delete Vehicles'),
                    'action' => 'views.php?view=Vehicles',
                    'new_tab' => false,
                    'class' => 'btn btn-danger',
                    'onClick' => 'if (confirm("' . _t('Are you sure you want to delete the selected vehicles?') . '") !== true) { return false; }',
                    'visible' => self::inProfileArray('super-admin'),
                ],
                'view_expirables' => [
                    'caption' => _t('View All Expirables'),
                    'action' => 'views.php?view=VehicleExpirables',
                    'class' => 'btn btn-secondary',
                ],
            ],
        ];
    }

    public static function getRowBackgroundClass($rowData): string
    {
        if (isset($rowData['next_expiration']) && !empty($rowData['next_expiration'])) {
            $expirationDate       = new \DateTime($rowData['next_expiration']);
            $today                = new \DateTime();
            $daysUntilExpiration = $today->diff($expirationDate)->days;

            if ($expirationDate < $today) {
                return 'border border-danger border-2';
            } elseif ($daysUntilExpiration <= PluginIserviceVehicleExpirable::EXPIRATION_SOON_DAYS) {
                return 'border border-warning border-2';
            }
        }

        return '';
    }

}

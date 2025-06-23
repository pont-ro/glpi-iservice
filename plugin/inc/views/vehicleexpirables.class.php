<?php

namespace GlpiPlugin\Iservice\Views;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use PluginIserviceVehicleExpirable;

class VehicleExpirables extends View
{

    public static $rightname = 'plugin_iservice_view_vehicle_expirables';

    public static $icon = 'ti ti-clock-exclamation';    public static function getName(): string
    {
        return _t('Vehicle Expirables');
    }

    public static function getRowBackgroundClass($rowData): string
    {
        if (!empty($rowData['expiration_date'])) {
            $expirationDate = new \DateTime($rowData['expiration_date']);
            $currentDate    = new \DateTime();
            $warningDate    = (clone $currentDate)->add(new \DateInterval('P' . PluginIserviceVehicleExpirable::EXPIRATION_SOON_DAYS . 'D'));

            if ($expirationDate < $currentDate) {
                return 'bg-danger';
            } elseif ($expirationDate <= $warningDate) {
                return 'bg-warning';
            }
        }

        return '';
    }

    public static function getNameDisplay($row): string
    {
        $expirableOptions = PluginIserviceVehicleExpirable::getExpirableTypes();
        return $expirableOptions[$row['name']] ?? $row['name'];
    }

    protected function getSettings(): array
    {
        if (IserviceToolBox::getInputVariable('mass_action_delete') && !empty(IserviceToolBox::getArrayInputVariable('item')['VehicleExpirables'])) {
            PluginIserviceVehicleExpirable::deleteExpirables(IserviceToolBox::getArrayInputVariable('item')['VehicleExpirables']);
        }

        $this->loadRequestVariables();

        return [
            'name'          => self::getName(),
            'query'         => "SELECT 
                        ve.id
                        , ve.vehicle_id
                        , v.name as vehicle_name
                        , v.license_plate
                        , ve.name
                        , ve.description
                        , ve.expiration_date as expiration_date
                        , CASE 
                            WHEN ve.expiration_date < NOW() THEN 'expired'
                            WHEN ve.expiration_date <= DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 'expiring_soon'
                            ELSE 'valid'
                        END as status_computed
                        , TIMESTAMPDIFF(DAY, NOW(), ve.expiration_date) as days_until_expiration
                    from glpi_plugin_iservice_vehicle_expirables ve
                    left join glpi_plugin_iservice_vehicles v on v.id = ve.vehicle_id
                    where 1
                        and cast(ve.id as char) like '[id]'
                        and cast(ve.vehicle_id as char) like '[vehicle_id]'
                        and ((v.name is null and '[vehicle_name]' = '%%') or v.name like '[vehicle_name]')
                        and ((v.license_plate is null and '[license_plate]' = '%%') or v.license_plate like '[license_plate]')
                        and ((ve.name is null and '[name]' = '%%') or ve.name like '[name]')
                        and ((ve.description is null and '[description]' = '%%') or ve.description like '[description]')
                        and ((ve.expiration_date is null and '[expiration_date]' = '%%') or ve.expiration_date like '[expiration_date]')
            ",
            'default_limit' => 50,
            'row_class' => 'function:\GlpiPlugin\Iservice\Views\VehicleExpirables::getRowBackgroundClass($row_data);',
            'filters'       => [
                'id' => [
                    'type' => self::FILTERTYPE_INT,
                    'caption' => _t('ID'),
                    'format' => '%%%s%%',
                    'header' => 'id',
                ],
                'vehicle_id' => [
                    'type' => self::FILTERTYPE_INT,
                    'caption' => _t('Vehicle ID'),
                    'format' => '%%%s%%',
                    'header' => 'vehicle_id',
                    'class' => 'me-2',
                ],
                'vehicle_name' => [
                    'type'           => self::FILTERTYPE_TEXT,
                    'caption'        => _t('Vehicle Name'),
                    'format' => '%%%s%%',
                    'header'         => 'vehicle_name',
                ],                'license_plate' => [
                    'type'           => self::FILTERTYPE_TEXT,
                    'caption'        => _t('License Plate'),
                    'format' => '%%%s%%',
                    'header'         => 'license_plate',
                ],
                'name' => [
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
                'expiration_date' => [
                    'type'           => self::FILTERTYPE_DATE,
                    'caption'        => _t('Expiration Date'),
                    'format' => '%%%s%%',
                    'header'         => 'expiration_date',
                ],
            ],
            'columns'       => [
                'id'       => [
                    'title'        => 'ID',
                    'link' => [
                        'title' => '[id]',
                        'href' => 'vehicleexpirable.form.php?id=[id]',
                        'target' => '_blank',
                    ],
                ],
                'vehicle_name'      => [
                    'title'  => _t('Vehicle'),
                    'link' => [
                        'title' => '[vehicle_name]',
                        'href' => 'vehicle.form.php?id=[vehicle_id]',
                        'target' => '_blank',
                    ],
                ],                'license_plate'      => [
                    'title'  => _t('License Plate'),
                ],
                'name'      => [
                    'title'  => __('Name'),
                    'format' => 'function:\GlpiPlugin\Iservice\Views\VehicleExpirables::getNameDisplay($row);',
                    'link' => [
                        'title' => '[id]',
                        'href' => 'vehicleexpirable.form.php?id=[id]',
                        'target' => '_blank',
                    ],
                ],
                'description'      => [
                    'title'  => __('Description'),
                ],
                'expiration_date'      => [
                    'title'  => _t('Expiration Date and Time'),
                    'default_sort' => 'ASC',
                ],                'days_until_expiration'      => [
                    'title'  => _t('Days Until Expiration'),
                    'align' => 'center',
                ],
            ],
            'mass_actions' => [
                'delete' => [
                    'caption' => _t('Delete Expirables'),
                    'action' => 'views.php?view=VehicleExpirables',
                    'class' => 'btn btn-danger',
                    'new_tab' => false,
                    'onClick' => 'if (confirm("' . _t('Are you sure you want to delete the selected expirables?') . '") !== true) { return false; }',
                    'visible' => self::inProfileArray('super-admin'),
                ],
                'create_expirable' => [
                    'caption' => _t('Create Expirable'),
                    'action' => 'vehicleexpirable.form.php',
                    'class' => 'btn btn-primary',
                    'visible' => self::inProfileArray('super-admin', 'admin'),
                ],
                'view_vehicles' => [
                    'caption' => _t('View Vehicles'),
                    'action' => 'views.php?view=Vehicles',
                    'class' => 'btn btn-secondary',
                ],
            ],
        ];
    }

}

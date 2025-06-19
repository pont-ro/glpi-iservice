<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

class PluginIserviceVehicle extends CommonDBTM
{
    public static $rightname = 'plugin_iservice_vehicle';

    public static function getTypeName($nb = 0): string
    {
        return _tn('Vehicle', 'Vehicles', $nb);
    }

    public function cleanDBonPurge(): void
    {
        $expirable = new PluginIserviceVehicleExpirable();
        $expirable->deleteByCriteria(['vehicle_id' => $this->getID()]);
    }

    public function showForm($id, array $options = []): bool
    {
        $this->initForm($id, $options);

        TemplateRenderer::getInstance()->display(
            '@iservice/pages/assets/vehicle.html.twig', [
                'item' => $this,
                'params' => $options,
            ]
        );

        return true;
    }

    /**
     * Get vehicle expirables
     *
     * @return array
     */
    public function getExpirables(): array
    {
        if ($this->isNewItem()) {
            return [];
        }

        $expirable = new PluginIserviceVehicleExpirable();
        return $expirable->find(['vehicle_id' => $this->getID()], ['name ASC']);
    }

    /**
     * Get expired items
     *
     * @return array
     */
    public function getExpiredItems(): array
    {
        if ($this->isNewItem()) {
            return [];
        }

        $expirable = new PluginIserviceVehicleExpirable();
        return $expirable->find(
            [
                'vehicle_id' => $this->getID(),
                'expiration_date' => ['<', date('Y-m-d')]
            ], ['expiration_date ASC']
        );
    }

    /**
     * Get items expiring soon (within PluginIserviceVehicleExpirable::EXPIRATION_SOON_DAYS days)
     *
     * @param int $days Number of days to check ahead
     *
     * @return array
     */
    public function getExpiringSoonItems(int $days = PluginIserviceVehicleExpirable::EXPIRATION_SOON_DAYS): array
    {
        if ($this->isNewItem()) {
            return [];
        }

        $expirable  = new PluginIserviceVehicleExpirable();
        $futureDate = date('Y-m-d', strtotime("+{$days} days"));

        return $expirable->find(
            [
                'vehicle_id' => $this->getID(),
                'expiration_date' => ['BETWEEN', [date('Y-m-d'), $futureDate]]
            ], ['expiration_date ASC']
        );
    }

    public static function getIcon()
    {
        return 'fas fa-car';
    }

    /**
     * Delete multiple vehicles
     *
     * @param array $vehicleIds Array of vehicle IDs to delete
     *
     * @return bool
     */
    public static function deleteVehicles(array $vehicleIds): bool
    {
        $ids     = array_keys(array_filter($vehicleIds));
        $success = true;
        $vehicle = new self();

        foreach ($ids as $vehicleId) {
            if ($vehicle->getFromDB($vehicleId)) {
                if (!$vehicle->delete(['id' => $vehicleId])) {
                    $success = false;
                    Session::addMessageAfterRedirect(sprintf(_t('Error deleting vehicle %d'), $vehicleId), false, ERROR);
                }
            }
        }

        if ($success) {
            Session::addMessageAfterRedirect(_t('Vehicles deleted successfully'), false, INFO);
        }

        return $success;
    }

    /**
     * Create expirables for multiple vehicles
     *
     * @param array $vehicleIds Array of vehicle IDs
     * @param string $expirableType Type of expirable to create
     * @param string|null $expirationDate Optional expiration date (format: Y-m-d)
     *
     * @return bool
     */
    public static function createExpirables(array $vehicleIds, string $expirableType, ?string $expirationDate = null): bool
    {
        $ids       = array_keys(array_filter($vehicleIds));
        $success   = true;
        $expirable = new PluginIserviceVehicleExpirable();

        if (empty($expirationDate)) {
            $expirationDate = date('Y-m-d', strtotime('+1 year'));
        }

        foreach ($ids as $vehicleId) {
            $data = [
                'vehicle_id' => $vehicleId,
                'name' => $expirableType,
                'expiration_date' => $expirationDate,
            ];

            if (!$expirable->add($data)) {
                $success = false;
                Session::addMessageAfterRedirect(sprintf(_t('Error creating expirable for vehicle %d'), $vehicleId), false, ERROR);
            }
        }

        if ($success) {
            Session::addMessageAfterRedirect(_t('Expirables created successfully'), false, INFO);
        }

        return $success;
    }

}

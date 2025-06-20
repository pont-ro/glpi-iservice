<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

class PluginIserviceVehicleExpirable extends CommonDBTM
{
    public static $rightname = 'plugin_iservice_vehicle_expirable';

    const EXPIRATION_SOON_DAYS = 7;

    // Common expirable types.
    const TYPE_INSURANCE = 'insurance';
    const TYPE_ROAD_TAX  = 'road_tax';
    const TYPE_CASCO     = 'casco';
    const TYPE_LEASING   = 'leasing';
    const TYPE_MOT       = 'mot';
    const TYPE_PARKING   = 'parking';
    const TYPE_OTHER     = 'other';

    public static function getTypeName($nb = 0): string
    {
        return _tn('Vehicle expirable', 'Vehicle expirables', $nb);
    }

    public static function getTable($classname = null) {
        return 'glpi_plugin_iservice_vehicle_expirables';
    }

    public function prepareInputForAdd($input)
    {
        $input = parent::prepareInputForAdd($input);

        if (!isset($input['vehicle_id']) || empty($input['vehicle_id'])) {
            Session::addMessageAfterRedirect(_t('Vehicle is required'), false, ERROR);
            return false;
        }

        $vehicle = new PluginIserviceVehicle();
        if (!$vehicle->getFromDB($input['vehicle_id'])) {
            Session::addMessageAfterRedirect(_t('Invalid vehicle'), false, ERROR);
            return false;
        }        // Validate expiration date

        if (isset($input['expiration_date']) && !empty($input['expiration_date'])) {
            if (!IserviceToolBox::isValidDate($input['expiration_date'])) {
                Session::addMessageAfterRedirect(_t('Invalid expiration date'), false, ERROR);
                return false;
            }
        }

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        return $this->prepareInputForAdd($input);
    }

    public function showForm($id, array $options = [])
    {
        $this->initForm($id, $options);

        TemplateRenderer::getInstance()->display(
            '@iservice/pages/assets/vehicleexpirable.html.twig', [
                'item' => $this,
                'params' => $options,
                'EXPIRATION_SOON_DAYS' => PluginIserviceVehicleExpirable::EXPIRATION_SOON_DAYS,
            ]
        );

        return true;
    }

    /**
     * Get available expirable types
     *
     * @return array
     */
    public static function getExpirableTypes(): array
    {
        return [
            self::TYPE_INSURANCE => _t('Insurance'),
            self::TYPE_ROAD_TAX => _t('Road tax'),
            self::TYPE_CASCO => _t('Casco'),
            self::TYPE_LEASING => _t('Leasing'),
            self::TYPE_MOT => _t('MOT'),
            self::TYPE_PARKING => _t('Parking prepayment'),
            self::TYPE_OTHER => _t('Other'),
        ];
    }

    /**
     * Check if expirable is expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if (empty($this->fields['expiration_date'])) {
            return false;
        }

        return $this->fields['expiration_date'] < date('Y-m-d');
    }

    public static function getIcon()
    {
        return 'fas fa-calendar-times';
    }

    /**
     * Delete multiple expirables
     *
     * @param array $expirableIds Array of expirable IDs to delete
     *
     * @return bool
     */
    public static function deleteExpirables(array $expirableIds): bool
    {
        $ids       = array_keys(array_filter($expirableIds));
        $success   = true;
        $expirable = new self();

        foreach ($ids as $expirableId) {
            if ($expirable->getFromDB($expirableId)) {
                if (!$expirable->delete(['id' => $expirableId])) {
                    $success = false;
                    Session::addMessageAfterRedirect(sprintf(_t('Error deleting expirable %d'), $expirableId), false, ERROR);
                }
            }
        }

        if ($success) {
            Session::addMessageAfterRedirect(_t('Expirables deleted successfully'), false, INFO);
        }

        return $success;
    }

}

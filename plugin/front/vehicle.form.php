<?php

use Glpi\Event;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

require "../inc/includes.php";

Session::checkRight("plugin_iservice_vehicle", READ);

$vehicleId     = IserviceToolBox::getInputVariable('id');
$addVehicle    = IserviceToolBox::getInputVariable('add');
$updateVehicle = IserviceToolBox::getInputVariable('update');

global $DB;

$post    = IserviceToolBox::filterVarArray(INPUT_POST);
$vehicle = new PluginIserviceVehicle();

/**
 * Check for duplicate vehicle fields (license plate or VIN)
 *
 * @param array $post           POST data containing vehicle information
 * @param string $field         Field to check ('license_plate' or 'vin')
 * @param int|null $excludeId   ID of vehicle to exclude from check (for updates)
 *
 * @return bool                 True if duplicate found, false otherwise
 */
function isDuplicateVehicleField($post, $field, $excludeId = null)
{
    if (empty($post[$field])) {
        return false;
    }

    $existingVehicle = new PluginIserviceVehicle();
    if ($existingVehicle->getFromDBByCrit([$field => $post[$field]])) {
        if ($excludeId !== null && $existingVehicle->getID() == $excludeId) {
            return false;
        }

        $fieldDisplay = ($field === 'license_plate') ? 'license plate' : 'VIN';
        Session::addMessageAfterRedirect(
            sprintf(__('A vehicle with %s %s already exists!', 'iservice'), $fieldDisplay, $post[$field]),
            true,
            ERROR
        );
        return true;
    }

    return false;
}

if (!empty($addVehicle)) {
    if (empty($post['name'])) {
        Session::addMessageAfterRedirect(__('Please enter a name', 'iservice'), true, ERROR);
    } else {
        $vehicle->check(-1, CREATE, $post);

        if (!isDuplicateVehicleField($post, 'license_plate') && !isDuplicateVehicleField($post, 'vin')) {
            unset($post['id']);

            if (($vehicleId = $vehicle->add($post)) !== false) {
                Session::addMessageAfterRedirect(_t('Vehicle added successfully'), true, INFO, true);
                Html::redirect($vehicle->getFormURL() . "?id=$vehicleId");
            } else {
                Session::addMessageAfterRedirect(_t('Error creating vehicle'), true, ERROR);
            }
        }
    }
} elseif (!empty($updateVehicle)) {
    $vehicle->check($post['id'], UPDATE);

    if (!isDuplicateVehicleField($post, 'license_plate', $post['id']) && !isDuplicateVehicleField($post, 'vin', $post['id'])) {
        if ($vehicle->update($post)) {
            Session::addMessageAfterRedirect(_t('Vehicle updated successfully'), true, INFO, true);
            Event::log($vehicleId, "vehicles", 4, "inventory", sprintf(__('%s updates an item'), $_SESSION["glpiname"]));
        } else {
            Session::addMessageAfterRedirect(_t('Error updating vehicle'), true, ERROR);
        }
    }

    Html::back();
}

Html::header(PluginIserviceVehicle::getTypeName(Session::getPluralNumber()), $_SERVER['PHP_SELF'], null, null);
$vehicle->showForm($vehicleId);
Html::footer();

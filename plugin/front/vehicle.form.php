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
function checkDuplicateVehicleField($post, $field, $excludeId = null)
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

        $error = false;
        $error = checkDuplicateVehicleField($post, 'license_plate') || $error;
        $error = checkDuplicateVehicleField($post, 'vin') || $error;

        if (empty($error)) {
            unset($post['id']);

            if (($vehicleId = $vehicle->add($post)) !== false) {
                Session::addMessageAfterRedirect(__('Vehicle added successfully', 'iservice'), true, INFO, true);
                Html::redirect($vehicle->getFormURL() . "?id=$vehicleId");
            } else {
                Session::addMessageAfterRedirect(__('Error creating vehicle', 'iservice'), true, ERROR);
            }
        }
    }
} elseif (!empty($updateVehicle)) {
    $vehicle->check($post['id'], UPDATE);

    $error = false;
    $error = checkDuplicateVehicleField($post, 'license_plate', $post['id']) || $error;
    $error = checkDuplicateVehicleField($post, 'vin', $post['id']) || $error;

    if (empty($error)) {
        if ($vehicle->update($post)) {
            Session::addMessageAfterRedirect(__('Vehicle updated successfully', 'iservice'), true, INFO, true);
            Event::log($vehicleId, "vehicles", 4, "inventory", sprintf(__('%s updates an item'), $_SESSION["glpiname"]));
        } else {
            Session::addMessageAfterRedirect(__('Error updating vehicle', 'iservice'), true, ERROR);
        }
    }

    Html::back();
}

Html::header(PluginIserviceVehicle::getTypeName(Session::getPluralNumber()), $_SERVER['PHP_SELF'], null, null);
$vehicle->showForm($vehicleId);
Html::footer();

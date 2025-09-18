<?php

use Glpi\Event;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

require "../inc/includes.php";

Session::checkRight("plugin_iservice_vehicle_expirable", READ);

$expirableId     = IserviceToolBox::getInputVariable('id');
$vehicleId       = IserviceToolBox::getInputVariable('vehicle_id');
$addExpirable    = IserviceToolBox::getInputVariable('add');
$updateExpirable = IserviceToolBox::getInputVariable('update');
$deleteExpirable = IserviceToolBox::getInputVariable('purge');

global $DB;

$post      = IserviceToolBox::filterVarArray(INPUT_POST);
$expirable = new PluginIserviceVehicleExpirable();

if (!empty($addExpirable)) {
    if (empty($post['vehicle_id'])) {
        Session::addMessageAfterRedirect(_t('Please select a vehicle'), true, ERROR);
    } elseif (empty($post['name'])) {
        Session::addMessageAfterRedirect(_t('Please enter a name'), true, ERROR);
    } elseif (empty($post['expiration_date'])) {
        Session::addMessageAfterRedirect(_t('Please enter an expiration date'), true, ERROR);
    } else {
        $expirable->check(-1, CREATE, $post);

        if (!IserviceToolBox::isValidDateTime($post['expiration_date'])) {
            Session::addMessageAfterRedirect(_t('Invalid expiration date format'), true, ERROR);
            $error = true;
        }

        if (empty($error)) {
            unset($post['id']);

            if (($expirableId = $expirable->add($post)) !== false) {
                Session::addMessageAfterRedirect(_t('Vehicle expirable added successfully'), true, INFO, true);
                Html::redirect($expirable->getFormURL() . "?id=$expirableId");
            } else {
                Session::addMessageAfterRedirect(_t('Error creating vehicle expirable'), true, ERROR);
            }
        }
    }
} elseif (!empty($updateExpirable)) {
    $expirable->check($post['id'], UPDATE);

    if (!empty($post['expiration_date']) && !IserviceToolBox::isValidDateTime($post['expiration_date'])) {
        Session::addMessageAfterRedirect(_t('Invalid expiration date format'), true, ERROR);
        $error = true;
    }

    if (empty($error)) {
        if ($expirable->update($post)) {
            Session::addMessageAfterRedirect(_t('Vehicle expirable updated successfully'), true, INFO, true);
            Event::log($expirableId, "vehicleexpirables", 4, "inventory", sprintf(_t('%s updates an item'), $_SESSION["glpiname"]));
        } else {
            Session::addMessageAfterRedirect(_t('Error updating vehicle expirable'), true, ERROR);
        }
    }

    Html::back();
} elseif (!empty($deleteExpirable)) {
    $expirable->check($expirableId, PURGE);

    if ($expirable->delete(['id' => $expirableId])) {
        Session::addMessageAfterRedirect(_t('Expirable deleted successfully'), true, INFO, true);
        Html::redirect("views.php?view=VehicleExpirables&vehicleexpirables0[vehicle_id]=$vehicleId");
    } else {
        Session::addMessageAfterRedirect(sprintf(_t('Error deleting expirable %d'), $expirableId), false, ERROR);
        Html::back();
    }
}

Html::header(PluginIserviceVehicleExpirable::getTypeName(Session::getPluralNumber()), $_SERVER['PHP_SELF'], null, null);
$expirable->showForm($expirableId, ['vehicle_id' => $vehicleId, 'expirable_dropdown_elements' => PluginIserviceVehicleExpirable::getExpirableTypes()]);
Html::footer();

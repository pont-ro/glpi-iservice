<?php

// Imported from iService2, needs refactoring. Original file: "hmarfaexport.form.php".
require "../inc/includes.php";

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

Session::checkRight("plugin_iservice_emaintenance", READ);

$id         = IserviceToolBox::getInputVariable('id');
$get_mails  = IserviceToolBox::getInputVariable('get_mails', null, INPUT_POST);
$max_emails = IserviceToolBox::getInputVariable('max_emails', 1);

$mass_action_apply       = IserviceToolBox::getInputVariable('mass_action_apply');
$mass_action_delete      = IserviceToolBox::getInputVariable('mass_action_delete');
$mass_action_mark_read   = IserviceToolBox::getInputVariable('mass_action_mark_read');
$mass_action_mark_unread = IserviceToolBox::getInputVariable('mass_action_mark_unread');

$post = filter_var_array($_POST);

if (!empty($get_mails)) {
    $em                  = new PluginIserviceEmaintenance();
    $em->maxfetch_emails = $max_emails;
    $em->collect($id, 1);
    Html::back();
} elseif (!empty($mass_action_delete) || !empty($mass_action_mark_read) || !empty($mass_action_mark_unread)) {
    Session::checkRight("plugin_iservice_emaintenance", UPDATE);
    $ids = implode(',', array_keys($post['item']['emaintenance']));

    if (!empty($mass_action_delete)) {
        $query           = "DELETE FROM glpi_plugin_iservice_ememails WHERE id IN ($ids)";
        $success_message = count($post['item']['emaintenance']) . " elemente șterse";
        $error_message   = "Eroare la ștergere";
    } elseif (!empty($mass_action_mark_read)) {
        $query           = "UPDATE glpi_plugin_iservice_ememails SET `read` = 1 WHERE id IN ($ids)";
        $success_message = count($post['item']['emaintenance']) . " elemente marcate ca citite";
        $error_message   = "Eroare la marcare";
    } elseif (!empty($mass_action_mark_unread)) {
        $query           = "UPDATE glpi_plugin_iservice_ememails SET `read` = 0 WHERE id IN ($ids)";
        $success_message = count($post['item']['emaintenance']) . " elemente marcate ca necitite";
        $error_message   = "Eroare la marcare";
    }

    global $DB;

    if ($DB->query($query)) {
        Session::addMessageAfterRedirect($success_message);
    } else {
        Session::addMessageAfterRedirect($error_message, false, ERROR);
    }

    Html::back();
} else {
    Html::header(PluginIserviceEmaintenance::getTypeName());
    Html::displayNotFoundError();
    Html::footer();
}

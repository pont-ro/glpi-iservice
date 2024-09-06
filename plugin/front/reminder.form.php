<?php

use Glpi\Event;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

require "../inc/includes.php";

Session::checkLoginUser();

$remind = new PluginIserviceReminder();
$id     = IserviceToolBox::getInputVariable('id', 0);
$add    = IserviceToolBox::getInputVariable('add');
$update = IserviceToolBox::getInputVariable('update');
$post   = IserviceToolBox::filter_var_array(INPUT_POST);

if (!empty($add)) {
    $remind->check(-1, CREATE, $post);

    if (($newID = $remind->add($post)) !== false) {
        Event::log($newID, "reminder", 4, "tools", sprintf(__('%1$s adds the item %2$s'), $_SESSION["glpiname"], $post["name"]));
        if ($_SESSION['glpibackcreated']) {
            Html::redirect($remind->getFormURL() . "?id=" . $newID);
        }
    }

    Html::back();
} elseif (!empty($update)) {
    $remind->check($post["id"], UPDATE);     // Right to update the reminder.

    $remind->update($post);
    Event::log(
        $post["id"], "reminder", 4, "tools",
        // TRANS: %s is the user login.
                    sprintf(__('%s updates an item'), $_SESSION["glpiname"])
    );
    Html::back();
} else {
    Html::header(PluginIserviceReminder::getTypeName());
    $remind->display(['id' => $id]);
    Html::footer();
}

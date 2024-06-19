<?php
// Imported from iService2, needs refactoring.
require "../inc/includes.php";

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

$partner = new PluginIservicePartner();

$id                  = IserviceToolBox::getInputVariable('id');
$update              = IserviceToolBox::getInputVariable('update');
$partner_contacted   = IserviceToolBox::getInputVariable('partner_contacted');
$generate_magic_link = IserviceToolBox::getInputVariable('generate_magic_link');

$post = filter_input_array(INPUT_POST);

if (!empty($generate_magic_link) && !empty($id)) {
    $partner->check($id, UPDATE);
    PluginIservicePartner::generateNewMagicLink($id);
    Html::back();
} elseif (!empty($update) && !empty($id)) {
    $partner->check($id, UPDATE);
    $partner->update($post);
    if (!empty($post['_customfields'])) {
        $partner_customfields = new PluginFieldsSuppliersuppliercustomfield();
        PluginIserviceDB::populateByItemsId($partner_customfields, $id);
        $post['_customfields'][$partner_customfields->getIndexName()] = $partner_customfields->getID();
        $partner_customfields->update($post['_customfields']);
    }

    Html::back();
}

Html::displayNotFoundError();

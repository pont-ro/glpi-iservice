<?php

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceConsumableDescription extends CommonDBTM
{

    public static function getTypeName($nb = 0): string
    {
        return _n('Consumable Description', 'Consumable Descriptions', $nb);
    }

    public static function ajaxEditDescription(): string
    {
        $id    = IserviceToolBox::getInputVariable('id');
        $value = IserviceToolBox::getInputVariable('value');

        if (empty($id) || empty($value)) {
            return _t('Invalid input data');
        }

        $consumableDescription = new self();
        if (PluginIserviceDB::populateByQuery($consumableDescription, "WHERE `{$consumableDescription->getTable()}`.`plugin_iservice_consumables_id` = '$id' LIMIT 1")
            && $consumableDescription->update(
                [
                    $consumableDescription->getIndexName() => $consumableDescription->getID(),
                    'description' => $value,
                    '_no_message' => true,
                ]
            )
        ) {
            return IserviceToolBox::RESPONSE_OK;
        } elseif ($consumableDescription->add(
            [
                'add' => 'add',
                'plugin_iservice_consumables_id' => $id,
                'description' => $value,
                '_no_message' => true,
            ]
        )
        ) {
            return IserviceToolBox::RESPONSE_OK;
        } else {
            return _t('Failed to update product description');
        }
    }

}

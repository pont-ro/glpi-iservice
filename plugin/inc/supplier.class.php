<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceSupplier extends Supplier
{
    use PluginIserviceItem;

    /*
     * @var PluginFieldsSuppliersuppliercustomfield
     */
    public $customfields = null;

    public function getCustomFieldsModelName(): string
    {
        return 'PluginFieldsSuppliersuppliercustomfield';
    }

}

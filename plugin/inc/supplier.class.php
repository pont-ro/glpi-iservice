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

    public function getFromDB($ID)
    {
        $this->customfields = new PluginFieldsSuppliersuppliercustomfield();
        if (parent::getFromDB($ID)) {
            if (!PluginIserviceDB::populateByItemsId($this->customfields, $ID) && !$this->customfields->add(['add' => 'add', 'items_id' => $ID, '_no_message' => true])) {
                return false;
            }

            // Further code possibility.
            self::$item_cache[$ID] = $this;
            return true;
        }

        return false;
    }

}

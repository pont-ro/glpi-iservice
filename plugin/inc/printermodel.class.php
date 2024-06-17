<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIservicePrinterModel extends PrinterModel
{
    use PluginIserviceItem;

    /*
     * @var PluginFieldsPrintermodelprintermodelcustomfield
     */
    public $customfields = null;

    public function getFromDB($ID)
    {
        $this->customfields = new PluginFieldsPrintermodelprintermodelcustomfield();
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

<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * PluginIserviceIntOrder Class
 * */
class PluginIserviceIntorder_View extends CommonDBTM
{

    // From CommonDBTM
    public    $dohistory  = false;
    static    $rightname  = 'plugin_iservice_intorder';
    protected $usenotepad = true;

    static function getTypeName($nb = 0)
    {
        return _n('Internal order', 'Internal orders', $nb, 'iservice');
    }

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_iservice_intorders_view';
    }
}

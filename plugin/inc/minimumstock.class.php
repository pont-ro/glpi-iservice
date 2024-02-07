<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * PluginIserviceExtOrder Class
 * */
class PluginIserviceMinimumStock extends CommonDBTM
{

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_iservice_minimum_stocks';
    }

}

<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * PluginIserviceOrderStatusChange Class
 * */
class PluginIserviceOrderStatusChange extends CommonDBTM
{

    function prepareInputForAdd($input)
    {
        $input['users_id'] = $_SESSION['glpiID'];
        return $input;
    }

}

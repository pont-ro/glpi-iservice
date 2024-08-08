<?php

// Imported from iService2, needs refactoring. Original file: "pendingemail.class.php".
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * PluginIservicePendingEmail Class
 * */
class PluginIservicePendingEmail extends CommonDBTM
{
    public $dohistory     = true;
    static $rightname     = 'plugin_iservice_pendingemail';
    protected $usenotepad = true;

    public static function getTypeName($nb = 0)
    {
        return _tn('Pending email', 'Pending emails', $nb);
    }

    public function getRawName()
    {
        return _tn('Pending email', 'Pending emails', 1) . " #" . $this->getID();
    }

}

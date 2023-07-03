<?php

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
        return _n('Pending email', 'Pending emails', $nb, 'iservice');
    }

    public function getRawName()
    {
        return __('Pending email', 'iservice') . " #" . $this->getID();
    }

}

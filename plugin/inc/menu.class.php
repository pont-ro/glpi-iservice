<?php
class PluginIserviceMenu extends CommonGLPI
{

    /**
     * Right name used to check rights to do actions on item
     *
     * @var string
     */
    public static $rightname = 'plugin_iservice_config';

    public static function getMenuName(): string
    {
        return _t('iService');
    }

    public static function getMenuContent(): array
    {
        if (!Session::haveRight(self::$rightname, READ)) {
            return [];
        }

        global $CFG_PLUGIN_ISERVICE;

        return [
            'title' => self::getMenuName(),
            'page'  => "$CFG_PLUGIN_ISERVICE[root_doc]/front/config.form.php",
            'icon'  => 'fas fa-cogs',
        ];
    }

}

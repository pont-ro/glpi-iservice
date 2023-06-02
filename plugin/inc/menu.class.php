<?php
class PluginIserviceMenu extends CommonGLPI
{

    /**
     * Right name used to check rights to do actions on item
     *
     * @var string
     */
    public static $rightname = 'entity';

    public static function getMenuName(): string
    {
        return __('iService', 'iservice');
    }

    public static function getMenuContent(): array
    {
        if (!Session::haveRight(self::$rightname, READ)) {
            return [];
        }

        return [
            'title' => self::getMenuName(),
            'page'  => Plugin::getPhpDir('iservice', false) . "/front/config.form.php",
            'icon'  => 'fas fa-cogs',
        ];
    }

}

<?php

class PluginIserviceConfig extends CommonDBTM
{

    public static $rightname = 'config';

    protected $displaylist = false;

    public $auto_message_on_action = false;
    public $showdebug              = true;

    public function defineTabs($options = []): array
    {
        $ong = [];
        $this->addStandardTab(__CLASS__, $ong, $options);
        return $ong;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): array
    {

        switch ($item->getType()) {
            case __CLASS__:
                return [
                    1 => __('General setup'),
                    2 => __('Import'),
                ];
            default:
                break;
        }

        return [];
    }

    public function showFormGeneral(CommonGLPI $item): bool
    {
        echo "General";

        return true;
    }

    public function showFormImport(CommonGLPI $item): bool
    {
        echo "Import";

        return true;
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        switch ($tabnum) {
            case 1:
                $item->showFormGeneral($item);
                break;
            case 2:
                $item->showFormImport($item);
                break;
            default:
                break;
        }

        return true;
    }

}

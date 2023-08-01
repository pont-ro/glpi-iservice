<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceUser extends User
{
    use PluginIserviceItem;

    public static function getType(): string
    {
        return Supplier::getType();
    }

    public static function getTable($classname = null): string
    {
        return Supplier::getTable($classname);
    }

}

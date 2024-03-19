<?php

namespace GlpiPlugin\Iservice\Views;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
;

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class Views
{
    public static $defaultViewsDirectory = null;

    /**
     * Gets a View descendant by it's class name from the views collection
     *
     * @param string $viewClassName The name of the view
     *
     * @return View
     */
    public static function getView($viewClassName = '', $loadSettings = true, $archive = false): View
    {
        if (!empty($viewClassName) && is_subclass_of("GlpiPlugin\Iservice\Views\\$viewClassName", 'GlpiPlugin\Iservice\Views\View')) {
            $fullClassName = "GlpiPlugin\Iservice\Views\\$viewClassName";

            if (empty(IserviceToolBox::getInputVariable('export'))) {
                \Html::header($fullClassName::getName());
            }

            return new $fullClassName($loadSettings, $archive ? "a_" : "");
        } else {
            return new View();
        }
    }

}

Views::$defaultViewsDirectory = PLUGIN_ISERVICE_DIR . '/inc/views';

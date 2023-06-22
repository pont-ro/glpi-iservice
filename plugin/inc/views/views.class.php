<?php

namespace GlpiPlugin\Iservice\Views;

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class Views
{
    public static $default_views_directory = null;

    /**
     * Gets a View descendant by it's class name from the views collection
     *
     * @param string $view_name The name of the view
     *
     * @return View
     */
    public static function getView($view_class_name = '', $load_settings = true, $archive = false): View
    {
        if (!empty($view_class_name) && is_subclass_of("$view_class_name", 'GlpiPlugin\Iservice\Views\View')) {
            return new $view_class_name($load_settings, $archive ? "a_" : "");
        } else {
            return new View();
        }
    }

}

Views::$default_views_directory = PLUGIN_ISERVICE_DIR . '/inc/views';

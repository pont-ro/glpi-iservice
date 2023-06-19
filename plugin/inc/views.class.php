<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}


class PluginIserviceViews {
    static $default_views_directory = null;
    static $views = null;

    static function loadViewsDirectory($views_directory = '') {
        if (empty(self::$default_views_directory)) {
            self::$default_views_directory = __DIR__ . DIRECTORY_SEPARATOR . 'views';
        }
        if (empty($views_directory)) {
            $views_directory = self::$default_views_directory;
        }
        self::$views = array();
        if (file_exists($views_directory) && is_dir($views_directory)) {
            $files = scandir($views_directory);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    include_once $views_directory . DIRECTORY_SEPARATOR . $file;
                    $class = "PluginIserviceView_" . pathinfo($file, PATHINFO_FILENAME);
                    if (class_exists($class) && is_subclass_of("$class", 'PluginIserviceView')) {
                        self::$views[strtolower(pathinfo($file, PATHINFO_FILENAME))] = "$class";
                    }
                }
            }
            uasort(self::$views, array("PluginIserviceView", "compareByOrder"));
            return true;
        } else {
            return false;
        }
    }

    /**
     * Gets a PluginIserviceView descendant by it's name from the views collection
     * @param string $view_name The name of the view
     * @return \PluginIserviceView
     */
    static function getView($view_name = '', $load_settings = true, $archive=false, $views_directory = '') {
        if (!empty($view_name) && (!empty(self::$views) || self::loadViewsDirectory($views_directory)) && array_key_exists(strtolower($view_name), self::$views)) {
            $view_class_name = self::$views[strtolower($view_name)];
            return new $view_class_name($load_settings, $archive ? "a_" : "");
        } else {
            return new PluginIserviceView();
        }
    }

    static function displaySelector($selected_view = '', $view_archive = false) {
        $html = new PluginIserviceHtml();
        echo "<form id='view-selector' method='get'>";
        echo "<b>", __('Views', 'iservice'), "</b> ";
        echo "<select name='view' onchange=\"forms['view-selector'].submit();\">";
        foreach (self::$views as $view_id => $view_class_name) {
            echo "<option value='$view_id'" . (strtolower($selected_view) === $view_id ? ' selected' : '') . ">{$view_class_name::getName()}</option>";
        }
        echo "</select> ";
        echo "<label for='view_archive'>", __('Archive', 'iservice'), "</label> ";
        echo "<input id='view_archive' name='view_archive' type='checkbox'" . ($view_archive ? ' checked' : '') . "> ";
        echo "<input type='submit' class='submit' value='" . __('View', 'iservice') . "' />";
        echo "</form>";
    }
}

PluginIserviceViews::$default_views_directory = __DIR__ . DIRECTORY_SEPARATOR . 'views';

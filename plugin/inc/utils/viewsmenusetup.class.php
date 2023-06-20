<?php

namespace GlpiPlugin\Iservice\Utils;

use GlpiPlugin\Iservice\Views\View;
use \Session;

class ViewsMenuSetup
{

    public static function setViewsDropdownNameIcon(&$menus): void
    {
         $menus['views']['title'] = __('Vizualizari', 'iservice');
         $menus['views']['icon']  = 'fa-fw ti ti-columns';
    }

    public static function getViewClasses(): array
    {
        $viewClasses = $_SESSION['plugin']['iservice']['viewClasses'] ?? null;

        if (empty($viewClasses)) {
            $viewClasses = $_SESSION['plugin']['iservice']['viewClasses'] = self::getViewClassesFromDisk();
        }

        return $viewClasses;
    }

    public static function getViewClassesFromDisk(): array
    {
        $viewFilesList = glob(GLPI_ROOT . '/plugins/iservice/inc/views/*.class.php');

        if (empty($viewFilesList)) {
            return [];
        }

        $preIncludedClasses = get_declared_classes();

        foreach ($viewFilesList as $viewFile) {
            include_once $viewFile;
        }

        $postIncludedClasses = get_declared_classes();

        $viewClasses = array_diff($postIncludedClasses, $preIncludedClasses);
        $viewClasses = array_filter(
            $viewClasses, function ($class) {
                return is_subclass_of("$class", 'GlpiPlugin\Iservice\Views\View');
            }
        );
        uasort($viewClasses, ["GlpiPlugin\Iservice\Views\View", "compareByOrder"]);

        return $viewClasses ?? [];

    }

}

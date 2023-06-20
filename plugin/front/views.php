<?php

require "../inc/includes.php";

$view_id      = PluginIserviceCommon::getInputVariable('view', 'Unpaid_Invoices');
$view_archive = PluginIserviceCommon::getInputVariable('view_archive', false);
$export       = filter_input(INPUT_POST, 'export') || filter_input(INPUT_GET, 'export');
if ($export) {
    define('GLPI_KEEP_CSRF_TOKEN', true);
}

$views_directory = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "inc" . DIRECTORY_SEPARATOR . "views_views";
$view            = PluginIserviceViews::getView($view_id, true, $view_archive, $views_directory);


Html::header(
    __("iService", "iservice"),
    $_SERVER['PHP_SELF'],
    "plugin_iservice_views",
    $view_id,
    "views"
);

Session::checkRight('entity', READ);

$view->display(false, $export);

Html::footer();

<?php

require "../inc/includes.php";

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use GlpiPlugin\Iservice\Views\Views;

global $DEBUG_SQL, $TIMER_DEBUG;

$DEBUG_SQL['debug_times'][$TIMER_DEBUG->getTime()] = 'Starting views.php';

$view_id      = IserviceToolBox::getInputVariable('view', 'Unpaid_Invoices');
$view_archive = IserviceToolBox::getInputVariable('view_archive', false);
$export       = filter_input(INPUT_POST, 'export') || filter_input(INPUT_GET, 'export');

$view = Views::getView($view_id, true, $view_archive);

$DEBUG_SQL['debug_times'][$TIMER_DEBUG->getTime()] = 'View data loaded';

if (empty(IserviceToolBox::getInputVariable('export'))) {
    Html::header(__("iService", "iservice"), $_SERVER['PHP_SELF'], "plugin_iservice_views", $view_id, "views");
}

Session::checkRight($view::$rightname, READ);

$view->display(false, $export);

$DEBUG_SQL['debug_times'][$TIMER_DEBUG->getTime()] = 'View displayed';

Html::footer();
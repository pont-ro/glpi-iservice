<?php

// Imported from iService2, needs refactoring. Original file: "planlunar.php".
require "../inc/includes.php";

Session::checkRight("plugin_iservice_monthly_plan", READ);

$monthlyPlan = new PluginIserviceMonthlyPlan();

Html::header(
    $monthlyPlan->getMenuName() . ' - ' . __("iService", "iservice"),
    $_SERVER['PHP_SELF'],
    "plugin_iservice_views",
    'monthly_plan',
    "monthly_plan"
);

$monthlyPlan->display();

Html::footer();

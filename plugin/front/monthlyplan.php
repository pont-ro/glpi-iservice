<?php

// Imported from iService2, needs refactoring. Original file: "planlunar.php".
require "../inc/includes.php";

// Session::checkRight("plugin_iservice_planlunar", READ);

Html::header(
    __("iService", "iservice"),
    $_SERVER['PHP_SELF'],
    "plugin_iservice_views",
    'monthly_plan',
    "monthly_plan"
);

(new PluginIserviceMonthlyPlan())->display();

Html::footer();

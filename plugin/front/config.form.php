<?php
require "../inc/includes.php";

Html::header(
    __("iService", "iservice"),
    $_SERVER['PHP_SELF'],
    "config",
    "pluginiservicemenu",
    "iserviceconfig"
);

Session::checkRight('config', READ);

PluginIserviceConfig::displayFullPageForItem(
    1,
    ["config", "iservice"],
    [
        'formoptions'  => "data-track-changes=true"
    ]
);

Html::footer();

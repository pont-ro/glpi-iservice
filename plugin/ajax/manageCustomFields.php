<?php

require "../inc/includes.php";

use GlpiPlugin\iService\InstallSteps\AddCustomFieldsInstallStep;

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

function setDefaultCustomFields(): bool
{
    return AddCustomFieldsInstallStep::do();
}

echo setDefaultCustomFields() ? __('Custom fields have been reset to default settings', 'iservice') : __('An error occurred while resetting custom fields to default settings', 'iservice');

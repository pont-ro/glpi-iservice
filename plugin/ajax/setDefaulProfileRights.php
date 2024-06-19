<?php

require "../inc/includes.php";

use GlpiPlugin\Iservice\InstallSteps\HandleProfileRightsInstallStep;

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

echo (HandleProfileRightsInstallStep::removeRightsToRemoveForNotSuperAdmins() && HandleProfileRightsInstallStep::setDefaultProfileRights()) ? __('Profile rights have been reset to default settings', 'iservice') : __('An error occurred while resetting profile rights to default settings', 'iservice');
echo "<br>" . (HandleProfileRightsInstallStep::setDefaultProfileRightsForCustomFields() ? __('Profile rights for custom fields have been reset to default settings', 'iservice') : __('An error occurred while resetting custom fields profile rights to default settings', 'iservice'));

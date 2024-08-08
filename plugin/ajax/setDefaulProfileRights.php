<?php

require "../inc/includes.php";

use GlpiPlugin\Iservice\InstallSteps\HandleProfileRightsInstallStep;

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

echo (HandleProfileRightsInstallStep::removeRightsToRemoveForNotSuperAdmins() && HandleProfileRightsInstallStep::setDefaultProfileRights()) ? _t('Profile rights have been reset to default settings') : _t('An error occurred while resetting profile rights to default settings');
echo "<br>" . (HandleProfileRightsInstallStep::setDefaultProfileRightsForCustomFields() ? _t('Profile rights for custom fields have been reset to default settings') : _t('An error occurred while resetting custom fields profile rights to default settings'));

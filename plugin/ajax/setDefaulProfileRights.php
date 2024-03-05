<?php

require "../inc/includes.php";

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

function setDefaultProfileRights(): bool
{
    global $DB;

    $defaultValues = include PLUGIN_ISERVICE_DIR . '/inc/rights.config.php';

    foreach ($defaultValues['defaultValues'] ?? [] as $profileName => $rightValues) {
        foreach ($rightValues as $rightName => $rightValue) {
            $profile = new Profile();
            if (!$profile->getFromDBByRequest(
                [
                    'WHERE' => [
                        'name' => $profileName,
                    ],
                ]
            )
            ) {
                return false;
            }

            $profileId = $profile->fields['id'];
            if (!$DB->update(
                'glpi_profilerights',
                [
                    'rights' => $rightValue,
                ],
                [
                    'WHERE'  => [
                        'name' => $rightName,
                        'profiles_id' => $profileId,
                    ],
                ]
            )
            ) {
                return false;
            }
        }
    }

        return true;
}

echo setDefaultProfileRights() ? __('Profile rights have been reset to default settings', 'iservice') : __('An error occurred while resetting profile rights to default settings', 'iservice');

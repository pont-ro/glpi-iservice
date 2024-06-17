<?php

require "../inc/includes.php";

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

function setDefaultProfileRights(): bool
{
    global $DB;

    $rightsConfig = getRightsConfig();

    foreach ($rightsConfig['defaultValues'] ?? [] as $profileName => $rightValues) {
        foreach ($rightValues as $rightName => $rightValue) {
            $profile = getProfileByName($profileName);
            if (!$profile) {
                return false;
            }

            if (!$DB->update(
                'glpi_profilerights',
                [
                    'rights' => $rightValue,
                ],
                [
                    'WHERE'  => [
                        'name' => $rightName,
                        'profiles_id' => $profile->fields['id'],
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

function setDefaultProfileRightsForCustomFields(): bool
{
    global $DB;

    $profilesWithFullAccess = getRightsConfig()['customFieldsRightsSettings']['profilesWithFullAccess'] ?? [];

    foreach ($profilesWithFullAccess as $profileName) {
        $profile = getProfileByName($profileName);
        if (!$profile) {
            return false;
        }

        if (!$DB->update(
            'glpi_plugin_fields_profiles',
            [
                'right' => 4,
            ],
            [
                'WHERE'  => [
                    'profiles_id' => $profile->fields['id'],
                ],
            ]
        )
        ) {
            return false;
        }
    }

    return true;
}

function getRightsConfig(): array
{
    $rightsConfig = include PLUGIN_ISERVICE_DIR . '/inc/rights.config.php';

    return $rightsConfig ?? [];
}

function getProfileByName(String $profileName): Profile|bool
{
    $profile = new Profile();
    $result  = $profile->getFromDBByRequest(
        [
            'WHERE' => [
                'name' => $profileName,
            ],
        ]
    );

    return $result ? $profile : false;
}

echo setDefaultProfileRights() ? __('Profile rights have been reset to default settings', 'iservice') : __('An error occurred while resetting profile rights to default settings', 'iservice');
echo "<br>" . (setDefaultProfileRightsForCustomFields() ? __('Profile rights for custom fields have been reset to default settings', 'iservice') : __('An error occurred while resetting custom fields profile rights to default settings', 'iservice'));

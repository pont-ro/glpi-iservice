<?php

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

// Direct access to file.
if (strpos($_SERVER['PHP_SELF'], "importPartnerFromHMarfa.php")) {
    include '../inc/includes.php';
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkLoginUser();

$cui = IserviceToolBox::getInputVariable('cui');

if (empty($cui)) {
    echoError(_t('Field Cod Fiscal is required for import!'));
    return;
}

$result = PluginIserviceDB::getQueryResult(
    "
    select hmf.*, cfs.items_id from hmarfa_firme hmf
    left join glpi_plugin_fields_suppliersuppliercustomfields cfs ON hmf.cod = cfs.hmarfa_code_field
     where hmf.cod1 like '%$cui'
 "
);

if (count($result) > 1) {
    echoError(_t('Please make sure the CUI is correct, multiple partners found: ') . "<br>" . getMultiplePartnersList($result));
    return;
}

if (!empty($result[0])) {
    if (isPartnerAlreadyImported($result[0])) {
        echoError(_t('Partner already imported: ') . "<br>" . getPartnerLink(replaceNullString($result[0]['initiale'] ?? ''), $result[0]['items_id']));
        return;
    }

    echo json_encode(
        [
            'success' => true,
            'partnerData' => [
                'name' => replaceNullString($result[0]['initiale'] ?? ''),
                'type' => replaceNullString($result[0]['tip'] ?? ''),
                'phonenumbers' => getPhoneNumbers($result[0]),
                'fax' => replaceNullString($result[0]['fax'] ?? ''),
                'address' => getAddress($result[0]),
                'postcode' => replaceNullString($result[0]['codpostal'] ?? ''),
                'town' => replaceNullString($result[0]['localitate'] ?? ''),
                'comment' => replaceNullString($result[0]['obs'] ?? ''),
                'cui' => replaceNullString($result[0]['cod1'] ?? ''),
                'crn' => replaceNullString($result[0]['cod2'] ?? ''),
                'hmarfa_code' => replaceNullString($result[0]['cod'] ?? ''),
            ],
        ]
    );
} else {
    echoError(_t('Partner not found'));
}

function getAddress($data): string
{
    $address = [
        replaceNullString($data['adrs1']),
        replaceNullString($data['adrs2']),
        replaceNullString($data['adrp1']),
        replaceNullString($data['adrp2']),
    ];

    return implode(', ', array_filter($address));
}

function getPhoneNumbers($data): string
{
    $phoneNumbers = [
        replaceNullString($data['tel1']),
        replaceNullString($data['tel2']),
    ];

    return implode(', ', array_filter($phoneNumbers));
}

function replaceNullString($string): string
{
    return $string === 'NULL' ? '' : $string;
}

function echoError($errorMessages = ''): void
{
    echo json_encode(
        [
            'error' => true,
            'errorMessage' => $errorMessages ?? _t('An error occurred!'),
        ]
    );
}

function getMultiplePartnersList($result): string
{
    $partners = [];

    foreach ($result as $partner) {
        $text = replaceNullString($partner['cod1'] ?? '') . ' - ' . replaceNullString($partner['initiale'] ?? '');

        $partners[] = isPartnerAlreadyImported($partner) ? getPartnerLink($text, $partner['items_id']) : $text;
    }

    return implode("<br>", $partners);
}

function isPartnerAlreadyImported($data): bool
{
    return !empty($data['items_id']);
}

function getPartnerLink($linkText, $supplierId): string
{
    global $CFG_PLUGIN_ISERVICE;

    return "<a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/printer.form.php?supplier_id=$supplierId' target='_blank'>»»» $linkText «««</a>";
}

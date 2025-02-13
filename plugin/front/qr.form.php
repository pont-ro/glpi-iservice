<?php

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

require "../inc/includes.php";

$qr                       = new PluginIserviceQr();
$code                     = IserviceToolBox::getInputVariable('code');
$serialNumber             = IserviceToolBox::getInputVariable('serial_number');
$uniqueIdentificationCode = IserviceToolBox::getInputVariable('unique_identification_code');
$qrTicketData             = IserviceToolBox::getArrayInputVariable('qr_ticket_data');
$filesData                = [
    '_filename' => IserviceToolBox::getArrayInputVariable('_filename'),
    '_prefix_filename' => IserviceToolBox::getArrayInputVariable('_prefix_filename'),
    '_tag_filename' => IserviceToolBox::getArrayInputVariable('_tag_filename'),
    '_uploader_filename' => IserviceToolBox::getArrayInputVariable('_uploader_filename'),
];

if (!empty($code)
    && empty($serialNumber)
    && empty($uniqueIdentificationCode)
    && $qr->getFromDBByRequest(
        [
            'code' => $code
        ]
    )
) {
    if ($qr->isConnected() && !empty($qrTicketData)) {
        // Auth must happen before the header is sent!
        $auth = new Auth();
        $auth->login(PluginIserviceConfig::getConfigValue('qr_ticket_user'), PluginIserviceConfig::getConfigValue('qr_ticket_user_password'));

        PluginIserviceHtml::publicHeader(PluginIserviceQr::getTypeName());
        $qr->createTicket($qr, $qrTicketData, $filesData);

        Session::cleanOnLogout();
    } elseif ($qr->isConnected()) {
        PluginIserviceHtml::publicHeader(PluginIserviceQr::getTypeName());
        $qr->showConnectedForm($qr->getID());
    } else {
        PluginIserviceHtml::publicHeader(PluginIserviceQr::getTypeName());
        $qr->showConnectForm();
    }
} elseif (!empty($serialNumber) && !empty($uniqueIdentificationCode)) {
    if ($qr->connectCodeToPrinter($code, $serialNumber, $uniqueIdentificationCode)) {
        Html::redirect($qr->getFormURL() . "?code=$code");
    }
} else {
    echo TemplateRenderer::getInstance()->render(
        '@iservice/qr/message_page.html.twig', [
            'message' => __('Invalid QR code.')
        ]
    );
}

Html::requireJs('fileupload');
Html::footer();

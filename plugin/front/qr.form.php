<?php

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

require "../inc/includes.php";

$qr                       = new PluginIserviceQr();
$code                     = IserviceToolBox::getInputVariable('code');
$serialNumber             = IserviceToolBox::getInputVariable('serial_number');
$uniqueIdentificationCode = IserviceToolBox::getInputVariable('unique_identification_code');
$qrTicketData             = IserviceToolBox::getArrayInputVariable('qr_ticket_data');

if (!empty($code)
    && empty($serialNumber)
    && empty($compRegistrationNumber)
    && $qr->getFromDBByRequest(
        [
            'code' => $code
        ]
    )
) {
    PluginIserviceHtml::publicHeader(PluginIserviceQr::getTypeName());

    if ($qr->isConnected() && !empty($qrTicketData)) {
        if ($qr->createTicket($qr, $qrTicketData)) {
            Html::redirect($qr->getFormURL() . "?code=$code");
        };
    } elseif ($qr->isConnected()) {
        $qr->showConnectedForm($qr->getID());
    } else {
        $qr->showConnectForm($qr->getID());
    }
} elseif (!empty($serialNumber) && !empty($uniqueIdentificationCode)) {
    PluginIserviceHtml::publicHeader(PluginIserviceQr::getTypeName());

    $qr->connectCodeToPrinter($code, $serialNumber, $uniqueIdentificationCode);
    Html::redirect($qr->getFormURL() . "?code=$code");
} else {
    PluginIserviceHtml::publicHeader(PluginIserviceQr::getTypeName());
    echo TemplateRenderer::getInstance()->render(
        '@iservice/qr/message_page.html.twig', [
            'message' => __('Invalid QR code.')
        ]
    );
}

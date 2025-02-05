<?php

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

require "../inc/includes.php";

$qr                       = new PluginIserviceQr();
$code                     = IserviceToolBox::getInputVariable('code');
$serialNumber             = IserviceToolBox::getInputVariable('serial_number');
$uniqueIdentificationCode = IserviceToolBox::getInputVariable('unique_identification_code');
$qrTicketData             = IserviceToolBox::getArrayInputVariable('qr_ticket_data');

PluginIserviceHtml::publicHeader(PluginIserviceQr::getTypeName());

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
        if ($qr->createTicket($qr, $qrTicketData)) {
            Html::redirect($qr->getFormURL() . "?code=$code");
        };
    } elseif ($qr->isConnected()) {
        $qr->showConnectedForm($qr->getID());
    } else {
        $qr->showConnectForm($qr->getID());
    }
} elseif (!empty($serialNumber) && !empty($uniqueIdentificationCode)) {
    $qr->connectCodeToPrinter($code, $serialNumber, $uniqueIdentificationCode);
    Html::redirect($qr->getFormURL() . "?code=$code");
} else {
    echo TemplateRenderer::getInstance()->render(
        '@iservice/qr/message_page.html.twig', [
            'message' => __('Invalid QR code.')
        ]
    );
}

Html::footer();

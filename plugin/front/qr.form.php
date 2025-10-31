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

// Auth must happen before the header is sent!
if (!PluginIserviceQr::loginQrUser()) {
    PluginIserviceHtml::publicHeader(PluginIserviceQr::getTypeName());
    Html::displayErrorAndDie(_t('Authorization error. Please make sure cookies are enabled in your browser.'));
}

// Cookies must be set before header is sent!
if (!empty($qrTicketData)) {
    setcookie(
        'qrFormData',
        json_encode([
            'contact' => $qrTicketData['contact_person'],
            'email' => $qrTicketData['send_email'],
        ]),
        time() + 365 * 24 * 60 * 60, '/'
    );
}


PluginIserviceHtml::publicHeader(PluginIserviceQr::getTypeName());

if (!empty($code)
    && empty($serialNumber)
    && empty($uniqueIdentificationCode)
    && $qr->getFromDBByRequest(
        [
            'code' => $code,
            'is_deleted' => 0
        ]
    )
) {
    if ($qr->isConnected() && !empty($qrTicketData)) {
        $qr->createTicket($qr, $qrTicketData, $filesData);
    } elseif ($qr->isConnected()) {
        $qr->showConnectedForm($qr->getID());
    } else {
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

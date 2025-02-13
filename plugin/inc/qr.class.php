<?php
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    include_once $vendorAutoload;
} else {
    throw new \Exception('Vendor autoload file not found. Please run composer install in the plugin directory.');
}

use Glpi\Application\View\TemplateRenderer;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * PluginIserviceQr Class
 * */
class PluginIserviceQr extends CommonDBTM
{
    public const QR_TICKET_NAME                  = 'Citire QR';
    public const QR_TICKETS_NUMBER_LIMIT_PER_DAY = 5;

    public static $rightname = 'plugin_iservice_view_qrs';

    public static function getTypeName($nb = 0): string
    {
        return _tn('QR Code', 'QR Codes', $nb);
    }

    public function getRawName(): string
    {
        return _tn('QR code', 'QR codes', 1) . " #" . $this->getID();
    }

    public static function generateQrCode(int $id): string
    {
        return base64_encode(mt_rand(10000, 99999) . str_pad($id, 5, '0', STR_PAD_LEFT) . mt_rand(10000, 99999));
    }

    public static function generateQrCodes($numberOfCodesToGenerate = 10): array
    {
        global $DB;
        $qrs    = $DB->request("SELECT `auto_increment` FROM INFORMATION_SCHEMA.TABLES WHERE table_name = '" . self::getTable() . "'");
        $nextId = $qrs->current()['auto_increment'];
        if ($nextId < 500) {
            $nextId = 500;
            $DB->request("ALTER TABLE " . self::getTable() . " AUTO_INCREMENT = $nextId");
        }

        $ids = [];

        $massInsertQuery = "INSERT INTO " . self::getTable() . " (itemtype, code) VALUES";
        for ($i = $nextId; $i < $nextId + $numberOfCodesToGenerate; $i++) {
            $massInsertQuery .= " ('Printer', '" . self::generateQrCode($i) . "'),";
            $ids[$i]          = true;
        }

        $massInsertQuery = rtrim($massInsertQuery, ',');
        if ($DB->request($massInsertQuery)) {
            return $ids;
        };

        return [];
    }

    public function showConnectForm(): void
    {
        echo TemplateRenderer::getInstance()->render('@iservice/qr/not_connected.html.twig');
    }

    public function showConnectedForm(int $id): void
    {
        $qr      = new self();
        $printer = new PluginIservicePrinter();

        if (!$qr->getFromDB($id)) {
            self::renderMessageTemplate(_t('Invalid QR code.'));

            return;
        }

        if (!$printer->getFromDB($qr->fields['items_id'])) {
            self::renderMessageTemplate(_t('Can not find printer.'));

            return;
        }

        $lastClosedTicketForPrinter = PluginIserviceTicket::getLastForPrinterOrSupplier(0, $printer->getID());

        $data = [
            'isColorPrinter' => $printer->isColor(),
            'isPlotter' => $printer->isPlotter(),
            'infoMessage' => sprintf(_t("You are connected to printer %s serial %s. Please check the toners your replaced and fill other fields if applicable. The data will be sent after pressing the \"Send\" button."), $printer->fields['original_name'], $printer->fields['serial']),
            'total2BlackRequiredMinimum' => $lastClosedTicketForPrinter->customfields->fields['total2_black_field'] ?? null,
            'total2ColorRequiredMinimum' => $lastClosedTicketForPrinter->customfields->fields['total2_color_field'] ?? null,
        ];

        $data['countersDefaultValues'] = PluginIserviceTicket::getCountersDefaultValues($printer, new PluginIserviceTicket(),  $lastClosedTicketForPrinter) ?? [];

        echo TemplateRenderer::getInstance()->render('@iservice/qr/connected.html.twig', $data);
    }

    public function connectCodeToPrinter($code, $printerSerialNumber, $uniqueIdentificationCode): bool
    {
        global $DB;

        $printerSerialNumber      = preg_replace('/\s+/', '', $printerSerialNumber);
        $uniqueIdentificationCode = preg_replace('/\s+/', '', $uniqueIdentificationCode);
        $qr                       = new self();

        $result = $DB->request(
            "SELECT 
                p.id as printer_id
                , p.name
                , p.serial
                , p.supplier_id
                , cfs.uic_field
                 FROM glpi_plugin_iservice_printers p
                LEFT JOIN glpi_plugin_fields_suppliersuppliercustomfields cfs ON p.supplier_id = cfs.items_id AND cfs.itemtype = 'Supplier'
                WHERE  
                    REPLACE(p.serial, ' ', '') = '$printerSerialNumber' 
                    AND REPLACE(cfs.uic_field, ' ', '') LIKE '%$uniqueIdentificationCode%'"
        );

        $printerSupplierData = [];
        if (count($result) == 1) {
            $printerSupplierData = $result->current();
        } else {
            self::renderMessageTemplate(sprintf(_t('We could not connect QR code to printer with serial %s'), $printerSerialNumber));

            return false;
        }

        $qrsConnectedToPrinter = $qr->find(
            [
                'items_id' => $printerSupplierData['printer_id'],
                'is_deleted' => 0,
            ]
        );

        if (count($qrsConnectedToPrinter) > 0) {
            self::renderMessageTemplate(sprintf(_t('Printer with serial %s is already connected to an other QR code.'), $printerSerialNumber));

            return false;
        }

        $qr->getFromDBByRequest(
            [
                'code' => $code,
            ]
        );

        if (!$qr->update(
            [
                'id'       => $qr->getID(),
                'itemtype' => 'Printer',
                'items_id' => $printerSupplierData['printer_id'],
                'modify_date' => $_SESSION['glpi_currenttime'],
            ]
        )
        ) {
            self::renderMessageTemplate(sprintf(_t('We could not connect QR code to printer with serial %s'), $printerSerialNumber));

            return false;
        }

        return true;
    }

    public function isConnected(): bool
    {
        return !empty($this->fields['items_id']);
    }

    public function createTicket(PluginIserviceQr $qr, array $qrTicketData, array $filesData): bool
    {
        if (!$this->canCreateQrTicket()) {
            self::renderMessageTemplate(_t('You have reached the maximum number of tickets that can be created from QR codes. Please contact the administrator if you need more tickets.'));

            return false;
        }

        $ticket  = new PluginIserviceTicket();
        $printer = new PluginIservicePrinter();
        $printer->getFromDB($qr->fields['items_id']);

        $message            = $qrTicketData['message'] ?? null;
        $replacedCartridges = [];

        if (!empty($qrTicketData['replaced_cartridges'])) {
            $replacedCartridges = array_keys(array_filter($qrTicketData['replaced_cartridges']));
        }

        $input = [
            'status' => Ticket::INCOMING,
            'add' => 1,
            'name' => self::QR_TICKET_NAME,
            'content' => $message,
            '_suppliers_id_assign' => $printer->fields['supplier_id'],
            'printer_id' => $qr->fields['items_id'],
            'date' => $_SESSION['glpi_currenttime'],
            'itilcategories_id' => PluginIserviceTicket::getItilCategoryId('Sesizare externa'),
            'total2_black_field' => $qrTicketData['total2_black_field'] ?? null,
            'total2_color_field' => $qrTicketData['total2_color_field'] ?? null,
            '_do_not_compute_status' => true, // This is needed to avoid ticket status change in CommonITILObject.php:prepareInputForUpdate method, line 1780.
            'effective_date_field' => $_SESSION['glpi_currenttime'],
            '_no_message' => true,
        ];

        $ticketId = $ticket->add(array_merge($input, $filesData));

        if (empty($ticketId)) {
            self::renderMessageTemplate(_t('Could not create ticket!'));

            return false;
        }

        $ticket->addPrinter($ticketId, $input);

        if (!empty($replacedCartridges)) {
            $ticket->fields['id']                     = $ticketId;
            $ticket->fields['items_id']['Printer'][0] = $qr->fields['items_id'];
            $ticket->fields['_suppliers_id_assign']   = $printer->fields['supplier_id'];
            $availableCartridges                      = PluginIserviceCartridgeItem::getChangeablesForTicket($ticket);

            $message .= "<br>" . _t('Replaced cartridges:') . "<br>";
            foreach ($replacedCartridges as $colorId) {
                $result              = self::addCartridgeBasedOnColorId($colorId, $qr, $ticket, $printer, $availableCartridges);
                $installedCartridges = PluginIservicePrinter::getInstalledCartridges($printer->getID(), "AND c.plugin_fields_cartridgeitemtypedropdowns_id = $colorId");
                $tonerId             = array_values($installedCartridges ?: [])[0]['id'] ?? 'N/A';
                $color               = IserviceToolBox::getCartridgeNameByColorId($colorId);

                if ($result === false) {
                    $message .= sprintf(_t("Replaced toner with id: %s, color %s, but there was an error while adding it to the ticket"), $tonerId, $color) . ".<br>";
                } elseif ($result !== true) {
                    $message .= sprintf(_t("Replaced toner with id: %s, color %s, but there was an error while adding it to the ticket"), $tonerId, $color) . ": " . $result . "<br>";
                } else {
                    $message .= sprintf(_t("Replaced toner with id: %s, color %s"), $tonerId, $color) . "<br>";
                }
            }

            global $DB;

            // Ticket update from model doesn't work if user is not logged in.
            $DB->request("UPDATE glpi_tickets SET content = '$message' WHERE id = $ticketId");
        }

        self::renderMessageTemplate($this->getTicketCreatedMessage($ticketId, $printer, $replacedCartridges, $qrTicketData));

        return true;
    }

    public static function renderMessageTemplate(string $message): void
    {
        echo TemplateRenderer::getInstance()->render(
            '@iservice/qr/message_page.html.twig',  [
                'message' => $message,
            ]
        );
    }

    public static function addCartridgeBasedOnColorId(string $colorId, self $qr, PluginIserviceTicket $ticket, PluginIservicePrinter $printer, array $availableCartridges): bool|string
    {
        if (empty($availableCartridges)) {
            return _t('No cartridges to add.');
        }

        foreach ($availableCartridges as $availableCartridgeItem) {
            if ($availableCartridgeItem['plugin_fields_cartridgeitemtypedropdowns_id'] === $colorId) {
                $inputForAddCartridge = [
                    'printer_id' => $qr->fields['items_id'],
                    'suppliers_id' => $printer->fields['supplier_id'],
                    'cartridge_install_date_field' => $_SESSION['glpi_currenttime'],
                    'effective_date_field' => $_SESSION['glpi_currenttime'],
                    '_plugin_iservice_cartridge' => [
                        'cartridgeitems_id' => $availableCartridgeItem['id'] . 'l' . $availableCartridgeItem['locations_id_field'],
                    ],
                ];

                $errorMessage = '';
                if (!$ticket->addCartridge($ticket->getID(), $inputForAddCartridge, $errorMessage)) {
                    return !empty($errorMessage) ? $errorMessage : false;
                }

                return true;
            }
        }

        return false;
    }

    public static function downloadQrCodes(Array $ids): void
    {
        global $DB;
        $qr  = new self();
        $ids = array_keys(array_filter($ids));

        $formUrl = PluginIserviceConfig::getConfigValue('url_base') . $qr->getFormURL() . "?code=";
        $qrs     = $DB->request(
            "
            SELECT id, code 
            FROM " . $qr::getTable() . " 
            WHERE id IN (" . implode(',', $ids) . ")
            "
        );

        $codes = [];
        foreach ($qrs as $qr) {
            $codes[$qr['id']] = $formUrl . $qr['code'];
        }

        self::prepareCodesForDownload($codes);
    }

    public static function assignQrCodesToTechnician(Array $ids, int $technicianId): void
    {
        global $DB;
        $qr  = new self();
        $ids = array_keys(array_filter($ids));

        if (!$DB->update($qr::getTable(), ['users_id_tech' => $technicianId], ['id' => $ids])) {
            Session::addMessageAfterRedirect(_t('Could not assign QR code(s) to technician.'), true, ERROR, true);
        }
    }

    public static function disconnectQrCodes(Array $ids)
    {
        global $DB;
        $qr  = new self();
        $ids = array_keys(array_filter($ids));

        if (!$DB->update($qr::getTable(), ['items_id' => null], ['id' => $ids])) {
            Session::addMessageAfterRedirect(_t('Could not disconnect QR codes.'), true, ERROR, true);
        }
    }

    public static function deleteQrCodes(Array $ids)
    {
        global $DB;
        $qr  = new self();
        $ids = array_keys(array_filter($ids));

        if (!$DB->update($qr::getTable(), ['is_deleted' => 1, 'date_mod' => $_SESSION['glpi_currenttime']], ['id' => $ids])) {
            Session::addMessageAfterRedirect(_t('Could not delete QR codes.'), true, ERROR, true);
        }
    }

    private function canCreateQrTicket()
    {
        $tickets = (new PluginIserviceTicket())->find(
            [
                'name' => self::QR_TICKET_NAME,
                'date_creation' => ['>=', date('Y-m-d H:i:s', strtotime('-1 day'))],
            ]
        );

        return count($tickets) < self::QR_TICKETS_NUMBER_LIMIT_PER_DAY;
    }

    private static function checkRequiredExtensions(): void
    {
        if (!extension_loaded('gd')) {
            throw new \Exception('GD extension is required for QR code generation');
        }

        if (!function_exists('imagettftext')) {
            throw new \Exception('FreeType support is required for TTF fonts. Please install php-gd with FreeType support');
        }
    }

    private static function getQrCodeOptions(): QROptions
    {
        return new QROptions(
            [
                'version'          => QRCode::VERSION_AUTO,
                'outputType'       => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel'         => QRCode::ECC_H,
                'scale'            => 10,
                'imageBase64'      => false,

                // Basic styling.
                'imageTransparent' => false,
                'bgColor'          => [255, 255, 255],
                'frontColor'       => [0, 0, 0],

                // Add quiet zone (white space around QR).
                'addQuietzone'     => true,
                'quietzoneSize'    => 2,

                // Space for logo.
                'imagickFormat'    => 'png',
                'returnResource'   => true
            ]
        );
    }

    private static function createBaseImage($qrImage): array
    {
        $qrWidth  = imagesx($qrImage);
        $qrHeight = imagesy($qrImage);

        // Create a larger image to accommodate text below QR code.
        $textHeight = 40; // Space for text.
        $finalImage = imagecreatetruecolor($qrWidth, $qrHeight + $textHeight);

        // Fill with white background.
        $white = imagecolorallocate($finalImage, 255, 255, 255);
        imagefill($finalImage, 0, 0, $white);

        // Copy QR code to new image.
        imagecopy($finalImage, $qrImage, 0, 0, 0, 0, $qrWidth, $qrHeight);
        imagedestroy($qrImage);

        return [
            'image'      => $finalImage,
            'width'      => $qrWidth,
            'height'     => $qrHeight,
            'textHeight' => $textHeight
        ];
    }

    private static function addLogo($image, $qrWidth, $qrHeight): void
    {
        $logo = __DIR__ . '/../assets/pics/logos/logo-G-100-white.png';
        if (!file_exists($logo)) {
            return;
        }

        // Load logo with transparency preserved.
        $logoGD = imagecreatefrompng($logo);
        imagesavealpha($logoGD, true);

        $logoWidth  = imagesx($logoGD);
        $logoHeight = imagesy($logoGD);

        // Calculate logo size (25% of QR code).
        $newLogoWidth  = $qrWidth * 0.25;
        $newLogoHeight = $logoHeight * ($newLogoWidth / $logoWidth);

        // Create resized logo with transparent background.
        $resizedLogo = imagecreatetruecolor($newLogoWidth, $newLogoHeight);
        imagealphablending($resizedLogo, false);
        imagesavealpha($resizedLogo, true);
        $transparent = imagecolorallocatealpha($resizedLogo, 0, 0, 0, 127);
        imagefill($resizedLogo, 0, 0, $transparent);

        // Resize logo.
        imagecopyresampled(
            $resizedLogo,
            $logoGD,
            0,
            0,
            0,
            0,
            $newLogoWidth,
            $newLogoHeight,
            $logoWidth,
            $logoHeight
        );

        // Calculate logo position (center of QR code).
        $logoX = ($qrWidth - $newLogoWidth) / 2;
        $logoY = ($qrHeight - $newLogoHeight) / 2;

        // Copy resized logo with transparent background to QR code.
        imagecopy(
            $image,
            $resizedLogo,
            $logoX,
            $logoY,
            0,
            0,
            $newLogoWidth,
            $newLogoHeight
        );

        imagedestroy($resizedLogo);
        imagedestroy($logoGD);
    }

    private static function addText($image, $id, $qrWidth, $qrHeight, $textHeight): void
    {
        $label     = "QR Code #" . $id;
        $fontSize  = 12;
        $fontColor = imagecolorallocate($image, 0, 0, 0);
        $font      = __DIR__ . '/../assets/fonts/arial.ttf';

        if (file_exists($font)) {
            self::addTtfText($image, $label, $font, $fontSize, $fontColor, $qrWidth, $qrHeight, $textHeight);
        } else {
            self::addBasicText($image, $label, $fontColor, $qrWidth, $qrHeight, $textHeight);
        }
    }

    private static function addTtfText($image, $label, $font, $fontSize, $fontColor, $qrWidth, $qrHeight, $textHeight): void
    {
        $bbox      = imagettfbbox($fontSize, 0, $font, $label);
        $textWidth = $bbox[2] - $bbox[0];
        $textX     = ($qrWidth - $textWidth) / 2;
        $textY     = $qrHeight + ($textHeight / 2) + ($fontSize / 2);

        imagettftext(
            $image,
            $fontSize,
            0,
            $textX,
            $textY,
            $fontColor,
            $font,
            $label
        );
    }

    private static function addBasicText($image, $label, $fontColor, $qrWidth, $qrHeight, $textHeight): void
    {
        $fontSize  = 3;
        $textWidth = strlen($label) * imagefontwidth($fontSize);
        $textX     = ($qrWidth - $textWidth) / 2;
        $textY     = $qrHeight + ($textHeight / 2);

        imagestring(
            $image,
            $fontSize,
            $textX,
            $textY,
            $label,
            $fontColor
        );
    }

    private static function createZipFile(array $qrs, string $tempDir): string
    {
        $zipFile = $tempDir . '/qrcodes.zip';
        $zip     = new ZipArchive();
        $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $options = self::getQrCodeOptions();

        foreach ($qrs as $id => $url) {
            $qrcode    = new QRCode($options);
            $qrImage   = $qrcode->render($url);
            $imageData = self::createBaseImage($qrImage);

            self::addLogo($imageData['image'], $imageData['width'], $imageData['height']);
            self::addText($imageData['image'], $id, $imageData['width'], $imageData['height'], $imageData['textHeight']);

            // Save QR code to file.
            $fileName = "qr_code_{$id}.png";
            $filePath = $tempDir . '/' . $fileName;
            imagepng($imageData['image'], $filePath);
            imagedestroy($imageData['image']);

            $zip->addFile($filePath, $fileName);
        }

        $zip->close();
        return $zipFile;
    }

    public static function prepareCodesForDownload(array $qrs): void
    {
        self::checkRequiredExtensions();

        // Create temporary directory.
        $tempDir = GLPI_TMP_DIR . '/qrcodes_' . uniqid();
        if (!is_dir($tempDir)) {
            mkdir($tempDir);
        }

        // Create and send ZIP file.
        $zipFile = self::createZipFile($qrs, $tempDir);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="qrcodes.zip"');
        header('Content-Length: ' . filesize($zipFile));
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($zipFile);

        // Clean up.
        array_map('unlink', glob($tempDir . '/*'));
        rmdir($tempDir);
        exit();
    }

    public function getTicketCreatedMessage($ticketId, PluginIservicePrinter $printer, array $replacedCartridges, array $qrTicketData): string
    {
        $message = sprintf(_t('Ticket with ID: %s was created for printer %s serial %s.'), $ticketId, $printer->fields['name'], $printer->fields['serial']);

        if (!empty($replacedCartridges)) {
            foreach ($replacedCartridges as $colorId) {
                $color    = IserviceToolBox::getCartridgeNameByColorId($colorId);
                $message .= "<br>" . sprintf(_t("Replaced %s toner"), $color);
            }
        }

        if (!empty($qrTicketData['total2_black_field'])) {
            $message .= "<br>" . _t('Black counter') . ': ' . $qrTicketData['total2_black_field'];
        }

        if (!empty($qrTicketData['total2_color_field'])) {
            $message .= "<br>" . _t('Color counter') . ': ' . $qrTicketData['total2_color_field'];
        }

        if (!empty($qrTicketData['message'])) {
            $message .= "<br>" . _t('Message') . ': ' . $qrTicketData['message'];
        }

        $message .= "<br>" . _t('Close this page and scan the QR code again for a new report!');

        return $message;
    }

}

<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

class PluginIserviceHmarfa
{

    const EXPORT_MODE_PRINTER      = 1;
    const EXPORT_MODE_TICKET       = 2;
    const EXPORT_MODE_MASS_INVOICE = 3;

    public static function showExportForm($id, $mode = self::EXPORT_MODE_PRINTER): void
    {
        switch ($mode) {
        case self::EXPORT_MODE_PRINTER:
            self::showPrinterExportForm($id);
            break;
        case self::EXPORT_MODE_TICKET:
            self::showTicketExportForm($id);
            break;
        case self::EXPORT_MODE_MASS_INVOICE:
            PluginIserviceHmarfa_Invoicer::showInvoiceExportForm();
            break;
        default:
            HTML::displayErrorAndDie("Unknown export mode: $mode");
        }
    }

    public static function showPrinterExportForm($id): void
    {
        global $DB, $CFG_PLUGIN_ISERVICE;

        include_once __DIR__ . '/TValuta.php';

        try {
            $currency_error    = null;
            $official_currency = new TValuta();
        } catch (Exception $ex) {
            $currency_error = $ex->getMessage();
        }

        $add_disabled_reason    = '';
        $import_disabled_reason = '';
        $acknowledge_other_csvs = IserviceToolBox::getInputVariable('acknowledge_other_csvs');
        $exportFilePath         = IserviceToolBox::getInputVariable('exportfilepath', PluginIserviceConfig::getConfigValue('hmarfa.export.default_path') . '/');
        $backFilePath           = $exportFilePath . "BAK";
        if (!file_exists($backFilePath)) {
            mkdir($backFilePath, 0775, true);
        }

        if (isset($_POST['hard_refresh'])) {
            unset($_POST);
        }

        $printer         = new PluginIservicePrinter();
        $manufacturer    = new Manufacturer();
        $printerState    = new State();
        $printerModel    = new PrinterModel();
        $printerLocation = new Location();
        $infoCom         = new Infocom();
        $enterprise      = new PluginIservicePartner();

        $printer->getFromDB($id);
        $infoCom->getFromDBforDevice('Printer', $id);
        $manufacturer->getFromDB($printer->fields["manufacturers_id"]);
        $printerState->getFromDB($printer->fields['states_id']);
        $printerModel->getFromDB($printer->fields["printermodels_id"]);
        $printerLocation->getFromDB($printer->fields["locations_id"]);
        $printerLocationName = $printer->customfields->fields["usage_address_field"] ?? '';

        if (isset($_POST['generate_magic_link'])) {
            PluginIservicePartner::generateNewMagicLink($infoCom->fields['suppliers_id']);
            $_POST['refresh'] = 'refresh';
        }

        $enterprise->getFromDB($infoCom->fields['suppliers_id']);
        $months       = [
            1 => 'ianuarie',
            2 => 'februarie',
            3 => 'martie',
            4 => 'aprilie',
            5 => 'mai',
            6 => 'iunie',
            7 => 'iulie',
            8 => 'august',
            9 => 'septembrie',
            10 => 'octombrie',
            11 => 'noiembrie',
            12 => 'decembrie',
        ];
        $mail_subject = "Factura ExpertLine - {$enterprise->fields['name']} - " . $months[date("n")] . ", " . date("Y");
        $mail_body    = $enterprise->getMailBody();

        $query = "SELECT glpi_contracts_items.* "
                . " FROM glpi_contracts_items, glpi_contracts LEFT JOIN glpi_entities ON (glpi_contracts.entities_id=glpi_entities.id)"
                . " WHERE glpi_contracts.id=glpi_contracts_items.contracts_id AND glpi_contracts_items.items_id = '$id' AND glpi_contracts_items.itemtype = 'Printer' "
                . getEntitiesRestrictRequest(" AND", "glpi_contracts", '', '', true) . " ORDER BY glpi_contracts.name";

        $result   = $DB->query($query);
        $number   = $DB->numrows($result);
        $contract = new PluginIserviceContract();
        if ($number > 0) {
            $contract->getFromDB($DB->result($result, 0, "contracts_id"));
        }

        $contract_customfields = new PluginFieldsContractcontractcustomfield();
        PluginIserviceDB::populateByItemsId($contract_customfields, $contract->getID());

        $contractType = new ContractType();
        if (!empty($contract->fields['contracttypes_id']) && $contractType->getFromDB($contract->fields['contracttypes_id'])) {
            $contractType = $contractType->fields["name"];
        } else {
            $contractType = '';
        }

        $nrcmd                     = IserviceToolBox::getInputVariable('nrcmd') ?: '000000';
        $invoice_date_field        = strtotime(IserviceToolBox::getInputVariable('invoice_date_field') ?: $printer->customfields->fields['invoice_date_field']);
        $invoice_expiry_date_field = strtotime(IserviceToolBox::getInputVariable('invoice_expiry_date_field') ?: $printer->customfields->fields['invoice_expiry_date_field']);
        $effective_date            = strtotime($printer->lastTicket()->customfields->fields['effective_date_field'] ?? '');
        $part_email_f1             = IserviceToolBox::getInputVariable('email_for_invoices_field') ?: $enterprise->customfields->fields['email_for_invoices_field'];
        $fixed1                    = IserviceToolBox::getInputVariable('fixed1') ?: false;
        $fixed2                    = IserviceToolBox::getInputVariable('fixed2') ?: false;
        $fixed3                    = IserviceToolBox::getInputVariable('fixed3') ?: false;

        $location    = (!empty($_POST['location'])) ? "{$printer->customfields->fields["usage_address_field"]} - " : "";
        $cost_center = (!empty($_POST['cost_center'])) ? "{$printer->customfields->fields["cost_center_field"]} - " : "";

        if ($invoice_expiry_date_field === false) {
            $invoice_expiry_date_field = strtotime(date("Y-m-t"));
        }

        $expDate = $invoice_expiry_date_field;
        if (date('d', $expDate) > 25) {
            $period  = date("m.Y", strtotime("+1 month", strtotime("-5 days", $expDate)));
            $expDate = date("Y-m-t", strtotime("+1 month", strtotime("-5 days", $expDate)));
        } else {
            $period  = date("d.m.y", strtotime("+1 day", $expDate)) . "-" . date("d.m.y", strtotime("+1 month", $expDate));
            $expDate = date("Y-m-d", strtotime("+1 month", $expDate));
        }

        $descrPart = "{$manufacturer->fields["name"]} {$printerModel->fields["name"]} ($cost_center$location{$printer->fields["serial"]})";

        $doc_date     = strtotime(IserviceToolBox::getInputVariable('doc_date') ?: date("Y-m-d"));
        $exp_date     = strtotime(IserviceToolBox::getInputVariable('exp_date') ?: $expDate);
        $divizorCopii = (empty($contract_customfields->fields['copy_price_divider_field']) || $contract_customfields->fields['copy_price_divider_field'] == 0) ? 1 : $contract_customfields->fields['copy_price_divider_field'];

        $contract_rate     = floatval((empty($contract_customfields->fields['currency_field']) || $contract_customfields->fields['currency_field'] == 0) ? 1 : $contract_customfields->fields['currency_field']);
        $contract_currency = $contract_rate > 1 ? "EUR" : ($contract_rate < 1 ? "???" : "RON");
        $contract_value    = $contract_customfields->fields['monthly_fee_field'] ?? 0;
        $rate              = isset($_POST['rate']) ? (is_numeric($_POST['rate']) ? $_POST['rate'] : intval($_POST['rate'])) : (($contract_currency === "EUR" && $currency_error === null) ? $official_currency->EuroCaNumar : $contract_rate);
        $currency          = $rate > 1 ? "EUR" : ($rate < 1 ? "???" : "RON");

        // $rate: $printer->tableData['invoice_rate']
        // $divizorCopii: $printer->tableData['divizor_copii'];
        $oldCounterBlack        = ($_POST['old_counter_black'] ?? $printer->customfields->fields['invoiced_total_black_field']) ?: 0; // $printer->tableData['contor_bk_uf']
        $oldCounterColor        = ($_POST['old_counter_color'] ?? $printer->customfields->fields['invoiced_total_color_field']) ?: 0; // $printer->tableData['contor_col_uf']
        $newCounterBlack        = $printer->lastTicket()->customfields->fields['total2_black_field'] ?? 0; // $printer->tableData['contor_bk_ui']
        $newCounterColor        = $printer->lastTicket()->customfields->fields['total2_color_field'] ?? 0; // $printer->tableData['contor_col_ui']
        $includedCopiesBlack    = $contract_customfields->fields['included_copies_bk_field'] ?? 0; // $printer->tableData['included_copies_bk_field']
        $includedCopiesColor    = $contract_customfields->fields['included_copies_col_field'] ?? 0; // $printer->tableData['included_copies_col_field']
        $includedCopiesValue    = $contract_customfields->fields['included_copy_value_field'] ?? 0; // $printer->tableData['included_copy_value_field']
        $blackCopyPrice         = $contract_customfields->fields['copy_price_bk_field'] ?? 0; // $printer->tableData['copy_price_bk_field']
        $colorCopyPrice         = $contract_customfields->fields['copy_price_col_field'] ?? 0; // $printer->tableData['copy_price_col_field']
        $allowedCounterBlack    = $oldCounterBlack + $includedCopiesBlack; // $printer->tableData['contor_bk_uf'] + $printer->tableData['included_copies_bk_field']
        $allowedCounterColor    = $oldCounterColor + $includedCopiesColor; // $printer->tableData['contor_col_uf'] + $printer->tableData['included_copies_col_field']
        $differenceCounterBlack = $newCounterBlack - $allowedCounterBlack; // $printer->tableData['contor_bk_ui'] -
        $differenceCounterColor = $newCounterColor - $allowedCounterColor;
        if (in_array(strtolower($contractType), ['coluna_1', 'coluna_2', 'coluna_3', 'cotrim'])) {
            $cod1 = 'S049';
            $cod2 = '';
            $cod3 = '';
        } else {
            if (stripos($contractType, 'coluna') === 0) {
                $cod = 'S045';
            } elseif (stripos($contractType, 'cofull') === 0) {
                $cod = 'S047';
            } elseif (stripos($contractType, 'coinc') === 0 || stripos($contractType, 'proiect') === 0) {
                $cod = 'S048';
            } else {
                $cod = 'S048';
            }

            $cod1 = $cod . "L";
            if ($includedCopiesValue > 0) {
                $cod2 = $cod === 'S045' ? "" : $cod . "V";
                $cod3 = "";
            } else {
                $cod2 = $cod === 'S045' ? "" : $cod . "B";
                $cod3 = $cod === 'S045' ? "" : $cod . "C";
            }
        }

        $codmat1   = isset($_POST['codmat1']) ? $_POST['codmat1'] : $cod1;
        $val1      = number_format($contract_value * $rate, 2, '.', '');
        $usedValue = $blackCopyPrice * ($newCounterBlack - $oldCounterBlack) + $colorCopyPrice * ($newCounterColor - $oldCounterColor);
        $cant1     = number_format((isset($_POST['cant1']) && $fixed1) ? $_POST['cant1'] : ($val1 == 0 ? 0 : 1), 2, '.', '');

        if ($cant1 > 0) {
            $descr         = "$descrPart / $period";
            $from_counter1 = $oldCounterBlack . ($printer->isColor() ? " - $oldCounterColor" : "");
            if ($includedCopiesValue > 0) {
                $untilPart  = ($newCounterBlack > 0) ? "$newCounterBlack copii alb-negru" : '';
                $untilPart .= ($printer->isColor() && $newCounterColor > 0) ? (empty($untilPart) ? '' : ' si ') . "$newCounterColor copii color" : '';
                if ($usedValue <= $includedCopiesValue) {
                    $descr .= " pana la $untilPart";
                }

                $to_counter1 = $newCounterBlack > 0 ? $newCounterBlack : "";
                if (!empty($to_counter1)) {
                    $to_counter1 .= ' - ';
                }

                $to_counter1 .= $newCounterColor > 0 ? $newCounterColor : "";
            } else {
                $to_counter1 = "";
                if (!empty($includedCopiesBlack)) {
                    if ($differenceCounterBlack <= 0) {
                        $descr       .= " pana la $newCounterBlack copii alb-negru";
                        $to_counter1 .= $newCounterBlack;
                    } else {
                        $descr       .= " de la $oldCounterBlack la $allowedCounterBlack copii alb-negru";
                        $to_counter1 .= $allowedCounterBlack;
                    }
                }

                if (!empty($includedCopiesColor)) {
                    if (!empty($includedCopiesBlack)) {
                        $descr       .= " si";
                        $to_counter1 .= ' - ';
                    }

                    if ($differenceCounterColor <= 0) {
                        $descr       .= " pana la $newCounterColor copii color";
                        $to_counter1 .= $newCounterColor;
                    } else {
                        $descr       .= " de la $oldCounterColor la $allowedCounterColor copii color";
                        $to_counter1 .= $allowedCounterColor;
                    }
                }
            }
        } else {
            $descr         = "";
            $from_counter1 = 0;
            $to_counter1   = 0;
        }

        $descr1 = (isset($_POST['refresh']) || !isset($_POST['descr1'])) ? $descr : $_POST['descr1'];

        $codmat2         = isset($_POST['codmat2']) ? $_POST['codmat2'] : $cod2;
        $conformContract = "";
        if ($cant1 > 0) {
            $descrPart = "{$printerModel->fields["name"]} ($cost_center{$printer->fields["serial"]})";
        }

        if ($includedCopiesValue > 0) {
            $val2          = number_format(($usedValue - $includedCopiesValue > 0) ? ($usedValue - $includedCopiesValue) * $rate : 0, 2, '.', '');
            $cant2         = number_format(($val2 > 0) ? 1 : 0, 2, '.', '');
            $descr         = (empty($codmat2) || $cant2 <= 0 ) ? "" : "$descrPart valoare copii suplimentare pana la $untilPart" . $conformContract;
            $descr2        = (isset($_POST['refresh']) || !isset($_POST['descr2'])) ? $descr : $_POST['descr2'];
            $from_counter2 = $oldCounterBlack;
            $to_counter2   = $newCounterBlack;
        } else {
            $val2  = number_format($blackCopyPrice * $rate * $divizorCopii, 2, '.', '');
            $cant2 = number_format((isset($_POST['cant2']) && $fixed2) ? $_POST['cant2'] : (empty($codmat2) || $val2 == 0 ? 0 : $differenceCounterBlack / $divizorCopii), 2, '.', '');
            if ($divizorCopii > 1) {
                $divizorExplanation2 = " (" . ($cant2 * $divizorCopii) . "=$cant2*$divizorCopii copii)";
            } else {
                $divizorExplanation2 = "";
            }

            $descr         = (empty($codmat2) || $cant2 <= 0 ) ? "" : "$descrPart de la $allowedCounterBlack la " . ($allowedCounterBlack + $cant2 * $divizorCopii) . " copii alb-negru$divizorExplanation2" . $conformContract;
            $descr2        = (isset($_POST['refresh']) || !isset($_POST['descr2'])) ? $descr : $_POST['descr2'];
            $from_counter2 = $allowedCounterBlack;
            $to_counter2   = $newCounterBlack;
        }

        $codmat3 = isset($_POST['codmat3']) ? $_POST['codmat3'] : $cod3;
        $val3    = number_format((empty($codmat3)) ? 0 : $colorCopyPrice * $rate * $divizorCopii, 2, '.', '');
        $cant3   = number_format((isset($_POST['cant3']) && $fixed3) ? $_POST['cant3'] : (empty($codmat3) || $val3 == 0 ? 0 : $differenceCounterColor / $divizorCopii), 2, '.', '');
        if ($divizorCopii > 1) {
            $divizorExplanation3 = " (" . ($cant3 * $divizorCopii) . "=$cant3*$divizorCopii copii)";
        } else {
            $divizorExplanation3 = "";
        }

        $descr         = (empty($codmat3) || $cant3 <= 0 ) ? "" : "$descrPart de la $allowedCounterColor la " . ($allowedCounterColor + $cant3 * $divizorCopii) . " copii color$divizorExplanation3" . $conformContract;
        $descr3        = (isset($_POST['refresh']) || !isset($_POST['descr3'])) ? $descr : $_POST['descr3'];
        $from_counter3 = $allowedCounterColor;
        $to_counter3   = $newCounterColor;

        $printerTotal = number_format($cant1 * $val1, 2, '.', '');
        if ($cant2 > 0) {
            $printerTotal .= number_format($cant2 * $val2, 2, '.', '');
        }

        if ($cant3 > 0) {
            $printerTotal .= number_format($cant3 * $val3, 2, '.', '');
        }

        $safeEnterpriseName   = preg_replace('/[^a-zA-z0-9-]/', '-', trim($enterprise->fields["name"]));
        $exportFileNameSuffix = IserviceToolBox::getInputVariable('export_file_name_suffix');

        if (IserviceToolBox::getInputVariable('restore')) {
            $backupPattern =
                "$backFilePath/" .
                IserviceToolBox::getInputVariable('backup_year') .
                "-" .
                IserviceToolBox::getInputVariable('backup_month') .
                "." . $enterprise->getID() .
                "." . IserviceToolBox::getInputVariable('backup_name') .
                ".[DS]*.*";
            foreach (glob($backupPattern) as $oldFilePath) {
                $fileNameParts = explode('.', pathinfo($oldFilePath, PATHINFO_BASENAME));
                if (count($fileNameParts) < 5) {
                    continue;
                }

                if (strtolower($fileNameParts[4]) === 'dat') {
                    $newFilePath = "{$exportFilePath}DAT/$safeEnterpriseName.$fileNameParts[2].$fileNameParts[1].$fileNameParts[4]";
                } else {
                    $newFilePath = "{$exportFilePath}$fileNameParts[3].$safeEnterpriseName.$fileNameParts[2].$fileNameParts[1].$fileNameParts[4]";
                }

                rename($oldFilePath, $newFilePath);
            }

            $exportFileNameSuffix = null;
        }

        if ($exportFileNameSuffix && IserviceToolBox::getInputVariable( 'delete')) {
            foreach (glob($exportFilePath . "S*.*.$exportFileNameSuffix.{$enterprise->getID()}.*") as $path) {
                unlink($path);
            }

            foreach (glob($exportFilePath . "DAT/*.$exportFileNameSuffix.{$enterprise->getID()}.dat") as $path) {
                unlink($path);
            }

            $exportFileNameSuffix = null;
        }

        if ($exportFileNameSuffix && IserviceToolBox::getInputVariable('import')) {
            foreach (glob($exportFilePath . "S*.*.$exportFileNameSuffix.{$enterprise->getID()}.*") as $oldFilePath) {
                $fileNameParts = explode('.', pathinfo($oldFilePath, PATHINFO_BASENAME));
                if (count($fileNameParts) < 5) {
                    continue;
                }

                $newFilePath = "$backFilePath/" . date("Y-m", filectime($oldFilePath)) . ".{$enterprise->getID()}.$exportFileNameSuffix.$fileNameParts[0].$fileNameParts[4]" ;
                rename($oldFilePath, $newFilePath);
            }

            foreach (glob($exportFilePath . "DAT/*.$exportFileNameSuffix.{$enterprise->getID()}.dat") as $oldFilePath) {
                $fileNameParts = explode('.', pathinfo($oldFilePath, PATHINFO_BASENAME));
                if (count($fileNameParts) < 4) {
                    continue;
                }

                $newFilePath = "$backFilePath/" . date("Y-m") . ".{$enterprise->getID()}.$exportFileNameSuffix.D.$fileNameParts[3]" ;
                rename($oldFilePath, $newFilePath);
            }

            $exportFileNameSuffix = null;

            $refreshTime = new DateTime(date('Y-m-d H:i:s', self::getNextImportTime() ?: time()));
            $refreshTime->add(new DateInterval('PT1M'));

            if (IserviceToolBox::getInputVariable('add_pending_mail')) {
                $pending_email = new PluginIservicePendingEmail();
                $pending_email->add(
                    [
                        '_add' => 'add',
                        'refresh_time' => $refreshTime->format('Y-m-d H:i'),
                        'printers_id' => $printer->getID(),
                        'mail_to' => $part_email_f1,
                        'subject' => $mail_subject,
                        'body' => $mail_body,
                    ]
                );
            }
        }

        $exportFileNameSuffixes = [];
        foreach (glob("{$exportFilePath}S.*.{$enterprise->getID()}.*") as $path) {
            $fileNameParts = explode('.', pathinfo($path, PATHINFO_FILENAME));
            if (count($fileNameParts) < 3) {
                continue;
            }

            $exportFileNameSuffixes[] = $fileNameParts[2];
        }

        $exportFileNameSuffix = $exportFileNameSuffix ?: end($exportFileNameSuffixes) ?: date('YmdHis');

        if (false === array_search($exportFileNameSuffix, $exportFileNameSuffixes)) {
            $exportFileNameSuffixes[] = $exportFileNameSuffix;
        } else {
            foreach (glob("{$exportFilePath}S.*.$exportFileNameSuffix.{$enterprise->getID()}.*") as $path) {
                $fileNameParts = explode('.', pathinfo($path, PATHINFO_FILENAME));
                if (count($fileNameParts) < 2) {
                    continue;
                }

                $safeEnterpriseName = $fileNameParts[1];
                break;
            }
        }

        $safeExportFileNameSuffix = preg_replace('/[^a-zA-z0-9-]/', '-', trim($exportFileNameSuffix));
        $exportFileNameBase       = implode('.', [$safeEnterpriseName, $safeExportFileNameSuffix, $enterprise->getID()]);
        $exportFileName           = $exportFilePath . "S.$exportFileNameBase.csv";
        $extendedExportFileName   = $exportFilePath . "SX.$exportFileNameBase.csv";
        if (!file_exists($exportFilePath . "DAT")) {
            mkdir($exportFilePath . "DAT");
        }

        $datFileName = $exportFilePath . "DAT/$exportFileNameBase.dat";

        $s039           = (isset($_POST['s039']) && $_POST['s039']) ? 1 : (file_exists($exportFileName) ? 0 : 1);
        $include_status = isset($_POST['include_status']) && $_POST['include_status'];
        $include_period = isset($_POST['include_period']) && $_POST['include_period'];

        $export_error = null;
        if (isset($_POST['add']) || isset($_POST['new'])) {
            if (!file_exists($exportFileName)) {
                $exportData = "DOC_TIP,NRCMD,DOC_DATA,PART_COD,CODMAT,CANT,DOC_VAL,DESCR\r\n";
            } else {
                $exportData = "";
            }

            if (!file_exists($extendedExportFileName)) {
                $extendedExportData = "DOC_TIP,NRCMD,DOC_DATA,PART_COD,CODMAT,CANT,DOC_VAL,DESCR,CENTRU_COST,CTR_DE_LA,CTR_PANA_LA,LOCATIE,APARAT,SERIE\r\n";
            } else {
                $extendedExportData = "";
            }

            if ($s039 || $include_status || $include_period) {
                $code        = empty($s039) && empty($include_status) ? "S039-G" : "S039-S";
                $descr_array = [];
                if (!empty($s039)) {
                    $descr_array[] = $contract->fields['num'] ?? '';
                }

                if (!empty($include_status)) {
                    $descr_array[] = $printerState->fields["name"];
                }

                if (!empty($include_period)) {
                    $descr_array[] = "perioada facturata: de la " . date('d.m.y', $invoice_date_field) . " pana la " . date('d.m.y', $effective_date);
                }

                $exportData .= "TFAC,$nrcmd," . date("Y.m.d", $doc_date) . ','
                        . $enterprise->customfields->fields['hmarfa_code_field'] . ','
                        . "$code,1,0,\"" . implode(', ', $descr_array) . "\"\r\n";
            }

            if ($cant1 > 0) {
                $data1               = "TFAC,$nrcmd," . date("Y.m.d", $doc_date) . ','
                        . $enterprise->customfields->fields['hmarfa_code_field'] . ','
                        . $codmat1 . ','
                        . number_format($cant1, 2, '.', '') . ','
                        . number_format($val1, 2, '.', '')
                        . ",\"$descr1\"";
                $exportData         .= "$data1\r\n";
                $extendedExportData .= "$data1,\"{$printer->customfields->fields['cost_center_field']}\",\"$from_counter1\",\"$to_counter1\",\"$printerLocationName\",\"{$printer->fields['name']}\",\"{$printer->fields["serial"]}\"\r\n";
            }

            if ($cant2 > 0) {
                $data2               = "TFAC,$nrcmd," . date("Y.m.d", $doc_date) . ','
                        . $enterprise->customfields->fields['hmarfa_code_field'] . ','
                        . $codmat2 . ','
                        . number_format($cant2, 2, '.', '') . ','
                        . number_format($val2, 2, '.', '')
                        . ",\"$descr2\"";
                $exportData         .= "$data2\r\n";
                $extendedExportData .= "$data2,\"{$printer->customfields->fields['cost_center_field']}\",$from_counter2,$to_counter2,\"$printerLocationName\",\"{$printer->fields['name']}\",\"{$printer->fields["serial"]}\"\r\n";
            }

            if ($cant3 > 0) {
                $data3               = "TFAC,$nrcmd," . date("Y.m.d", $doc_date) . ','
                        . $enterprise->customfields->fields['hmarfa_code_field'] . ','
                        . $codmat3 . ','
                        . number_format($cant3, 2, '.', '') . ','
                        . number_format($val3, 2, '.', '')
                        . ",\"$descr3\"";
                $exportData         .= "$data3\r\n";
                $extendedExportData .= "$data3,\"{$printer->customfields->fields['cost_center_field']}\",$from_counter3,$to_counter3,\"$printerLocationName\",\"{$printer->fields['name']}\",\"{$printer->fields["serial"]}\"\r\n";
            }

            file_put_contents($exportFileName, $exportData, FILE_APPEND);
            if (!is_writable($exportFileName)) {
                $export_error = print_r(error_get_last(), true);
            }

            file_put_contents($extendedExportFileName, $extendedExportData, FILE_APPEND);
            if (!is_writable($extendedExportFileName)) {
                $extended_export_error = print_r(error_get_last(), true);
            }

            $exportData = "$id," . date("Y-m-d", $doc_date) . "," . date("Y-m-d", $exp_date) . ",$newCounterBlack,$newCounterColor\r\n";
            file_put_contents($datFileName, $exportData, FILE_APPEND);
            if (!is_writable($datFileName)) {
                $export2_error = print_r(error_get_last(), true);
            }
        } elseif (isset($_POST['save'])) {
            $printer_customfields = new PluginFieldsPrinterprintercustomfield();
            if (!$printer_customfields->update(
                [
                    $printer->customfields->getIndexName() => $printer->customfields->getID(),
                    "invoice_date_field" => date('Y-m-d', $invoice_date_field),
                    "invoice_expiry_date_field" => date('Y-m-d', $invoice_expiry_date_field),
                    "invoiced_total_black_field" => $oldCounterBlack,
                    "invoiced_total_color_field" => $oldCounterColor,
                ]
            )
            ) {
                die(sprintf(__("Error updating %s custom fields"), "Printer"));
            }

            $supplier_customfields = new PluginFieldsSuppliersuppliercustomfield();
            if (!$supplier_customfields->update(
                [
                    $enterprise->customfields->getIndexName() => $enterprise->customfields->getID(),
                    'email_for_invoices_field' => $part_email_f1
                ]
            )
            ) {
                die(sprintf(__("Error updating %s custom fields"), "Partner"));
            }
        } elseif (isset($_POST['update'])) {
            if (file_exists($datFileName)) {
                $printer_customfields = new PluginFieldsPrinterprintercustomfield();
                foreach (explode("\r\n", file_get_contents($datFileName)) as $row) {
                    $data = explode(",", $row);
                    if (count($data) > 3 && PluginIserviceDB::populateByItemsId($printer_customfields, $data[0])) {
                        $printer_customfields->update(
                            [
                                $printer_customfields->getIndexName() => $printer_customfields->getID(),
                                "invoice_date_field" => $data[1],
                                "invoice_expiry_date_field" => $data[2],
                                "invoiced_total_black_field" => $data[3],
                                "invoiced_total_color_field" => $data[4]
                            ]
                        );
                    }
                }
            }
        }

        $otherCsvLineWarning = '';
        $unfinishedString    = 'nefinalizată';
        if (count($exportFileNameSuffixes) > 1) {
            $otherCsvLineWarning = (count($exportFileNameSuffixes) > 2 ? 'alte facturi' : 'altă factură') . ' de servicii';
            $unfinishedString    = count($exportFileNameSuffixes) > 2 ? 'nefinalizate' : 'nefinalizată';
        } elseif (!file_exists($exportFileName)) {
            $import_disabled_reason = 'Adăugați date in fișier întâi!';
        }

        $otherCsvs = glob("{$exportFilePath}F*.*.{$enterprise->getID()}.*");
        if (count($otherCsvs) > 0) {
            $unfinishedString     = (empty($otherCsvLineWarning) && count($otherCsvs) < 2) ? 'nefinalizată' : 'nefinalizate';
            $otherCsvLineWarning .= (empty($otherCsvLineWarning) ? '' : ' și ') . (count($otherCsvs) > 1 ? 'facturi' : 'factură') . ' de consumabile';
        }

        if (empty($otherCsvLineWarning)) {
            $otherCsvLineWarning = '&nbsp;';
        } else {
            $otherCsvLineWarning  = "<span style='color:red;font-weight:bold;'>ATENȚIE! Aveți $otherCsvLineWarning $unfinishedString!</span>";
            $otherCsvLineWarning .= " <input name='acknowledge_other_csvs' type='checkbox' " . ($acknowledge_other_csvs ? 'checked' : '') . " onclick='$(\"[name=refresh]\").click();'/> Continuă";
            if (!$acknowledge_other_csvs) {
                $import_disabled_reason = $import_disabled_reason ?: 'Bifați "continuă" de lângă atenționare';
                $add_disabled_reason    = $add_disabled_reason ?: 'Bifați "continuă" de lângă atenționare';
            }
        }

        echo "<div align='center'>\n";
        echo "<form id='hmarfa-export-form' name='hmarfa-export-form' class='hmarfa-export-form printer' method='post'>\n";
        echo "<table class='tab_cadre_fixe wide'>\n";
        echo "  <tr><td  class='tab_bg_1' style='padding:0;vertical-align:top;'>\n";
        echo "    <table width='100%'>\n";
        echo "    <tr><th align='center' >\n";
        echo "      <a href='" . GLPI_ROOT . "/front/printer.form.php?id=$id'>" . $printer->fields["original_name"] . "(" . $printer->fields["serial"] . ") - " . $printer->fields["otherserial"] . "</a>\n";
        echo "    </th></tr>\n";
        echo "    <tr><td align='center' >\n";
        echo "	    <table id='hmarfa_export_left'>\n";

        $prefix = "        ";

        $magic_link_label        = "Link magic partener:";
        $magic_link_button_name  = "Genereaza";
        $magic_link_button_class = "";
        if (!empty($enterprise->customfields->fields['magic_link_field'])) {
            $magic_link              = $enterprise->getMagicLink();
            $magic_link_label        = "<a href='$magic_link'>$magic_link_label</a>";
            $magic_link_button_name .= " nou";
            $magic_link_button_class = " new";
        }

        $magic_link_button = "<input type='submit' name='generate_magic_link' class='submit$magic_link_button_class' value='$magic_link_button_name'>";
        if (empty($enterprise->customfields->fields['uic_field'])) {
            $magic_link_button = "<span style='color: red'>Partenerul nu are CUI!</span>";
        }

        if ($enterprise->customfields->fields['uic_field'] != $enterprise->hMarfa_fields['cod1']) {
            $part_cui          = $enterprise->customfields->fields['uic_field'];
            $cod_cui           = $enterprise->hMarfa_fields['cod1'];
            $magic_link_button = "<span style='color: red'>CUI hMarfa si iService difera!<br>$part_cui != $cod_cui</span>";
        }

        echo self::generateInputFieldRow($prefix, $magic_link_button, $magic_link_label);
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "", $enterprise->fields["name"], true), "Nume <a href='" . GLPI_ROOT . "/front/supplier.form.php?id={$enterprise->getID()}'>partener</a>:");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "", $printerLocation->fields["name"] ?? '', true), "Locatie:", 'se va folosi pentru managementul cartuselor');
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "", $printer->customfields->fields["cost_center_field"], true), "Centru de cost:", 'se va folosi pentru calculul de subtotaluri la clientii cu mai multe centre de cost');
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "", $printer->customfields->fields["usage_address_field"], true), _t('Usage address') . ':', "adresa la care se afla aparatul");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "", $printerState->fields["name"], true), "Status:");
        echo "        <tr><td>Data ultima factura:</td><td><div class='dropdown_wrapper'>\n";
        Html::showDateField("invoice_date_field", ['value' => date("Y-m-d", $invoice_date_field)]);
        echo "        </div></td></tr>\n";
        echo "        <tr><td>Data exp. factura:</td><td><div class='dropdown_wrapper'>\n";
        Html::showDateField("invoice_expiry_date_field", ['value' => date("Y-m-d", $invoice_expiry_date_field)]);
        echo "        </div></td></tr>\n";
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "old_counter_black", $oldCounterBlack, true), "Contor black ultima factura:");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "new_counter_black", $newCounterBlack, true), "Contor black ulitma interventie:", "Pentru a modifica, schimbati tichetul " . $printer->lastTicket()->getID());
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "old_counter_color", $oldCounterColor, true), "Contor color ultima factura:");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "new_counter_color", $newCounterColor, true), "Contor color ultima interventie:", "Pentru a modifica, schimbati tichetul " . $printer->lastTicket()->getID());
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "effective_date_field", date("Y-m-d", $effective_date), true), "Data ultima interventie:", "Pentru a modifica, schimbati tichetul " . $printer->lastTicket()->getID());
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "", empty($contract->fields['num']) ? '' : $contract->fields['num'], true), "Numar <a href='" . GLPI_ROOT . "/front/contract.form.php?id={$contract->getID()}'>contract</a>:");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "", $contractType, true), "Tip contract:");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "", $divizorCopii, true), "Divizor copii:");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "", $includedCopiesBlack, true), "Numar copii black incluse:");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "", $includedCopiesColor, true), "Numar copii color incluse:");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "", $includedCopiesValue, true), "Valoare copii incluse:");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "", $contract_value, true, "", false, " $currency"), "Valoare contract:");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "", $blackCopyPrice, true, "", false, " $currency"), "Tarif copie black:");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "", $colorCopyPrice, true, "", false, " $currency"), "Tarif copie color:");
        echo "        <tr>";
        echo "          <td><a class='vsubmit' href='mailto:$part_email_f1?subject=$mail_subject&body=$mail_body'>Trimite email către:</a></td>";
        echo "          <td><textarea name='email_for_invoices_field' style='width:150px;' rows='2'>$part_email_f1</textarea></td><td>\n";
        echo "        <tr>\n";
        echo "      </table>\n";
        echo "    </td></tr>\n";
        echo "    </table>\n";
        echo "  </td><td class='tab_bg_1' style='padding:0;vertical-align:top;'>\n";
        echo "    <table width='100%'>\n";
        echo "    <tr><th align='center' >Export catre hMarfa</th></tr>\n";
        echo "    <tr><td align='center' >\n";
        echo "	    <table>\n";
        echo self::generateInputFieldRow('        ', self::generateInputField("", "nrcmd", $nrcmd), "Nr comanda: ");
        echo "        <tr><td>Data facturare:</td><td><div class='dropdown_wrapper'>\n";
        Html::showDateField("doc_date", ['value' => date("Y-m-d", $doc_date)]);
        echo "        </div></td></tr>\n";
        echo "        <tr><td>Data exp. facturare:</td><td>\n";
        echo "          <div class='dropdown_wrapper'>\n";
        Html::showDateField("exp_date", ['value' => date("Y-m-d", $exp_date)]);
        echo "          </div>\n";
        echo "          <input type='button' name='refresh' class='submit' value='Azi' onClick='document.getElementsByName(\"exp_date\")[0].value=\"" . date("Y-m-d") . "\"; document.getElementsByName(\"_exp_date\")[0].value=\"" . date("Y-m-d") . "\";'>\n";
        echo "          <input type='button' name='refresh' class='submit' value='Sfarsitul lunii' onClick='document.getElementsByName(\"exp_date\")[0].value=\"" . date("Y-m-t") . "\"; document.getElementsByName(\"_exp_date\")[0].value=\"" . date("Y-m-t") . "\";'>\n";
        echo "        </td></tr>\n";

        $rate_extra = "&nbsp;&nbsp;<input type='submit' name='refresh' class='submit' value='Contract: $contract_rate' onClick='document.getElementById(\"rate\").value=\"$contract_rate\";'>";
        if ($currency == "EUR") {
            if ($currency_error === null) {
                $rate_extra .= "&nbsp;&nbsp;<input type='submit' name='refresh' class='submit' value='BNR: $official_currency->EuroCaNumar' onClick='document.getElementById(\"rate\").value=\"$official_currency->EuroCaNumar\";'>";
            } else {
                $rate_extra .= "&nbsp;&nbsp;<input type='button' name='refresh' class='submit' value='BNR: $currency_error'>";
            }
        }

        echo self::generateInputFieldRow($prefix, self::generateInputField("", "rate", $rate) . $rate_extra, "Curs valutar:");
        $checkboxes  = self::generateCheckboxField("&nbsp;&nbsp;", "location", !empty($location), " Adresa expl.");
        $checkboxes .= self::generateCheckboxField("&nbsp;&nbsp;", "cost_center", !empty($cost_center), " Centru de cost");
        $checkboxes .= self::generateCheckboxField("&nbsp;&nbsp;", "s039", $s039, " S039");
        $checkboxes .= self::generateCheckboxField("&nbsp;&nbsp;", "include_status", $include_status, " Status", "' onclick='if($(this).is(\":checked\")){\$(\"#s039\").prop(\"checked\", true);}");
        $checkboxes .= self::generateCheckboxField("&nbsp;&nbsp;", "include_period", $include_period, " Perioada", "' onclick='if($(this).is(\":checked\")){\$(\"#s039\").prop(\"checked\", true);}");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "part_cod", $enterprise->customfields->fields['hmarfa_code_field'], true, "", false, $checkboxes), "Cod partener hMarfa:");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "codmat1", $codmat1, false, ""), "Codul serviciului:");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "val1", $val1, true, "width:72px;", false, " pe luna" . self::generateQuantityAndTotalFields(1, $cant1, $fixed1, number_format($val1 * $cant1, 2, '.', ''))), "Pret unitar in RON:");
        echo self::generateInputFieldRow($prefix, self::generateTextArea("", "descr1", $descr1, false, "width:470px;", true), "Descriere:");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "codmat2", $codmat2), "Codul serviciului:");
        if ($divizorCopii > 1) {
            $divizorExplanation = "<span id='divizorexp'>pe $divizorCopii copii</span>";
        } else {
            $divizorExplanation = "<span id='divizorexp'>pe copie</span>";
        }

        echo self::generateInputFieldRow($prefix, self::generateInputField("", "val2", $val2, false, "", false, " $divizorExplanation" . self::generateQuantityAndTotalFields(2, $cant2, $fixed2, number_format($val2 * $cant2, 2, '.', ''))), "Pret unitar in RON:");
        echo self::generateInputFieldRow($prefix, self::generateTextArea("", "descr2", $descr2, false, "width:470px;", true), "Descriere:");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "codmat3", $codmat3), "Codul serviciului:");
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "val3", $val3, false, "", false, " $divizorExplanation" . self::generateQuantityAndTotalFields(3, $cant3, $fixed3, number_format($val3 * $cant3, 2, '.', ''))), "Pret unitar in RON:");
        echo self::generateInputFieldRow($prefix, self::generateTextArea("", "descr3", $descr3, false, "width:470px;", true), "Descriere:");
        echo "		  <tr><td>&nbsp;</td></tr>\n";
        echo self::generateInputFieldRow($prefix, self::generateInputField("", "exportfilepath", $exportFilePath, false, "width:272px;", false, self::generateInputField("&nbsp;&nbsp;&nbsp;Subtotal aparat: ", "devicetotal", $printerTotal, true, "width: 65px;", false, " RON")), "Cale fisier export:");
        echo "		  <tr><td>&nbsp;</td></tr>\n";
        echo "        <tr>\n";
        echo "          <td align='right'>\n";
        echo "            <input type='submit' class='submit' name='refresh' value='Actualizare'/>&nbsp;&nbsp;&nbsp;\n";
        echo "          </td><td>\n";
        echo "            <select onchange='$(\"[name=export_file_name_suffix]\").val($(this).val());$(\"[name=refresh]\").click();' style='width:480px;'>\n";
        foreach ($exportFileNameSuffixes as $suffix) {
            echo "           <option name='$suffix' " . ($suffix === $exportFileNameSuffix ? 'selected' : '') . ">$suffix</option>\n";
        }

        echo "            </select>";
        echo "            <i class='pointer fa fa-trash' onclick='$(\"[name=delete]\").click();' style='color:red;'></i><input name='delete' type='submit' style='display: none;'/>";
        echo "            <input class='submit" . (empty($import_disabled_reason) ? '' : ' disabled') . "' name='import' style='color:red;' title='" . ($import_disabled_reason ?: 'ATENȚIE! Apăsând butonul ștergeți fișierele csv!') . "' type='submit' value='Importat în hMarfa' onclick='if ($(this).hasClass(\"disabled\")) { return false; }'/>";
        echo "          </td>\n";
        echo "        </tr><tr>\n";
        echo "          <td align='right'><input class='submit" . (empty($add_disabled_reason) ? '' : ' disabled') . "' name='add' title='$add_disabled_reason' type='submit' value='Adaugă in document' onclick='if ($(this).hasClass(\"disabled\")) { return false; }'/>&nbsp;&nbsp;&nbsp;</td>\n";
        echo "          <td style='position: relative'>\n";
        echo "            <input type='text' name='export_file_name_suffix' value='$exportFileNameSuffix' style='width:470px;' onchange='checkExportFileNameSuffix();'/>\n";
        echo "            &nbsp;&nbsp;&nbsp;&nbsp;\n";
        echo "            <span style='float: right'><input type='checkbox' name='add_pending_mail' value='1'> si trimite email</span><br>\n";
        echo "            <span style='position: absolute; right: 0; margin-top: 5px;'>" . self::getNextImportText() . "</span>";
        echo "          </td>\n";
        echo "        </tr>\n";
        echo "      </table>\n";
        echo "    </td></tr>\n";
        echo "    </table>\n";
        echo "  </td></tr>";

        echo "  <tr><td colspan=2>&nbsp;</td>\n";
        echo "  <tr>\n";
        echo "    <td align='center'>\n";
        echo "      <input type='submit' class='submit' name='update' value='Update facturare'/>&nbsp;&nbsp;&nbsp;\n";
        echo "      <input type='submit' class='submit' name='save' value='Salveaza'/>&nbsp;&nbsp;&nbsp;\n";
        echo "      <input type='submit' class='submit' name='hard_refresh' value='Refresh'/>\n";
        echo "    </td>\n";
        echo "    <td align='center'>\n";
        echo "      <a class='vsubmit' href='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id={$printer->lastTicket()->getID()}'>Înapoi la ultimul tichet</a>\n";
        echo "    </td>\n";
        echo "  </tr>";
        echo "  <tr><td colspan=2>&nbsp;</td>\n";

        echo "  <tr><td colspan=2 style='padding:0px;'>\n";
        echo "      <table class='tab_bg_1' width='100%'>\n";
        echo "      <tr>\n";
        echo "        <th colspan=2>\n";
        echo "          Continutul fisierelor din $exportFilePath";
        echo "          <span style='display:inline-block;width:4em;'></span>";
        $checked          = (IserviceToolBox::getInputVariable('extended-csv') == '') ? "checked" : "";
        $hide_dat         = IserviceToolBox::getInputVariable('hide-dat');
        $show_dat_checked = $hide_dat ? "checked" : "";
        echo "          <input id='extended-chb' type='checkbox' name='extended-csv' onclick='$(\".extended-csv\").toggle(this.checked); $(\".base-csv\").toggle(!this.checked);' $checked/> CSV extins\n";
        echo "          <input id='show-dat-chb' type='checkbox' name='show-dat-csv' onclick='$(\"#hide-dat\").val(this.checked ? 0 : 1); $(\".base-dat\").toggle(this.checked); $(this).closest(\"th\").attr(\"colspan\", 1 + (this.checked ? 1 : 0));' $show_dat_checked /> Arată dat\n";
        echo "          <input id='hide-dat' name='hide-dat' type='hidden' value ='" . ($hide_dat ? 1 : 0) . "' />";
        echo "          <span style='display:inline-block;width:4em;'></span>";
        echo "          $otherCsvLineWarning";
        echo "        </th>\n";
        echo "      </tr>\n";
        echo "      <tr>\n";
        $generate_ssx_ajaxcall_success_function = "function(message) {if (message!=\"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {alert(\"Fișierul S%2\$s generat cu succes\");}}";
        $url_encoded_exportFilePath             = urlencode($exportFilePath);
        $generate_ajaxcall                      = "ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/generateSsx.php?path=%1\$s&file_name=%2\$s\", \"\", $generate_ssx_ajaxcall_success_function);";
        $generate_ss_ajaxcall                   = sprintf($generate_ajaxcall, $url_encoded_exportFilePath, "S.$exportFileNameBase.csv");
        $generate_ssx_ajaxcall                  = sprintf($generate_ajaxcall, $url_encoded_exportFilePath, "SX.$exportFileNameBase.csv");
        echo "        <th class='base-csv'><a class='vsubmit' href='javascript:none;' onclick='$generate_ss_ajaxcall'>Generează fișier SS</a>   S.$exportFileNameBase.csv</th>\n";
        echo "        <th class='extended-csv'><a class='vsubmit' href='javascript:none;' onclick='$generate_ssx_ajaxcall'>Generează fișier SSX</a> SX.$exportFileNameBase.csv</th>\n";
        echo "        <th class='base-dat' style='widht:3em;'>DAT/$exportFileNameBase.dat</th>\n";
        echo "      </tr>\n";
        echo "      <tr style='text-align:center'>\n";
        echo "        <td class='base-csv'><textarea id='csv_text' rows=12 style='width:98%'>" . ($export_error !== null ? $export_error : (file_exists($exportFileName) ? file_get_contents($exportFileName) : "")) . "</textarea></td>";
        echo "        <td class='extended-csv'><textarea id='csv_text' rows=12 style='width:98%'>" . (!empty($extended_export_error) ? $extended_export_error : (file_exists($extendedExportFileName) ? file_get_contents($extendedExportFileName) : "")) . "</textarea></td>";
        echo "        <td class='base-dat'><textarea id='dat_text' rows=12 style='width:98%;'>" . (!empty($export2_error) ? $export2_error : (file_exists($datFileName) ? file_get_contents($datFileName) : "")) . "</textarea></td>";
        echo "      </tr>\n";
        echo "      <tr>\n";
        echo "        <td align='center'>\n";
        echo "          <select id='backup_year' name='backup_year' style='width: 55px;' onchange='refreshHistoryData();'></select> ";
        echo "          <select id='backup_month' name='backup_month' style='width: 40px;' onchange='refreshHistoryData(true);'></select> ";
        echo "          <select id='backup_name' name='backup_name' style='width: 377px;'></select> ";
        echo "          <input class='submit' name='restore' type='submit' style='margin:1em 0;' value='Restabilire'>";
        echo "        </td>\n";
        echo "        <td></td>\n";
        echo "      </tr>\n";
        echo "    </table>\n";
        echo "  </td></tr>\n";
        echo "</table>";
        Html::closeForm();
        echo "</div>\n";
        echo "<script>\n";
        echo "  $(\"#extended-chb\").click();$(\"#show-dat-chb\").click();";

        echo "  function adjustValueInput(inputId, width, spanId) {\n";
        echo "    document.getElementById(inputId).style.width = (width - document.getElementById(spanId).offsetWidth) + 'px';\n";
        echo "  }\n";
        echo "  adjustValueInput('val2', 120, 'divizorexp');\n";
        echo "  adjustValueInput('val3', 120, 'divizorexp');\n";

        $backupData = [];
        echo "  var years = [];";
        echo "  var months = [];";
        echo "  var backup_data = [];\n";
        foreach (glob("$backFilePath/*.{$enterprise->getID()}.*.S.csv") as $path) {
            $fileNameParts = explode('.', pathinfo($path, PATHINFO_FILENAME));
            if (count($fileNameParts) < 4) {
                continue;
            }

            $dateParts = explode('-', $fileNameParts[0]);
            if (count($dateParts) < 2) {
                continue;
            }

            $backupData[$dateParts[0]][$dateParts[1]][] = $fileNameParts[2];
        }

        foreach (array_keys($backupData) as $year) {
            echo "  years.push('$year');\n";
            echo "  months.push([]);\n";
            echo "  backup_data.push([]);\n";
            echo "  $('#backup_year').append(new Option($year, $year, false, false));\n";
            foreach (array_keys($backupData[$year]) as $month) {
                echo "  months[years.indexOf('$year')].push('$month');\n";
                echo "  backup_data[years.indexOf('$year')].push([]);\n";
                foreach ($backupData[$year][$month] as $name) {
                    echo "  backup_data[years.indexOf('$year')][months[years.indexOf('$year')].indexOf('$month')].push('$name');\n";
                }
            }
        }

        echo "  $('#backup_year option:last').attr('selected', true);\n";
        echo "  function refreshHistoryData(ignoreMonth) {
                    $('#backup_name option').remove();
                    if (months.length > 0 && (ignoreMonth === undefined || !ignoreMonth)) {
                        $('#backup_month option').remove();
                        months[years.indexOf($('#backup_year').val())].forEach(function(item){
                            $('#backup_month').append(new Option(item, item));
                        });
                        $('#backup_month option:last').attr('selected', true);
                    }
                    if (months.length < 1) {
                        return;
                    }
                    backup_data[years.indexOf($('#backup_year').val())][months[years.indexOf($('#backup_year').val())].indexOf($('#backup_month').val())].forEach(function(item){
                        $('#backup_name').append(new Option(item, item));
                    });
                    $('#backup_name option:last').attr('selected', true);
                }
                
                function checkExportFileNameSuffix() {
                    let year = '" . date('Y') . "'
                    let month = '" . date('m') . "'
                    if (years.indexOf(year) < 0 ||  months[years.indexOf(year)].indexOf(month) < 0 || backup_data[years.indexOf(year)][months[years.indexOf(year)].indexOf(month)].indexOf($('[name=export_file_name_suffix]').val()) < 0) {
                        $('[name=add]').removeClass('disabled');
                        return;
                    }
                    alert('Această nume a fost deja folosită în această lună!');
                    $('[name=add]').addClass('disabled');
                }
                ";
        echo "  jQuery(document).ready(function() {refreshHistoryData();});";
        echo "</script>\n";
    }

    public static function showTicketExportForm($id)
    {
        global $DB, $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
        $form = new PluginIserviceHtml();

        $order = IserviceToolBox::getInputVariable('order');
        if (!empty($order)) {
            echo "<form id='redirect-form' action='$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php' method='post'>";
            echo "<input type='hidden' name='id' value='$id'/>";
            echo "<input type='hidden' name='order' value='Order'/>";
            echo "<input type='hidden' name='_add_fail_message' value='Eroare la crearea comenzii interne'/>";
            echo "<input type='hidden' name='_add_success_message' value='Comanda internă creată cu succes'/>";
            echo "<input type='hidden' name='_force_back' value='1'/>";
            $form->closeForm();
            echo "<script>$('#redirect-form').submit();</script>";
            die;
        }

        if (IserviceToolBox::getInputVariable('to_ticket', null)) {
            Html::redirect("$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?id=$id");
        }

        $add                    = IserviceToolBox::getInputVariable('add');
        $save                   = IserviceToolBox::getInputVariable('save');
        $wait                   = IserviceToolBox::getInputVariable('wait');
        $delete                 = IserviceToolBox::getInputVariable('delete');
        $import                 = IserviceToolBox::getInputVariable('import');
        $finish                 = IserviceToolBox::getInputVariable('finish');
        $restore                = IserviceToolBox::getInputVariable('restore');
        $acknowledge_other_csvs = IserviceToolBox::getInputVariable('acknowledge_other_csvs');
        $finished               = null;
        if ($wait) {
            $finished = 0;
        }

        if ($import) {
            $finish = true;
        }

        if ($finish) {
            $wait     = true;
            $finished = 1;
        }

        if ($add || $wait) {
            $save = true;
        }

        $acknowledge_disabled_reason = $import_disabled_reason = $add_disabled_reason = $wait_disabled_reason = $export_disabled_reason = null;

        $export_file_path = IserviceToolBox::getInputVariable('export_file_path', PluginIserviceConfig::getConfigValue('hmarfa.export.default_path') . '/');
        $back_file_path   = $export_file_path . "BAK";
        if (!file_exists($back_file_path)) {
            mkdir($back_file_path, 0775, true);
        }

        $ticket = new PluginIserviceTicket();
        $ticket->check($id, READ); // This includes a getFromDB().

        $export_types = [
            PluginIserviceTicket::EXPORT_TYPE_INVOICE_ID => [
                'doc_tip' => 'TFAC',
                'file_prefix' => 'F',
            ],
            PluginIserviceTicket::EXPORT_TYPE_NOTICE_ID => [
                'doc_tip' => 'TAIM',
                'file_prefix' => 'A',
            ],
        ];
        if (!in_array($ticket->customfields->fields['plugin_fields_ticketexporttypedropdowns_id'], array_keys($export_types))) {
            Html::displayErrorAndDie(_t('Ticket has an invalid export type'));
        }

        $consumable_ticket  = new PluginIserviceConsumable_Ticket();
        $ticket_consumables = $consumable_ticket->getForTicket($id);
        if (empty($ticket_consumables)) {
            Html::displayErrorAndDie(_t('Ticket has no consumables'));
        }

        $partner = $ticket->getFirstAssignedPartner();
        if ($partner->isNewItem()) {
            Html::displayErrorAndDie(_t('Ticket has no partner'));
        }

        $printer = $ticket->getFirstPrinter();

        include_once __DIR__ . '/TValuta.php';
        try {
            $currency_error    = null;
            $official_currency = new TValuta();
        } catch (Exception $ex) {
            $currency_error = $ex->getMessage();
        }

        $currency_rate = floatval(IserviceToolBox::getInputVariable('currency_rate', $currency_error == null ? $official_currency->EuroCaNumar : $currency_error));

        $ticket->fields['_users_id_assign'] = $ticket->getFirstAssignedUser()->getID();

        $ticket_consumable_prices      = [];
        $ticket_consumable_prices_data = explode('###', $ticket->customfields->fields['consumable_prices_field']);
        foreach ($ticket_consumable_prices_data as $ticket_consumable_price_data) {
            $part_data = explode(':', $ticket_consumable_price_data, 2);
            if (count($part_data) > 1) {
                $ticket_consumable_prices[$part_data[0]] = $part_data[1];
            }
        }

        $ticket_consumable_descriptions      = [];
        $ticket_consumable_descriptions_data = explode('###', $ticket->customfields->fields['consumable_descriptions_field']);
        foreach ($ticket_consumable_descriptions_data as $ticket_consumable_description_data) {
            $part_data = explode(':', $ticket_consumable_description_data, 2);
            if (count($part_data) > 1) {
                $ticket_consumable_descriptions[$part_data[0]] = $part_data[1];
            }
        }

        /*
        * Right side *
        */
        $right_side_header = new PluginIserviceHtml_table_row(
            '', [
                new PluginIserviceHtml_table_cell(_t('Partner information'), '', '', 7, 1, 'th')
            ]
        );

        // Partner email.
        $ticket_partner_email     = IserviceToolBox::getInputVariable('ticket_partner_email', $partner->customfields->fields['email_for_invoices_field']);
        $ticket_partner_email_row = new PluginIserviceHtml_table_row(
            '', [
                new PluginIserviceHtml_table_cell('Email de trimis facturi: ', '', 'width: 20%'),
                new PluginIserviceHtml_table_cell($form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'ticket_partner_email', $ticket_partner_email, false, ['style' => 'width:95%;']), '', 'width: 80%'),
            ]
        );
        $partner_email_table      = new PluginIserviceHtml_table('tab_cadre_fixe', null, $ticket_partner_email_row);

        // Partner info.
        $ticket_partner_sales_query = "
            SELECT
                    fr.nrfac AS Nr_Fact
                , fa.datafac AS Data_Fact
                , fr.codmat As Cod_Articol
                , n.denum AS Denumire_Articol
                , fr.cant AS Cantitate
                , ROUND(fr.puliv,2) AS Pret_Livrare
                , ROUND((fr.puliv / NULLIF(fr.puini, 0)), 2) AS Procent
            FROM hmarfa_facrind fr
            LEFT JOIN hmarfa_facturi fa ON fa.nrfac = fr.nrfac
            LEFT JOIN hmarfa_firme fi ON fi.cod = fa.codbenef
            LEFT JOIN hmarfa_nommarfa n ON fr.codmat = n.cod
            WHERE NOT fr.tip IN ('AIMFS', 'TAIM')
                AND NOT fr.codmat LIKE 'S%'
                AND fa.codbenef = '{$partner->customfields->fields['hmarfa_code_field']}'
            ORDER BY fa.datafac DESC, fr.nrfac DESC
            LIMIT 8
            ";

        $total_gain                   = 0;
        $ticket_partner_sales_rows    = [];
        $ticket_partner_sales_columns = [];
        if (($ticket_partner_sales_result = $DB->query($ticket_partner_sales_query)) !== false) {
            while ($data = $DB->fetchAssoc($ticket_partner_sales_result)) {
                if ($ticket_partner_sales_columns === []) {
                    $ticket_partner_sales_columns = array_keys($data);
                }

                $ticket_partner_sales_rows[] = new PluginIserviceHtml_table_row('', $data);
                $total_gain                 += $data['Procent'];
            }
        }

        $default_sales_average_percent     = count($ticket_partner_sales_rows) == 0 ? 1.3 : number_format($total_gain / count($ticket_partner_sales_rows), 2, '.', '');
        $sales_average_percent             = IserviceToolBox::getInputVariable('sales_average_percent', $default_sales_average_percent);
        $sales_average                     = _t('Average percent') . ": " . $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'sales_average_percent', $sales_average_percent, false, ['style' => 'width:4em;']);
        $ticket_partner_sales_average_cell = new PluginIserviceHtml_table_cell($sales_average, '', '', count($ticket_partner_sales_columns));

        $ticket_partner_sales_rows[] = new PluginIserviceHtml_table_row('tall', $ticket_partner_sales_average_cell, 'padding:1em;');

        $partner_info_header = [
            0 => new PluginIserviceHtml_table_row('tall', new PluginIserviceHtml_table_cell(_t('Last sales to the partner'), '', 'font-weight:bold;', count($ticket_partner_sales_columns))),
            1 => new PluginIserviceHtml_table_row('short'),
        ];
        $partner_info_header[1]->populateCells($ticket_partner_sales_columns, '', '', 'th');

        $partner_info_table = new PluginIserviceHtml_table('tab_cadre_fixe wide', $partner_info_header, $ticket_partner_sales_rows);

        $doc_date                = time();
        $safe_partner_name       = preg_replace('/[^a-zA-z0-9-]/', '-', $partner->fields["name"]);
        $export_file_name_prefix = $export_types[$ticket->customfields->fields['plugin_fields_ticketexporttypedropdowns_id']]['file_prefix'];
        $export_file_name_suffix = IserviceToolBox::getInputVariable('export_file_name_suffix');

        if ($restore) {
            $backup_pattern =
                "$back_file_path/" .
                IserviceToolBox::getInputVariable('backup_year') .
                "-" .
                IserviceToolBox::getInputVariable('backup_month') .
                "." . $partner->getID() .
                "." . IserviceToolBox::getInputVariable('backup_name') .
                ".$export_file_name_prefix.*";
            foreach (glob($backup_pattern) as $old_file_path) {
                $file_name_parts = explode('.', pathinfo($old_file_path, PATHINFO_BASENAME));
                if (count($file_name_parts) < 5) {
                    continue;
                }

                $new_file_path = "{$export_file_path}$file_name_parts[3].$safe_partner_name.$file_name_parts[2].$file_name_parts[1].$file_name_parts[4]";
                rename($old_file_path, $new_file_path);
            }

            $export_file_name_suffix = null;
        }

        if ($export_file_name_suffix && $delete) {
            foreach (glob("$export_file_path$export_file_name_prefix.*.$export_file_name_suffix.{$partner->getID()}.*") as $path) {
                unlink($path);
            }

            $export_file_name_suffix = null;
        }

        if ($export_file_name_suffix && $import) {
            foreach (glob("$export_file_path$export_file_name_prefix.*.$export_file_name_suffix.{$partner->getID()}.*") as $old_file_path) {
                $file_name_parts = explode('.', pathinfo($old_file_path, PATHINFO_BASENAME));
                if (count($file_name_parts) < 5) {
                    continue;
                }

                $new_file_path = "$back_file_path/" . date("Y-m", filectime($old_file_path)) . ".{$partner->getID()}.$export_file_name_suffix.$file_name_parts[0].$file_name_parts[4]" ;
                rename($old_file_path, $new_file_path);
            }

            $export_file_name_suffix = null;
        }

        $export_file_name_suffixes = [];
        foreach (glob("{$export_file_path}$export_file_name_prefix.*.{$partner->getID()}.*") as $path) {
            $file_name_parts = explode('.', pathinfo($path, PATHINFO_FILENAME));
            if (count($file_name_parts) < 3) {
                continue;
            }

            $export_file_name_suffixes[] = $file_name_parts[2];
        }

        $export_file_name_suffix = $export_file_name_suffix ?: end($export_file_name_suffixes) ?: date('YmdHis');

        if (false === array_search($export_file_name_suffix, $export_file_name_suffixes)) {
            $export_file_name_suffixes[] = $export_file_name_suffix;
            $export_data                 = "DOC_TIP,NRCMD,DOC_DATA,PART_COD,CODMAT,CANT,DOC_VAL,DESCR\r\n";
        } else {
            foreach (glob("{$export_file_path}$export_file_name_prefix.*.$export_file_name_suffix.{$partner->getID()}.*") as $path) {
                $file_name_parts = explode('.', pathinfo($path, PATHINFO_FILENAME));
                if (count($file_name_parts) < 2) {
                    continue;
                }

                $safe_partner_name = $file_name_parts[1];
                break;
            }

            $export_data = '';
        }

        $safe_export_file_name_suffix = preg_replace('/[^a-zA-z0-9-]/', '-', trim($export_file_name_suffix));
        $export_file_name_base        = "$export_file_name_prefix.$safe_partner_name";
        $export_file_name             = "$export_file_name_base.$safe_export_file_name_suffix.{$partner->getID()}.csv";

        // S039-M.
        if ($ticket->isExportTypeNotice() && !empty($printer)) {
            $contract_item  = new Contract_Item();
            $contract_items = $contract_item->find(['itemtype' => 'Printer', 'items_id' => $printer->getId()]);
            $contract       = new PluginIserviceContract();
            if (count($contract_items) > 0) {
                foreach ($contract_items as $item) {
                    if ($contract->getFromDB($item['contracts_id'])) {
                        break;
                    }
                }
            }

            if (!$contract->isNewItem()) {
                $export_data .= $export_types[$ticket->customfields->fields['plugin_fields_ticketexporttypedropdowns_id']]['doc_tip'] . ',';
                $export_data .= $ticket->getID() . '-' . Dropdown::getDropdownName('glpi_users', $ticket->fields['_users_id_assign']) . ',';
                $export_data .= date("Y.m.d", $doc_date) . ',';
                $export_data .= $partner->customfields->fields['hmarfa_code_field'] . ',';
                $export_data .= 'S039-M,1,0,"';
                $export_data .= $contract->fields['num'] . "\"\r\n";
            }
        }

        // Consumables on ticket.
        $cartridge           = new PluginIserviceCartridge();
        $total_amount        = 0;
        $used_cartridges     = [];
        $consumables_columns = null;
        foreach ($ticket_consumables as $ticket_consumable) {
            // Preparing consumable data.
            $ticket_consumable['Cod_Articol'] = $ticket_consumable['plugin_iservice_consumables_id'];

            $consumable = new PluginIserviceConsumable();
            $consumable->getFromDB($ticket_consumable['Cod_Articol']);
            $consumable_description                                            = IserviceToolBox::getInputVariable("consumable_description_$ticket_consumable[Cod_Articol]", empty($ticket_consumable_descriptions[$ticket_consumable['Cod_Articol']]) ? ($ticket->isExportTypeNotice() ? 'Livrat cu tichet ' . $ticket->fields['id'] : '') : $ticket_consumable_descriptions[$ticket_consumable['Cod_Articol']]);
            $ticket_consumable_descriptions[$ticket_consumable['Cod_Articol']] = $consumable_description;
            $ticket_consumable['Descriere']                                    = $consumable->fields['denumire'] . "<br>" . $form->generateField(PluginIserviceHtml::FIELDTYPE_MEMO, "consumable_description_$ticket_consumable[Cod_Articol]", $consumable_description, false, ['style' => 'height:2.5em;']);
            $ticket_consumable['Cant']                                         = number_format($ticket_consumable['amount'], 2, '.', '');
            $consumable_history                                                = null;
            $gain                   = null;
            $average_delivery_price = null;
            // History.
            $consumable->getHistoryTable($partner->customfields->fields['hmarfa_code_field'], $consumable_history, $gain, $average_delivery_price);
            $ticket_consumable['Istoric_Vanzari'] = $consumable_history;
            // Gain.
            $consumable_gain                  = IserviceToolBox::getInputVariable("consumable_gain_$ticket_consumable[Cod_Articol]", empty($gain) ? $sales_average_percent : $gain);
            $ticket_consumable[__('Average')] = $consumable_gain;
            // Stock price.
            $ticket_consumable['Pret_Stoc'] = number_format($consumable->fields['Pret'], 2, '.', '');
            // Recommended price.
            $processed_pret_stoc           = intval(str_replace([',', '.'], '', $ticket_consumable['Pret_Stoc']));
            $ticket_consumable['Pret_Rec'] = empty($processed_pret_stoc) ? $average_delivery_price : number_format($ticket_consumable['Pret_Stoc'] * $consumable_gain, 2, '.', '');
            // Euro price.
            if ($ticket_consumable['euro_price']) {
                $ticket_consumable['Pret_Euro'] = number_format($ticket_consumable['price'], 2, '.', '');
            } else {
                $ticket_consumable['Pret_Euro'] = 0;
            }

            // Agreed price.
            if ($ticket_consumable['euro_price']) {
                $agreed_price                  = number_format($ticket_consumable['price'] * $currency_rate, 2, '.', '');
                $ticket_consumable['Pret_Agr'] = "<span style='font-weight:bold' title='$ticket_consumable[price] * $currency_rate'>$agreed_price</span>";
            } else {
                $ticket_consumable['Pret_Agr'] = $agreed_price = number_format($ticket_consumable['price'], 2, '.', '');
            }

            // Final price.
            $final_price = intval($agreed_price) == 0 ? $ticket_consumable['Pret_Rec'] : $agreed_price;
            $final_price = isset($ticket_consumable_prices[$ticket_consumable['Cod_Articol']]) && $ticket_consumable_prices[$ticket_consumable['Cod_Articol']] > 0 ? $ticket_consumable_prices[$ticket_consumable['Cod_Articol']] : $final_price;

            $final_final_price = number_format(IserviceToolBox::getInputVariable("final_price_$ticket_consumable[Cod_Articol]", $final_price), 2, '.', '');

            $ticket_consumable_prices[$ticket_consumable['Cod_Articol']] = $final_final_price;
            $ticket_consumable['Pret_Vanz']                              = $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, "final_price_$ticket_consumable[Cod_Articol]", $final_final_price, false, ['style' => 'width:6em;', 'class' => 'p-2']);

            $cartridge_ids = str_replace('|', '', $ticket_consumable['new_cartridge_ids']);
            foreach ($cartridge->find(
                [
                    "id" => $cartridge_ids,
                    'NOT' => ['date_use' => 'null'],
                ]
            ) as $cartr) {
                $used_cartridges[$cartr['id']] = ['id' => $cartr['id'], 'ticket_use' => $cartr['tickets_id_use_field']];
            }

            unset($ticket_consumable['id']);
            unset($ticket_consumable['price']);
            unset($ticket_consumable['amount']);
            unset($ticket_consumable['euro_price']);
            unset($ticket_consumable['tickets_id']);
            unset($ticket_consumable['locations_id']);
            unset($ticket_consumable['new_cartridge_ids']);
            unset($ticket_consumable['cartridgeitem_name']);
            unset($ticket_consumable['plugin_iservice_consumables_id']);
            unset($ticket_consumable['plugin_fields_cartridgeitemtypedropdowns_id']);

            $total_amount += $final_final_price * $ticket_consumable['Cant'];

            // Preparing export data.
            $export_data .= $export_types[$ticket->customfields->fields['plugin_fields_ticketexporttypedropdowns_id']]['doc_tip'] . ',';
            $export_data .= $ticket->getID() . '-' . Dropdown::getDropdownName('glpi_users', $ticket->fields['_users_id_assign']) . ',';
            $export_data .= date("Y.m.d", $doc_date) . ',';
            $export_data .= $partner->customfields->fields['hmarfa_code_field'] . ',';
            $export_data .= $ticket_consumable['Cod_Articol'] . ',';
            $export_data .= number_format($ticket_consumable['Cant'], 2, '.', '') . ',';
            $export_data .= number_format($final_final_price, 2, '.', '') . ',"';
            $export_data .= $consumable_description . "\"\r\n";

            // Creating row to display.
            $consumables_rows[] = new PluginIserviceHtml_table_row('', $ticket_consumable);
            // Preparing table header.
            if (empty($consumables_columns)) {
                $consumables_columns = array_keys($ticket_consumable);
            }
        }

        $vat                  = IserviceToolBox::getInputVariable('vat', 19);
        $vatField             = $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'vat', $vat, false, ['style' => 'width: 3em;', 'class' => 'p-2']);
        $total_vat_value      = number_format($total_amount * $vat / 100, 2, '.', '');
        $total_value_with_vat = number_format($total_vat_value + $total_amount, 2, '.', '');
        $total_value          = number_format($total_amount, 2, '.', '');

        $consumables_rows[] = new PluginIserviceHtml_table_row('tall', new PluginIserviceHtml_table_cell("TVA: $vatField% &nbsp;&nbsp;&nbsp; Total factură: $total_value + $total_vat_value TVA = $total_value_with_vat RON", '', 'text-align:right;font-weight:bold;', count($consumables_columns)));

        $export_error = null;
        $ticket_customfields_input[$ticket->customfields->getIndexName()] = $ticket->customfields->getID();
        if ($add) {
            if (false === file_put_contents($export_file_path . $export_file_name, $export_data, FILE_APPEND)) {
                $export_error = "Error writing file: $export_file_path$export_file_name\r\n" . print_r(error_get_last(), true);
                $finished     = 0;
            }
        }

        if ($save) {
            $ticket_customfields_input['consumable_prices_field'] = '';
            foreach ($ticket_consumable_prices as $consumable_id => $consumable_price) {
                if (!empty($ticket_customfields_input['consumable_prices_field'])) {
                    $ticket_customfields_input['consumable_prices_field'] .= '###';
                }

                $ticket_customfields_input['consumable_prices_field'] .= "$consumable_id:" . number_format($consumable_price, 2, '.', '');
            }

            $ticket_customfields_input['consumable_descriptions_field'] = '';
            foreach ($ticket_consumable_descriptions as $consumable_id => $consumable_description) {
                if (!empty($ticket_customfields_input['consumable_descriptions_field'])) {
                    $ticket_customfields_input['consumable_descriptions_field'] .= '###';
                }

                $ticket_customfields_input['consumable_descriptions_field'] .= "$consumable_id:$consumable_description";
            }

            if ($finished !== null) {
                $ticket_customfields_input['exported_field'] = $finished;
            }

            if (count($ticket_customfields_input) > 1) {
                $ticket_customfields = new PluginFieldsTicketticketcustomfield();
                $ticket_customfields->update($ticket_customfields_input);
                if ($_SESSION["MESSAGE_AFTER_REDIRECT"]) {
                    Html::displayMessageAfterRedirect();
                }

                $ticket->getFromDB($id);
            }

            $partner_customfields_input = [
                $partner->customfields->getIndexName() => $partner->customfields->getID(),
                'email_for_invoices_field' => $ticket_partner_email
            ];
            $partner_customfields       = new PluginFieldsSuppliersuppliercustomfield();
            $partner_customfields->update($partner_customfields_input);
        }

        if (!$ticket->customfields->fields['delivered_field']) {
            $acknowledge_disabled_reason = $import_disabled_reason = $add_disabled_reason = $wait_disabled_reason = $export_disabled_reason = 'Finalizați livrarea înainte de export!';
        }

        $otherCsvLineWarning = '';
        $unfinishedString    = 'nefinalizată';
        if (count($export_file_name_suffixes) > 1) {
            $otherCsvLineWarning = (count($export_file_name_suffixes) > 2 ? 'alte facturi' : 'altă factură') . ' de consumabile';
            $unfinishedString    = count($export_file_name_suffixes) > 2 ? 'nefinalizate' : 'nefinalizată';
        } elseif (!file_exists($export_file_path . $export_file_name)) {
            $export_disabled_reason = $import_disabled_reason = 'Adăugați date in fișier întâi!';
        }

        $otherCsvs = glob("{$export_file_path}S*.*.{$partner->getID()}.*");
        if (count($otherCsvs) > 0) {
            $unfinishedString     = (empty($otherCsvLineWarning) && count($otherCsvs) < 2) ? 'nefinalizată' : 'nefinalizate';
            $otherCsvLineWarning .= (empty($otherCsvLineWarning) ? '' : ' și ') . (count($otherCsvs) > 1 ? 'facturi' : 'factură') . ' de servicii';
        }

        if (empty($otherCsvLineWarning)) {
            $otherCsvLineWarning = '&nbsp;';
        } else {
            $otherCsvLineWarning  = "<span style='color:red;font-weight:bold;'>ATENȚIE! Aveți $otherCsvLineWarning $unfinishedString!</span>";
            $otherCsvLineWarning .= " <input name='acknowledge_other_csvs' type='checkbox' " . ($acknowledge_other_csvs ? 'checked' : '') . ($acknowledge_disabled_reason ? ' disabled' : '') . " onclick='$(\"[name=refresh]\").click();' title='$acknowledge_disabled_reason' /> Continuă";
            if (!$acknowledge_other_csvs) {
                $import_disabled_reason = $import_disabled_reason ?: 'Bifați "continuă" de lângă atenționare';
                $add_disabled_reason    = $add_disabled_reason ?: 'Bifați "continuă" de lângă atenționare';
            }
        }

        $consumables_header = [
            0 => new PluginIserviceHtml_table_row('tall', new PluginIserviceHtml_table_cell(_tn('Consumable', 'Consumables', 2), '', 'font-weight:bold;text-align:left;', count($consumables_columns))),
            1 => new PluginIserviceHtml_table_row('short'),
        ];
        $consumables_header[1]->populateCells($consumables_columns, '', '', 'th');

        $consumables_table = new PluginIserviceHtml_table('tab_cadre_fixe wide', $consumables_header, $consumables_rows, 'text-align:center;');

        // Action buttons.
        $action_buttons = " <a class='vsubmit' href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Tickets' target='_blank'>" . _t('Ticket list') . "</a>";
        if ($ticket->getOrderStatus() === 0) {
            $action_buttons .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
            $action_buttons .= "&nbsp;" . $form->generateSubmit('order', _t('Order'));
        }

        $action_buttons .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        if (Session::haveRight('plugin_iservice_ticket_hmarfa_export_close', CREATE)) {
            $action_buttons .= '&nbsp;' . $form->generateSubmit('wait', _t('Pending'), empty($wait_disabled_reason) ? [ 'onclick' => 'if ($(this).hasClass("disabled")) { return false; }'] : ['class' => 'submit disabled', 'onclick' => 'if ($(this).hasClass("disabled")) { return false; }', 'title' => $wait_disabled_reason]);
            $action_buttons .= '&nbsp;' . $form->generateSubmit('finish', 'Finalizează', empty($export_disabled_reason) ? [ 'onclick' => 'if ($(this).hasClass("disabled")) { return false; }'] : ['class' => 'submit disabled', 'onclick' => 'if ($(this).hasClass("disabled")) { return false; };', 'title' => $export_disabled_reason]);
        } else {
            $action_buttons .= '&nbsp;' . $form->generateSubmit('save', __('Save'));
        }

        $action_buttons .= '&nbsp;' . $form->generateSubmit('to_ticket', 'Înapoi la ticket');

        $action_buttons .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        $action_buttons .= $form->generateSubmit('refresh', __('Update'));
        $action_buttons .= "&nbsp;<select onchange='$(\"[name=export_file_name_suffix]\").val($(this).val());$(\"[name=refresh]\").click();' style='width:250px;'>";
        foreach ($export_file_name_suffixes as $suffix) {
            $action_buttons .= "<option name='$suffix' " . ($suffix === $export_file_name_suffix ? 'selected' : '') . ">$suffix</option>";
        }

        $action_buttons .= "</select>";
        $action_buttons .= " <i class='pointer fa fa-trash' onclick='$(\"[name=delete]\").click();' style='color:red;'></i><input name='delete' type='submit' style='display: none;'/>";
        // $action_buttons .= " <i class='pointer fa fa-check' onclick='$(\"[name=import]\").click();' style='color:green'>";
        $import_button_options = ['style' => 'color:red;', 'onclick' => 'if ($(this).hasClass("disabled")) { return false; }'];
        if ($import_disabled_reason) {
            $import_button_options['class'] = 'submit disabled';
            $import_button_options['title'] = $import_disabled_reason;
        }

        $action_buttons .= " " . $form->generateSubmit('import', 'Importat în hMarfa', $import_button_options);

        $datafiles_table  = "<table class='tab_bg_1' width='100%'>\n";
        $datafiles_table .= "  <tr><th colspan=2>";
        $datafiles_table .= $form->generateSubmit('add', 'Adaugă in document', empty($add_disabled_reason) ? [ 'onclick' => 'if ($(this).hasClass("disabled")) { return false; }'] : ['class' => 'submit disabled', 'onclick' => 'if ($(this).hasClass("disabled")) { return false; }', 'title' => $add_disabled_reason]);
        $datafiles_table .= ' ' . $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'export_file_path', $export_file_path, false, ['style' => 'width:200px;']);
        $datafiles_table .= " $export_file_name_base.";
        $datafiles_table .= $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'export_file_name_suffix', $export_file_name_suffix, false, ['style' => 'width:200px;', 'onchange' => 'checkExportFileNameSuffix();']);
        $datafiles_table .= ".{$partner->getID()}.csv";
        $datafiles_table .= "  </th></tr>";
        $datafiles_table .= "  <tr>";
        $datafiles_table .= "    <td><textarea id='csv_text' rows=12 style='width:99%'>" . ($export_error !== null ? $export_error : (file_exists($export_file_path . $export_file_name) ? file_get_contents($export_file_path . $export_file_name) : "")) . "</textarea></td>";
        $datafiles_table .= "  </tr>";
        $datafiles_table .= "  <tr><td align='middle'>";
        $datafiles_table .=
            "Istoric: <select id='backup_year' name='backup_year' style='width: 75px;' class='p-2' onchange='refreshHistoryData();'></select> " .
            "<select id='backup_month' name='backup_month' style='width: 75px;' class='p-2' onchange='refreshHistoryData(true);'></select> " .
            "<select id='backup_name' name='backup_name' style='width: 377px;' class='p-2'></select> " .
            "<input class='submit' name='restore' type='submit' value='Restabilire'>";
        $datafiles_table .= "  </td></tr>";
        $datafiles_table .= "</table>";

        // Currency rate.
        $invoice_currency_rate = $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'currency_rate', $currency_rate, false, ['style' => 'width: 6em;']);
        if ($currency_error === null) {
            $invoice_currency_rate .= "&nbsp;&nbsp;<input type='button' name='refresh' class='submit' value='BNR: $official_currency->EuroCaNumar' onClick='document.getElementsByName(\"currency_rate\")[0].value=\"$official_currency->EuroCaNumar\";'>";
        } else {
            $invoice_currency_rate .= "&nbsp;&nbsp;<input type='button' name='refresh' class='submit' value='BNR: $currency_error'>";
        }

        $invoice_info_table = new PluginIserviceHtml_table('tab_cadre_fixe wide', [$form->generateFieldTableRow('Curs valutar', $invoice_currency_rate)]);

        $form->openForm(
            [
                'id' => 'hmarfa-export-form',
                'name' => 'hmarfa-export-form',
                'class' => 'hmarfa-export-form ticket',
                'method' => 'post',
            ]
        );

        $right_side_table = new PluginIserviceHtml_table(
            'tab_cadre_fixe wide', $right_side_header, [
                new PluginIserviceHtml_table_row('tall', $partner_email_table),
                new PluginIserviceHtml_table_row('', $partner_info_table),
                new PluginIserviceHtml_table_row('tall', $invoice_info_table),
                new PluginIserviceHtml_table_row('', $consumables_table),
                new PluginIserviceHtml_table_row('tall', $otherCsvLineWarning, 'text-align:center;'),
                new PluginIserviceHtml_table_row('tall', $action_buttons, 'text-align:center;'),
                new PluginIserviceHtml_table_row('tall', [new PluginIserviceHtml_table_cell($datafiles_table, '', '', 2)])
            ]
        );

        echo $right_side_table;
        $form->closeForm();

        echo "<script>\n";
        $backupData = [];
        echo "  var years = [];";
        echo "  var months = [];";
        echo "  var backup_data = [];\n";
        foreach (glob("$back_file_path/*.{$partner->getID()}.*.$export_file_name_prefix.csv") as $path) {
            $file_name_parts = explode('.', pathinfo($path, PATHINFO_FILENAME));
            if (count($file_name_parts) < 4) {
                continue;
            }

            $dateParts = explode('-', $file_name_parts[0]);
            if (count($dateParts) < 2) {
                continue;
            }

            $backupData[$dateParts[0]][$dateParts[1]][] = $file_name_parts[2];
        }

        foreach (array_keys($backupData) as $year) {
            echo "  years.push('$year');\n";
            echo "  months.push([]);\n";
            echo "  backup_data.push([]);\n";
            echo "  $('#backup_year').append(new Option($year, $year, false, false));\n";
            foreach (array_keys($backupData[$year]) as $month) {
                echo "  months[years.indexOf('$year')].push('$month');\n";
                echo "  backup_data[years.indexOf('$year')].push([]);\n";
                foreach ($backupData[$year][$month] as $name) {
                    echo "  backup_data[years.indexOf('$year')][months[years.indexOf('$year')].indexOf('$month')].push('$name');\n";
                }
            }
        }

        echo "  $('#backup_year option:last').attr('selected', true);\n";
        echo "  function refreshHistoryData(ignoreMonth) {
                    $('#backup_name option').remove();
                    if (months.length > 0 && (ignoreMonth === undefined || !ignoreMonth)) {
                        $('#backup_month option').remove();
                        months[years.indexOf($('#backup_year').val())].forEach(function(item){
                            $('#backup_month').append(new Option(item, item));
                        });
                        $('#backup_month option:last').attr('selected', true);
                    }
                    if (months.length < 1) {
                        return;
                    }
                    backup_data[years.indexOf($('#backup_year').val())][months[years.indexOf($('#backup_year').val())].indexOf($('#backup_month').val())].forEach(function(item){
                        $('#backup_name').append(new Option(item, item));
                    });
                    $('#backup_name option:last').attr('selected', true);
                }
                
                function checkExportFileNameSuffix() {
                    let year = '" . date('Y') . "'
                    let month = '" . date('m') . "'
                    if (years.indexOf(year) < 0 ||  months[years.indexOf(year)].indexOf(month) < 0 || backup_data[years.indexOf(year)][months[years.indexOf(year)].indexOf(month)].indexOf($('[name=export_file_name_suffix]').val()) < 0) {
                        $('[name=add]').removeClass('disabled');
                        return;
                    }
                    alert('Această nume a fost deja folosită în această lună!');
                    $('[name=add]').addClass('disabled');
                }
                ";
        echo "  jQuery(document).ready(function() {refreshHistoryData();});";
        echo "</script>\n";
    }

    protected static function generateInputField($prefix, $name, $value, $readonly = false, $style = '', $withCounter = false, $suffix = '', $class = '')
    {
        $result = "$prefix<input type='text' id='$name' name='$name' value='$value'"
                . ($readonly ? " readonly='readonly'" : "")
                . (empty($style) ? "" : " style='$style'")
                . (empty($class) ? "" : " class='$class'")
                . ($withCounter ? " onkeyup='document.getElementsByName(\"cnt_$name\")[0].value=this.value.length;'" : "")
                . "/>";
        if ($withCounter) {
            $result .= "&nbsp;&nbsp;&nbsp;";
            $result .= "<input type='text' name='cnt_$name' value='" . strlen($value) . "' style='width:20px'/>";
        }

        $result .= $suffix;
        return $result;
    }

    protected static function generateCheckboxField($prefix, $name, $checked = false, $suffix = "", $style = '')
    {
        return "$prefix<input type='checkbox' id='$name' name='$name' style='$style'"
                . ($checked ? " checked='checked'" : "")
                . "/>$suffix";
    }

    protected static function generateTextArea($prefix, $name, $value, $readonly = false, $style = '', $withCounter = false, $suffix = '')
    {
        $result = "$prefix<textarea name='$name'"
                . ($readonly ? " readonly='readonly'" : "")
                . (empty($style) ? "" : " style='$style'")
                . ($withCounter ? " onkeyup='document.getElementsByName(\"cnt_$name\")[0].value=this.value.length;'" : "")
                . ">$value</textarea>";
        if ($withCounter) {
            $result .= "&nbsp;&nbsp;&nbsp;";
            $result .= "<input type='text' name='cnt_$name' value='" . strlen($value) . "' style='width:20px;vertical-align:top;margin-top:2px;'/>";
        }

        $result .= $suffix;
        return $result;
    }

    protected static function generateQuantityAndTotalFields($number, $quantity, $fixed, $total)
    {
        $fixedPart = self::generateCheckboxField("&nbsp;Fixeaza&nbsp;", "fixed$number", $fixed, "", "margin-top: 3px;");
        $totalPart = self::generateInputField("$fixedPart &nbsp;&nbsp;Total: ", "total$number", $total, true, "width:65px");
        return self::generateInputField("&nbsp;&nbsp;Cantitate:", "cant$number", $quantity, false, "width:65px", false, $totalPart);
    }

    protected static function generateInputFieldRow($prefix, $inputField, $label = '', $label_tooltip = '')
    {
        return "$prefix<tr><td title='$label_tooltip'>$label</td><td>$inputField</td></tr>\n";
    }

    public static function getNextImportText()
    {
        $nexthMarfaImportTime = self::getNextImportTime();
        if ($nexthMarfaImportTime === null) {
            $text = "hMarfaImport nu a rulat niciodată";
        } elseif ($nexthMarfaImportTime === '') {
            $text = 'hMarfaImport ar fi trebuit să ruleze cu mai mult de 60 secunde in urmă';
        } elseif ($nexthMarfaImportTime === 0) {
            $text = 0;
        } else {
            $text = $nexthMarfaImportTime - time();
        }

        if (is_int($text)) {
            return "hMarfa import în <span class='countdown'>$text</span> secunde";
        } else {
            return "<span style='color: red;'>$text</span>";
        }
    }

    /*
     * @return int|null Return the time when hMarfaImport should run next
     *                  null if hMarfaImport was never run
     *                  empty string if hMarfaImport should have run more than 60 seconds ago
     *                  0 if hMarfaImport should run as soon as possible.
     */
    protected static function getNextImportTime()
    {
        $cronTasks = PluginIserviceDB::getQueryResult("select * from glpi_crontasks where itemtype='PluginIserviceHMarfaImporter' and name='hMarfaImport'");
        $cronTask  = array_shift($cronTasks);

        if (empty($cronTask['lastrun'])) {
            return null;
        }

        $next = strtotime($cronTask['lastrun']) + $cronTask['frequency'];
        $h    = date('H', $next);
        $deb  = ($cronTask['hourmin'] < 10 ? "0" . $cronTask['hourmin']
            : $cronTask['hourmin']);
        $fin  = ($cronTask['hourmax'] < 10 ? "0" . $cronTask['hourmax']
            : $cronTask['hourmax']);

        if (($deb < $fin)
            && ($h < $deb)
        ) {
            $disp = date('Y-m-d', $next) . " $deb:00:00";
            $next = strtotime($disp);
        } else if (($deb < $fin)
            && ($h >= $cronTask['hourmax'])
        ) {
            $disp = date('Y-m-d', $next + DAY_TIMESTAMP) . " $deb:00:00";
            $next = strtotime($disp);
        }

        if (($deb > $fin) && ($h < $deb) && ($h >= $fin)) {
            $disp = date('Y-m-d', $next) . " $deb:00:00";
            $next = strtotime($disp);
        }

        $difference = $next - time();
        return ($difference < -60) ? '' : ($difference < 0 ? 0 : $next);
    }

}

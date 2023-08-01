<?php

// Imported from iService2, needs refactoring.
/*
 * @copyright Copyright (c) 2019 hupu
 * @author    hupu
 * @license   Proprietary
 * @since     2022-04-28
 */
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

class PluginIserviceHmarfa_Invoicer // extends PluginIserviceHmarfa
{

    public static function showInvoiceExportForm()
    {

        $buttons = IserviceToolBox::getInputVariables(
            [
                'add' => null,
                'import' => null,
                'update' => null,
                'delete' => null,
                'restore' => null,
                'refresh' => null,
                'overwrite' => false,
                'generate_magic_link' => null,
            ]
        );

        $items = self::getItemsFromInput($buttons);

        if ($buttons['generate_magic_link']) {
            PluginIservicePartner::generateNewMagicLink($items['first']['supplier']);
            $buttons['refresh'] = 'refresh';
        }

        self::renderFrontend($items, self::getFrontendData($items, $buttons));
    }

    protected static function getItemsFromInput(array $buttons): array
    {
        foreach (['printer', 'router'] as $itemType) {
            foreach (IserviceToolBox::getArrayInputVariable('item')[$itemType] ?? [] as $itemId => $itemData) {
                $result['input'][$itemId] = $itemData;
            }
        }

        if (empty($result['input'])) {
            Html::displayErrorAndDie('Niciun aparat selectat!');
        }

        $result['invoice_total'] = 0;

        $result['contracts'] = PluginIserviceDB::getQueryResult(
            "
            SELECT 
                ci.items_id
              , c.id
              , c.num
              , c.currency_field
              , c.copy_price_divider_field
              , c.included_copies_bk_field
              , c.included_copies_col_field
              , c.included_copy_value_field
              , c.monthly_fee_field
              , c.copy_price_bk_field
              , c.copy_price_col_field
              , ct.name contract_type
            FROM glpi_contracts_items ci
            JOIN glpi_plugin_iservice_contracts c on c.id = ci.contracts_id and ci.itemtype = 'Printer'
            LEFT JOIN glpi_contracttypes ct on ct.id = c.contracttypes_id
            WHERE ci.items_id in (" . implode(",", array_keys($result['input'])) . ")
            ",
            'items_id'
        );

        foreach (array_keys($result['input']) as $itemId) {
            $item = new PluginIservicePrinter();

            if (!$item->getFromDB($itemId)) {
                $result['errors']['item'][] = $itemId;
                continue;
            }

            $itemState = new State();
            $itemState->getFromDB($item->fields['states_id']);
            $itemLocation = new Location();
            $itemLocation->getFromDB($item->fields['locations_id']);
            $itemLocationParent = new Location();
            $itemLocationParent->getFromDB($itemLocation->fields['locations_id'] ?? 0);

            $itemManufacturer = new Manufacturer();
            $itemManufacturer->getFromDB($item->fields['manufacturers_id']);

            $itemModel = new PrinterModel();
            $itemModel->getFromDB($item->fields['printermodels_id']);

            if (empty($result['first'])) {
                // It is important to create a new PluginIserviceInstance for the first Item!
                $firstItem = new PluginIservicePrinter();
                $firstItem->getFromDB($itemId);
                $firstItemTechUser = new User();
                $firstItemTechUser->getFromDB($item->fields['users_id_tech']);
                $firstItemState = new State();
                $firstItemState->getFromDB($item->fields['states_id']);
                $firstItemLocation = new Location();
                $firstItemLocation->getFromDB($firstItem->fields['locations_id']);
                $firstItemInfoCom = new Infocom();
                $firstItemInfoCom->getFromDBforDevice('Printer', $itemId);
                $firstItemSupplier = new PluginIservicePartner();
                $firstItemSupplier->getFromDB($firstItemInfoCom->fields['suppliers_id']);
                $result['first'] = [
                    'item' => $firstItem,
                    'tech' => $firstItemTechUser,
                    'state' => $firstItemState,
                    'location' => $itemLocation,
                    'supplier' => $firstItemSupplier,
                    'contract' => $result['contracts'][$itemId] ?? [],
                ];
            } elseif ($item->fields['supplier_id'] != $result['first']['item']->fields['supplier_id']) {
                $result['errors']['supplier'][] = $item;
                continue;
            } elseif ($item->fields['states_id'] != $result['first']['item']->fields['states_id']) {
                if (!str_starts_with(strtolower($itemState->fields['name']), 'co') || !str_starts_with(strtolower($result['first']['state']->fields['name']), 'co')) {
                    $result['errors']['state'][] = $item;
                    continue;
                }
            }

            $result['ids'][]  = $itemId;
            $contractData     = $result['contracts'][$itemId] ?? [];
            $contractRate     = floatval($contractData['currency_field'] ?? 1) ?: 1;
            $contractCurrency = $contractRate > 1 ? "EUR" : ($contractRate < 1 ? "???" : "RON");

            $item->tableData['invoice_rate']              = IserviceToolBox::getInputVariable('invoice_rate', intval($result['input'][$itemId]['invoice_rate'] ?? 0) ?: ($contractCurrency === "EUR" ? (IserviceToolBox::getExchangeRate('Euro') ?? $contractRate) : $contractRate));
            $item->tableData['contract_id']               = $contractData['id'] ?? null;
            $item->tableData['contract_number']           = $contractData['num'] ?? '';
            $item->tableData['contract_type']             = $contractData['contract_type'] ?? '';
            $item->tableData['state_name']                = $itemState->fields['name'];
            $item->tableData['id']                        = $itemId;
            $item->tableData['name']                      = $item->fields['original_name'];
            $item->tableData['model_name']                = $itemModel->fields['name'] ?? '';
            $item->tableData['manufacturer_name']         = $itemManufacturer->fields['name'] ?? '';
            $item->tableData['location']                  = $itemLocation->fields['completename'] ?? '';
            $item->tableData['location_parent']           = $itemLocationParent->fields['completename'] ?? '';
            $item->tableData['cost_center_field']         = $item->customfields->fields['cost_center_field'] ?? '';
            $item->tableData['usage_address_field']       = $item->customfields->fields['usage_address_field'] ?? '';
            $item->tableData['invoice_expiry_date_field'] = $result['input'][$itemId]['invoice_expiry_date_field'] ?? (!empty($item->customfields->fields['invoice_expiry_date_field']) ? date('Y-m-d', strtotime($item->customfields->fields['invoice_expiry_date_field'])) : '');
            $item->tableData['printertype']               = $item->fields['printertypes_id'];

            switch ($item->fields['printertypes_id']) {
            case PluginIservicePrinter::ID_ROUTER_TYPE:
                $item->tableData['data_ult_fact']   = ($item->lastTicket()->customfields->fields['effective_date_field'] < $item->customfields->fields['invoice_date_field']) ? IserviceToolBox::addMonthToDate($item->tableData['invoice_expiry_date_field'], 1) : $item->lastTicket()->customfields->fields['effective_date_field'] ;
                $item->tableData['data_fact_until'] = $result['input'][$itemId]['data_fact_until'] ?? ($item->tableData['data_ult_fact'] ?? '');
                $item->tableData['pret_unitar']     = $result['input'][$itemId]['pret_unitar'] ?? '';
                $item->tableData['cantitate']       = $result['input'][$itemId]['cantitate'] ?? '';
                $item->tableData['total']           = $result['input'][$itemId]['total'] ?? '';
                $item->tableData['description']     = $result['input'][$itemId]['description'] ?? '';
                $item->tableData['switch']          = $result['input'][$itemId]['switch_text'] ?? 'partial';
                $item->tableData['switch_part']     = $result['input'][$itemId]['switch_part_text'] ?? 'lung';

                $result['routers'][$itemId] = $item;
                $result['invoice_total']   += floatval($item->tableData['total']);
                break;
            default:
                $item->tableData['printer_codmat']  = self::getPrinterCodmat($result['contracts'][$itemId]['contract_type'] ?? '');
                $item->tableData['no_invoice']      = $item->customfields->fields['no_invoice_field'] ?? false;
                $item->tableData['data_ult_fact']   = $item->customfields->fields['invoice_date_field'] ?? '';
                $item->tableData['data_fact_until'] = $result['input'][$itemId]['data_fact_until'] ?? null;
                $item->tableData['val_ult_fact']    = $item->customfields->fields['invoiced_value_field'] ?? 0 ?: 'necunoscut';
                if (is_numeric($item->tableData['val_ult_fact'])) {
                    $item->tableData['val_ult_fact'] = IserviceToolBox::numberFormat($item->tableData['val_ult_fact'], 2);
                }

                $item->tableData['serial']                    = $item->fields['serial'];
                $item->tableData['otherserial']               = $item->fields['otherserial'];
                $item->tableData['contor_bk_uf']              = $item->customfields->fields['invoiced_total_black_field'] ?? 0;
                $item->tableData['contor_bk_ui']              = $item->lastTicket()->fields['total2_black'] ?? 0;
                $item->tableData['contor_col_uf']             = $item->customfields->fields['invoiced_total_color_field'] ?? 0;
                $item->tableData['contor_col_ui']             = $item->lastTicket()->fields['total2_color'] ?? 0;
                $item->tableData['data_ui']                   = empty($item->lastTicket()->customfields->fields['effective_date_field']) ? '' : date('Y-m-d', strtotime($item->lastTicket()->customfields->fields['effective_date_field']));
                $item->tableData['divizor_copii']             = ($contractData['copy_price_divider_field'] ?? 1) ?: 1;
                $item->tableData['cop_bk_inclus']             = $contractData['included_copies_bk_field'] ?? 0;
                $item->tableData['included_copies_col_field'] = $contractData['included_copies_col_field'] ?? 0;
                $item->tableData['included_copy_value_field'] = $contractData['included_copy_value_field'] ?? 0;
                $item->tableData['monthly_fee_field']         = $contractData['monthly_fee_field'] ?? 0;
                $item->tableData['copy_price_bk_field']       = $contractData['copy_price_bk_field'] ?? 0;
                $item->tableData['tarif_cop_col']             = $contractData['copy_price_col_field'] ?? 0;
                $item->tableData['rows']                      = self::getPrinterTableData($item, $result['input'][$itemId], $buttons);
                $item->tableData['subtotal']                  = IserviceToolBox::numberFormat(
                    $item->tableData['rows'][1]['total'] +
                    (($item->tableData['rows'][2]['total'] > 0 || $item->tableData['rows'][2]['fixed']) ? $item->tableData['rows'][2]['total'] : 0) +
                    (($item->tableData['rows'][3]['total'] > 0 || $item->tableData['rows'][3]['fixed']) ? $item->tableData['rows'][3]['total'] : 0),
                    2
                );

                $result['printers'][$itemId] = $item;
                $result['invoice_total']    += $item->tableData['subtotal'];
                break;
            }
        }

        if (!empty($result['errors'])) {
            global $CFG_GLPI;
            echo "<div class='center'><br><br>";
            echo Html::image("$CFG_GLPI[root_doc]/pics/warning.png", ['alt' => __('Warning')]);
            foreach ($result['errors'] as $errorType => $typeErrors) {
                switch ($errorType) {
                case 'supplier':
                    echo "<br><br><span class='b'>Următoarele aparate nu aparțin partenerului {$result['first']['item']->fields['supplier_name']}:</span>";
                    break;
                case 'state':
                    echo "<br><br><span class='b'>Următoarele aparate nu aparțin proiectului {$result['first']['state']->fields['name']}:</span>";
                    break;
                }

                foreach ($typeErrors as $error) {
                    echo "<br><span>[" . $error->getID() . "] - " . $error->fields['name'] . "</span>";
                }
            }

            echo "<br><br><br></div>";
        }

        return $result;
    }

    protected static function getPrinterCodmat(?string $contractType): string
    {
        $contractType = strtolower($contractType);

        if (in_array($contractType, ['coluna_1', 'coluna_2', 'coluna_3', 'cotrim'])) {
            return 'S049';
        }

        if (stripos($contractType, 'coluna') === 0) {
            return 'S045';
        }

        if (stripos($contractType, 'cofull') === 0) {
            return 'S047';
        }

        if (stripos($contractType, 'coinc') === 0 || stripos($contractType, 'proiect') === 0) {
            return 'S048';
        }

        return "S048";
    }

    protected static function getMailingData($items): array
    {
        $months = [
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

        return [
            'subject' => "Factura ExpertLine - {$items['first']['supplier']->fields['name']} - " . $months[date("n")] . ", " . date("Y"),
            'body' => $items['first']['supplier']->getMailBody(),
        ];
    }

    protected static function getPrinterTableData(PluginIservicePrinter $printer, $printerInputData, array $buttons): array
    {
        if (!is_array($printerInputData)) {
            $printerInputData = [$printerInputData];
        }

        $rows['location']     = $printerInputData['location'] ?? $printer->tableData['location'];
        $locationText         = empty($printerInputData['include_location']) ? '' : sprintf('%s - ', $rows['location']);
        $rows['cost_center']  = $printerInputData['cost_center'] ?? $printer->tableData['cost_center_field'];
        $costCenterText       = empty($printerInputData['include_cost_center']) ? '' : sprintf('*%s* - ', $rows['cost_center']);
        $rows['usageaddress'] = $printerInputData['usageaddress'] ?? $printer->tableData['usage_address_field'];
        $usageaddressText     = empty($printerInputData['include_usageaddress']) ? '' : sprintf('%s - ', $rows['usageaddress']);
        $statusText           = empty($printerInputData['include_status']) ? '' : " - {$printer->tableData['state_name']}";

        if ($printer->tableData['no_invoice']) {
            for ($i = 1; $i < 4; $i++) {
                $rows[$i] = [
                    'codmat' => '',
                    'val'    => 0,
                    'um'     => '',
                    'cant'   => 0,
                    'fixed'  => 0,
                    'total'  => 0,
                    'descr'  => '',
                    'to'     => 0,
                    'from'   => 0,
                ];
            }

            $rows['until'] = '';
            return $rows;
        }

        $usedValue =
            $printer->tableData['copy_price_bk_field'] * ($printer->tableData['contor_bk_ui'] - $printer->tableData['contor_bk_uf']) +
            $printer->tableData['tarif_cop_col'] * ($printer->tableData['contor_col_ui'] - $printer->tableData['contor_col_uf']);

        $allowedCounterBlack = $printer->tableData['contor_bk_uf'];
        if ($printer->tableData['contor_bk_ui'] >= $printer->tableData['contor_bk_uf']) {
            $allowedCounterBlack += $printer->tableData['cop_bk_inclus'];
        }

        $allowedCounterColor = $printer->tableData['contor_col_uf'];
        if ($printer->tableData['contor_col_ui'] >= $printer->tableData['contor_col_uf']) {
            $allowedCounterColor += $printer->tableData['included_copies_col_field'];
        }

        $printer->tableData['equalizer_coefficient'] = 0;
        $printer->tableData['cop_bk_echiv']          = 0;
        $printer->tableData['cop_col_echiv']         = 0;
        // If echivalent calculation.
        if (stripos($printer->tableData['contract_type'], 'coie') === 0) {
            // If color copies are included but not used.
            if ($printer->tableData['included_copies_col_field'] && $printer->tableData['contor_col_ui'] < $allowedCounterColor) {
                $printer->tableData['equalizer_coefficient'] = round($printer->tableData['tarif_cop_col'] / $printer->tableData['copy_price_bk_field'], 2);
                $printer->tableData['cop_bk_echiv']          = floor(($allowedCounterColor - $printer->tableData['contor_col_ui']) * $printer->tableData['equalizer_coefficient']);
                $printer->tableData['cop_col_echiv']         = 0;
                $allowedCounterBlack                        += $printer->tableData['cop_bk_echiv'];
            } elseif ($printer->tableData['cop_bk_inclus'] && $printer->tableData['contor_bk_ui'] < $allowedCounterBlack) {
                $printer->tableData['equalizer_coefficient'] = round($printer->tableData['copy_price_bk_field'] / $printer->tableData['tarif_cop_col'], 2);
                $printer->tableData['cop_bk_echiv']          = 0;
                $printer->tableData['cop_col_echiv']         = floor(($allowedCounterBlack - $printer->tableData['contor_bk_ui']) * $printer->tableData['equalizer_coefficient']);
                $allowedCounterColor                        += $printer->tableData['cop_col_echiv'];
            }
        }

        $differenceCounterBlack = $printer->tableData['counter_difference_bk'] = $printer->tableData['contor_bk_ui'] - $allowedCounterBlack;
        $differenceCounterColor = $printer->tableData['counter_difference_col'] = $printer->tableData['contor_col_ui'] - $allowedCounterColor;

        if ($printer->tableData['printertype'] === PluginIservicePrinter::ID_PLOTTER_TYPE) {
            $row2Subject = 'ml cereneala consumata';
            $row3Subject = 'mp suprafata printata';
        } else {
            $row2Subject = 'copii a/n';
            $row3Subject = 'copii color';
        }

        $untilPart = '';

        // Row 1.
        $val1    = self::numberFormat($printer->tableData['monthly_fee_field'] * $printer->tableData['invoice_rate']);
        $cant1   = self::numberFormat(!empty($printerInputData['row_1']['cant']) && !empty($printerInputData['row_1']['fixed']) ? $printerInputData['row_1']['cant'] : 1);
        $rows[1] = [
            'codmat' => (!empty($printerInputData['row_1']['codmat']) && !empty($printerInputData['row_1']['fixed'])) ? $printerInputData['row_1']['codmat'] : ($printer->tableData['printer_codmat'] . 'L'),
            'val'    => $printerInputData['row_1']['val'] ?? $val1,
            'um'     => $printerInputData['row_1']['um'] ?? 'lună',
            'cant'   => $cant1,
            'fixed'  => $printerInputData['row_1']['fixed'] ?? 0,
            'total'  => self::numberFormat($val1 * $cant1),
        ];

        $rows['until'] = strtotime($printer->tableData['invoice_expiry_date_field']);
        if (date('d', $rows['until']) > 25) {
            $period        = date("m.Y", strtotime("+1 month", strtotime("-5 days", $rows['until'])));
            $rows['until'] = $printer->tableData['data_fact_until'] ?? date("Y-m-t", strtotime("+1 month", strtotime("-5 days", $rows['until'])));
        } else {
            $period        = date("d.m", strtotime("+1 day", $rows['until'])) . "-" . date("d.m.y", strtotime("+1 month", $rows['until']));
            $rows['until'] = $printer->tableData['data_fact_until'] ?? date("Y-m-d", strtotime("+1 month", $rows['until']));
        }

        if ($rows[1]['cant'] > 0) {
            $descr1 = "{$printer->tableData["manufacturer_name"]} {$printer->tableData["model_name"]} ($costCenterText$locationText$usageaddressText{$printer->tableData["serial"]})$statusText";
            // The new default is to include the period, so if we don't have anything from the input we have to include it.
            if (!isset($printerInputData['include_period']) || $printerInputData['include_period']) {
                $descr1 .= " / $period";
            }

            $rows[1]['from'] = $printer->tableData['contor_bk_uf'] . ($printer->isColor() ? " - {$printer->tableData['contor_col_uf']}" : '');

            if ($printer->tableData['included_copy_value_field'] > 0) {
                $untilPart  = ($printer->tableData['contor_bk_ui'] > 0) ? "{$printer->tableData['contor_bk_ui']} $row2Subject" : '';
                $untilPart .= ($printer->isColor() && $printer->tableData['contor_col_ui'] > 0) ? (empty($untilPart) ? '' : ' si ') . "{$printer->tableData['contor_col_uf']} $row3Subject" : '';
                if ($usedValue <= $printer->tableData['included_copy_value_field']) {
                    $descr1 .= " pana la $untilPart";
                }

                $rows[1]['to'] = $printer->tableData['contor_bk_ui'] > 0 ? $printer->tableData['contor_bk_ui'] : "";
                if (!empty($rows[1]['to'])) {
                    $rows[1]['to'] .= ' - ';
                }

                $rows[1]['to'] .= $printer->tableData['contor_col_ui'] > 0 ? $printer->tableData['contor_col_ui'] : "";
            } elseif ($printer->tableData['cop_bk_inclus'] > 0 || $printer->tableData['included_copies_col_field'] > 0) {
                $rows[1]['to']   = 0;
                $rows[1]['from'] = 0;
                $descr1         .= ' de la ';

                if ($printer->tableData['cop_bk_echiv'] || $printer->tableData['cop_bk_inclus']) {
                    $descr1 .= "{$printer->tableData['contor_bk_uf']}";
                    if ($printer->tableData['cop_bk_inclus'] || $printer->tableData['cop_bk_echiv']) {
                        $descr1 .= ' la ' . min($printer->tableData['contor_bk_ui'], $allowedCounterBlack);
                    }

                    $descr1 .= " $row2Subject";
                    if ($printer->tableData['cop_col_echiv'] || $printer->tableData['included_copies_col_field']) {
                        $descr1 .= ", ";
                    }
                }

                if ($printer->tableData['cop_col_echiv'] || $printer->tableData['included_copies_col_field']) {
                    $descr1 .= "de la {$printer->tableData['contor_col_uf']}";
                    if ($printer->tableData['included_copies_col_field'] || $printer->tableData['cop_col_echiv']) {
                        $descr1 .= ' la ' . min($printer->tableData['contor_col_ui'], $allowedCounterColor);
                    }

                    $descr1 .= " $row3Subject";
                }
            } else {
                $rows[1]['to'] = '';
                if (!empty($printer->tableData['cop_bk_inclus'])) {
                    if ($differenceCounterBlack <= 0) {
                        $descr1        .= " pana la {$printer->tableData['contor_bk_ui']} $row2Subject";
                        $rows[1]['to'] .= $printer->tableData['contor_bk_ui'];
                    } else {
                        $descr1        .= " de la {$printer->tableData['contor_bk_uf']} la $allowedCounterBlack $row2Subject";
                        $rows[1]['to'] .= $allowedCounterBlack;
                    }
                }

                if (!empty($printer->tableData['included_copies_col_field'])) {
                    if (!empty($printer->tableData['cop_bk_inclus'])) {
                        $descr1        .= " si";
                        $rows[1]['to'] .= ' - ';
                    }

                    if ($differenceCounterColor <= 0) {
                        $descr1        .= " pana la {$printer->tableData['contor_col_ui']} $row3Subject";
                        $rows[1]['to'] .= $printer->tableData['contor_col_ui'];
                    } else {
                        $descr1        .= " de la {$printer->tableData['contor_col_uf']} la $allowedCounterColor $row3Subject";
                        $rows[1]['to'] .= $allowedCounterColor;
                    }
                }
            }
        } else {
            $descr1          = '';
            $rows[1]['to']   = 0;
            $rows[1]['from'] = 0;
        }

        $rows[1]['descr'] = !empty($buttons['refresh']) || !isset($printerInputData['row_1']['descr']) ? $descr1 : $printerInputData['row_1']['descr'];

        $descrPart = "{$printer->tableData["model_name"]} ($costCenterText{$printer->tableData["serial"]})";

        if ($printer->tableData['printertype'] === PluginIservicePrinter::ID_PLOTTER_TYPE) {
            $row2Subject = 'ml cereneala consumata';
            $row3Subject = 'mp suprafata printata';
        } else {
            $row2Subject = 'copii suplimentare a/n';
            $row3Subject = 'copii suplimentare color';
        }

        // Row 2.
        $codmat2 = (!empty($printerInputData['row_2']['codmat']) && !empty($printerInputData['row_2']['fixed'])) ? $printerInputData['row_2']['codmat'] : ($printer->tableData['printer_codmat'] . ($printer->tableData['printer_codmat'] === 'S045' ? '' : ($printer->tableData['included_copy_value_field'] > 0 ? 'V' : 'B')));
        if ($printer->tableData['included_copy_value_field'] > 0) {
            $val2            = ($usedValue - $printer->tableData['included_copy_value_field'] > 0) ? ($usedValue - $printer->tableData['included_copy_value_field']) * $printer->tableData['invoice_rate'] : 0;
            $cant2           = ($val2 > 0) ? 1 : 0;
            $descr2          = "$descrPart valoare copii suplimentare pana la $untilPart";
            $rows[2]['from'] = $printer->tableData['contor_bk_uf'];
        } else {
            $val2  = $printer->tableData['copy_price_bk_field'] * $printer->tableData['invoice_rate'] * $printer->tableData['divizor_copii'];
            $cant2 = (isset($printerInputData['row_2']['cant']) && ($printerInputData['row_2']['fixed'] ?? 0)) ? $printerInputData['row_2']['cant'] : (empty($codmat2) || empty($val2) ? 0 : $differenceCounterBlack / $printer->tableData['divizor_copii']);
            if ($printer->tableData['divizor_copii'] > 1) {
                $divizorExplanation2 = " (" . ($cant2 * $printer->tableData['divizor_copii']) . "=$cant2*{$printer->tableData['divizor_copii']} copii)";
            } else {
                $divizorExplanation2 = "";
            }

            $descr2          = "$descrPart de la $allowedCounterBlack la " . ($allowedCounterBlack + $cant2 * $printer->tableData['divizor_copii']) . " $row2Subject$divizorExplanation2";
            $rows[2]['from'] = $allowedCounterBlack;
        }

        $rows[2]['to'] = $printer->tableData['contor_bk_ui'];

        if (empty($codmat2) || $cant2 === 0) {
            $descr2 = '';
        }

        $rows[2] = [
            'codmat' => $codmat2,
            'val'    => self::numberFormat($val2),
            'um'     => 'copie bk',
            'cant'   => self::numberFormat($cant2),
            'fixed'  => $printerInputData['row_2']['fixed'] ?? 0,
            'total'  => self::numberFormat($val2 * $cant2),
            'descr'  => !empty($buttons['refresh']) || !isset($printerInputData['row_2']['descr']) ? $descr2 : $printerInputData['row_2']['descr'],
        ];

        // Row 3.
        $codmat3 = (!empty($printerInputData['row_3']['codmat']) && !empty($printerInputData['row_3']['fixed'])) ? $printerInputData['row_3']['codmat'] : ($printer->tableData['printer_codmat'] . ($printer->tableData['printer_codmat'] === 'S045' ? '' : ($printer->tableData['included_copy_value_field'] > 0 ? '' : 'C')));
        $val3    = (empty($codmat3)) ? 0 : $printer->tableData['tarif_cop_col'] * $printer->tableData['invoice_rate'] * $printer->tableData['divizor_copii'];
        $cant3   = (isset($printerInputData['row_3']['cant']) && ($printerInputData['row_3']['fixed'] ?? 0)) ? $printerInputData['row_3']['cant'] : (empty($codmat3) || $val3 == 0 ? 0 : ($differenceCounterColor / $printer->tableData['divizor_copii']));
        if ($printer->tableData['divizor_copii'] > 1) {
            $divizorExplanation3 = " (" . ($cant3 * $printer->tableData['divizor_copii']) . "=$cant3*{$printer->tableData['divizor_copii']} copii)";
        } else {
            $divizorExplanation3 = "";
        }

        if ($printer->tableData['included_copy_value_field'] > 0) {
            $val3  = 0;
            $cant3 = 0;
        }

        $descr3          = (empty($codmat3) || $cant3 === 0) ? "" : "$descrPart de la $allowedCounterColor la " . ($allowedCounterColor + $cant3 * $printer->tableData['divizor_copii']) . " $row3Subject$divizorExplanation3";
        $rows[3]['to']   = $printer->tableData['contor_col_ui'];
        $rows[3]['from'] = $allowedCounterColor;

        $rows[3] = [
            'codmat' => $codmat3,
            'val'    => self::numberFormat($val3),
            'um'     => 'copie col',
            'cant'   => self::numberFormat($cant3),
            'fixed'  => $printerInputData['row_3']['fixed'] ?? 0,
            'total'  => self::numberFormat($val3 * $cant3),
            'descr'  => !empty($buttons['refresh']) || !isset($printerInputData['row_3']['descr']) ? $descr3 : $printerInputData['row_3']['descr']
        ];

        return $rows;
    }

    protected static function getExportFileData(array $items): array
    {
        global $CFG_PLUGIN_ISERVICE;

        $exportFileData = [
            'partner_name' => preg_replace('/[^A-z0-9-]/', '-', trim($items['first']['supplier']->fields["name"])),
            'name_suffix' => IserviceToolBox::getInputVariable('export_file_name_suffix', date('YmdHis')),
            'path' => IserviceToolBox::getInputVariable("exportfilepath", $CFG_PLUGIN_ISERVICE['hmarfa']['export']['default_path']),
        ];

        $exportFileData['safe_suffix']       = IserviceToolBox::getHtmlSanitizedValue($exportFileData['name_suffix']);
        $exportFileData['base_name']         = implode('.', [$exportFileData['partner_name'], $exportFileData['safe_suffix'], $items['first']['supplier']->getID()]);
        $exportFileData['csv_name']          = "S.$exportFileData[base_name].csv";
        $exportFileData['ext_csv_name']      = "SX.$exportFileData[base_name].csv";
        $exportFileData['csv_full_path']     = "$exportFileData[path]/$exportFileData[csv_name]";
        $exportFileData['ext_csv_full_path'] = "$exportFileData[path]/$exportFileData[ext_csv_name]";

        $exportFileData['dat_path']      = "$exportFileData[path]/DAT";
        $exportFileData['dat_name']      = "$exportFileData[base_name].dat";
        $exportFileData['dat_full_path'] = "$exportFileData[dat_path]/$exportFileData[dat_name]";

        $exportFileData['dat_exists']     = file_exists($exportFileData['dat_full_path']);
        $exportFileData['csv_exists']     = file_exists($exportFileData['csv_full_path']);
        $exportFileData['ext_csv_exists'] = file_exists($exportFileData['ext_csv_full_path']);

        $exportFileData = array_merge(
            $exportFileData, IserviceToolBox::getInputVariables(
                [
                    'backup_path' => "$exportFileData[path]/BAK",
                    'backup_year' => null,
                    'backup_month' => null,
                    'backup_name' => null,
                ]
            )
        );

        foreach (['backup_path', 'dat_path'] as $pathKey) {
            if (!file_exists($exportFileData[$pathKey])) {
                mkdir($exportFileData[$pathKey], 0775, true);
            }
        }

        return $exportFileData;
    }

    protected static function getInvoiceData(array $items, array $exportFileData): array
    {
        // Nr comanda.
        if (str_starts_with(strtolower($items['first']['state']->fields['name']), 'pro')) {
            $defaultNrCmd =
                explode('"', $items['first']['state']->fields['name'])[1] ??
                explode(' ', $items['first']['state']->fields['name'])[0] ??
                '';
        } else {
            $defaultNrCmd = $items['first']['tech']->fields['name'] ?? $items['first']['state']->fields['name'] ?? '';
        }

        // Currency.
        $firstContractCurrency = floatval($items['first']['contract']['currency_field'] ?? 1) ?: 1;

        return IserviceToolBox::getInputVariables(
            [
                'email_for_invoices_field' => $items['first']['supplier']->customfields->fields['email_for_invoices_field'],
                'nrcmd' => $defaultNrCmd,
                'doc_date' => date("Y-m-d"),
                'invoice_rate' => $firstContractCurrency > 1 ? (IserviceToolBox::getExchangeRate('Euro') ?? $firstContractCurrency) : $firstContractCurrency,

                's039' => !$exportFileData['csv_exists'],
                's039_include_status' => IserviceToolBox::getInputVariable('s039_include_status', str_starts_with(strtolower($items['first']['state']->fields['name']), 'pro')),
                's039_include_period' => IserviceToolBox::getInputVariable('s039_include_period', 0),
                's039_period_from' => $items['printers'][$items['first']['item']->getID()]->tableData['invoice_expiry_date_field'] ?? $items['routers'][$items['first']['item']->getID()]->tableData['invoice_expiry_date_field'],
                's039_period_to' => $items['printers'][$items['first']['item']->getID()]->tableData['rows']['until'] ?? $items['routers'][$items['first']['item']->getID()]->tableData['data_fact_until'] ?? '',

                'general_include_location' => false,
                'general_include_cost_center' => false,
                'general_include_usageaddress' => false,
                'general_include_period' => true,
                'general_include_status' => false,

                'codmat_router' => 'S048RO',
                'show_dat' => false,
            ]
        );
    }

    /*
     * @param  $items
     * @param  $exportFileData
     * @param  $invoiceData
     * @param  $buttons
     * @return array Empty array on success, otherwise the 'errors' indexed element contains the errors
     */
    protected static function doExport($items, &$exportFileData, $invoiceData, $buttons): array
    {
        $result['firstLine'] = [];

        if ($buttons['restore']) {
            self::restoreBackupFile($exportFileData, $items);
            // $exportFileData['name_suffix'] will be null after this function
        }

        if ($exportFileData['name_suffix'] && $buttons['delete']) {
            self::deleteExportFile($exportFileData, $items);
            // $exportFileData['name_suffix'] will be null after this function
        }

        if ($exportFileData['name_suffix'] && $buttons['import']) {
            self::importExportFile($exportFileData, $items);
            // $exportFileData['name_suffix'] will be null after this function
        }

        self::adjustExportFileSuffix($exportFileData, $items);

        if ($invoiceData['s039'] || $invoiceData['s039_include_status'] || $invoiceData['s039_include_period']) {
            if (!empty($invoiceData['s039'])) {
                $descr_array[] = $items['first']['contract']['num'] ?? '';
            }

            if (!empty($invoiceData['s039_include_status'])) {
                $descr_array[] = $items['first']['state']->fields['name'];
            }

            if (!empty($invoiceData['s039_include_period'])) {
                $descr_array[] = "perioada facturata: de la {$invoiceData['s039_period_from']} pana la {$invoiceData['s039_period_to']}";
            }

            $result['firstLine']['CODMAT'] = empty($invoiceData['s039']) && empty($invoiceData['s039_include_status']) ? "S039-G" : "S039-S";
            $result['firstLine']['DESCR']  = implode(', ', $descr_array ?? []);
        }

        if ($buttons['add']) {
            $datExportData           = [];
            $csvExportData[]         = ($exportFileData['csv_exists'] && !$buttons['overwrite']) ? '' : [
                'DOC_TIP',
                'NRCMD',
                'DOC_DATA',
                'PART_COD',
                'CODMAT',
                'CANT',
                'DOC_VAL',
                'DESCR'
            ];
            $csvExtendedExportData[] = ($exportFileData['ext_csv_exists'] && !$buttons['overwrite']) ? '' : [
                'DOC_TIP',
                'NRCMD',
                'DOC_DATA',
                'PART_COD',
                'CODMAT',
                'CANT',
                'DOC_VAL',
                'DESCR',
                'CENTRU_COST',
                'CTR_DE_LA',
                'CTR_PANA_LA',
                'LOCATIE',
                'APARAT',
                'SERIE'
            ];

            if ($invoiceData['s039'] || $invoiceData['s039_include_status'] || $invoiceData['s039_include_period']) {
                $csvExportData[] = [
                    'DOC_TIP' => 'TFAC',
                    'NRCMD' => $invoiceData['nrcmd'],
                    'DOC_DATA' => date("Y.m.d", strtotime($invoiceData['doc_date'])),
                    'PART_COD' => $items['first']['supplier']->customfields->fields['hmarfa_code_field'],
                    'CODMAT' => $result['firstLine']['CODMAT'],
                    'CANT' => 1,
                    'DOC_VAL' => 0,
                    'DESCR' => $result['firstLine']['DESCR'],
                ];
            }

            foreach ($items['printers'] ?? [] as $printer) {
                for ($row = 1; $row < 4; $row++) {
                    if ($printer->tableData['rows'][$row]['cant'] > 0 || $printer->tableData['rows'][$row]['fixed']) {
                        $data = [
                            'DOC_TIP'  => 'TFAC',
                            'NRCMD'    => $invoiceData['nrcmd'],
                            'DOC_DATA' => date("Y.m.d", strtotime($invoiceData['doc_date'])),
                            'PART_COD' => $items['first']['supplier']->customfields->fields['hmarfa_code_field'],
                            'CODMAT'   => $printer->tableData['rows'][$row]['codmat'],
                            'CANT'     => $printer->tableData['rows'][$row]['cant'],
                            'DOC_VAL'  => $printer->tableData['rows'][$row]['val'],
                            'DESCR'    => $printer->tableData['rows'][$row]['descr'],
                        ];

                        $csvExportData[]         = $data;
                        $csvExtendedExportData[] = array_merge(
                            $data, [
                                'CENTRU_COST' => $printer->tableData['rows']['cost_center'],
                                'CTR_DE_LA'   => $printer->tableData['rows'][$row]['from'] ?? '',
                                'CTR_PANA_LA' => $printer->tableData['rows'][$row]['to'] ?? '',
                                'LOCATIE'     => $printer->tableData['rows']['location'],
                                'APARAT'      => $printer->fields['name'],
                                'SERIE'       => $printer->fields['serial']
                            ]
                        );
                    }
                }

                $datExportData[] = [
                    'ITEM_ID' => $printer->tableData['id'],
                    'DOC_DATE' => $invoiceData['doc_date'],
                    'DATA_FACT_UNTIL' => $printer->tableData['data_fact_until'],
                    'COUNTER_BK' => $printer->tableData['contor_bk_ui'],
                    'COUNTER_COL' => $printer->tableData['contor_col_ui'],
                    'TOTAL_FACT' => $printer->tableData['subtotal'],
                ];
            }

            foreach ($items['routers'] ?? [] as $router) {
                $usageaddressfield = $invoiceData['general_include_usageaddress'] ? "{$router->tableData['usage_address_field']} - " : "";
                $cost_center       = $invoiceData['general_include_cost_center'] ? "*{$router->tableData['cost_center_field']}* - " : "";

                $csvExportData[] = [
                    'DOC_TIP' => 'TFAC',
                    'NRCMD' => $invoiceData['nrcmd'],
                    'DOC_DATA' => date("Y.m.d", strtotime($invoiceData['doc_date'])),
                    'PART_COD' => $items['first']['supplier']->customfields->fields['hmarfa_code_field'],
                    'CODMAT' => $invoiceData['codmat_router'],
                    'CANT' => self::numberFormat($router->tableData['cantitate']),
                    'DOC_VAL' => self::numberFormat($router->tableData['pret_unitar']),
                    'DESCR' => $cost_center . $usageaddressfield . $router->tableData['description'],
                ];

                $datExportData[] = [
                    'ITEM_ID' => $router->tableData['id'],
                    'DOC_DATE' => $invoiceData['doc_date'],
                    'DATA_FACT_UNTIL' => $router->tableData['data_fact_until'],
                    'COUNTER_BK' => '',
                    'COUNTER_COL' => '',
                ];
            }

            foreach (['csv' => $csvExportData, 'ext_csv' => $csvExtendedExportData, 'dat' => $datExportData] as $dataType => $data) {
                if (null !== ($csvResult = IserviceToolBox::writeCsvFile($exportFileData[$dataType . '_full_path'], $data, !$buttons['overwrite']))) {
                    $result['errors'][$dataType] = $csvResult;
                }
            }
        } elseif ($buttons['update']) {
            if (!file_exists($exportFileData['dat_full_path'])) {
                $result['errors']['dat'] = "$exportFileData[dat_full_path] does not exist";
                return $result;
            }

            $printerCustomfields = new PluginFieldsPrinterprintercustomfield();
            foreach (IserviceToolBox::getCsvFile($exportFileData['dat_full_path']) as $data) {
                if (count($data) > 1 && $printerCustomfields->getFromDBByItemsId($data[0])) {
                    $updateData = [
                        $printerCustomfields->getIndexName() => $printerCustomfields->getID(),
                        "invoice_date_field" => $data[1],
                        "invoice_expiry_date_field" => $data[2],
                        "invoiced_value_field" => $data[5] ?? null,
                    ];
                    if ($data[3] !== '') {
                        $updateData['invoiced_total_black_field'] = $data[3];
                    }

                    if ($data[4] !== '') {
                        $updateData['invoiced_total_color_field'] = $data[4];
                    }

                    $printerCustomfields->update($updateData);

                    // Display the updated fields
                    foreach (['printers', 'routers'] as $itemType) {
                        if (isset($items[$itemType][$data[0]])) {
                            $items[$itemType][$data[0]]->tableData['invoice_expiry_date_field'] = $data[2];
                            if ($data[3] !== '' && isset($items[$itemType][$data[0]]->tableData['invoiced_total_black_field'])) {
                                $items[$itemType][$data[0]]->tableData['invoiced_total_black_field'] = $data[3];
                            }

                            if ($data[4] !== '' && isset($items[$itemType][$data[0]]->tableData['invoiced_total_color_field'])) {
                                $items[$itemType][$data[0]]->tableData['invoiced_total_color_field'] = $data[4];
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    protected static function restoreBackupFile(&$exportFileData, $items)
    {
        $backupPattern =
            "$exportFileData[backup_path]/$exportFileData[backup_year]-$exportFileData[backup_month].{$items[first][supplier]->getID()}.$exportFileData[backup_name].[DS]*.*";

        foreach (glob($backupPattern) as $oldFilePath) {
            $fileNameParts = explode('.', pathinfo($oldFilePath, PATHINFO_BASENAME));
            if (count($fileNameParts) < 5) {
                continue;
            }

            if (strtolower($fileNameParts[4]) === 'dat') {
                $newFilePath = "$exportFileData[path]/DAT/$exportFileData[partner_name].$fileNameParts[2].$fileNameParts[1].$fileNameParts[4]";
            } else {
                $newFilePath = "$exportFileData[path]/$fileNameParts[3].$exportFileData[partner_name].$fileNameParts[2].$fileNameParts[1].$fileNameParts[4]";
            }

            rename($oldFilePath, $newFilePath);
        }

        $exportFileData['name_suffix'] = null;
    }

    protected static function deleteExportFile(&$exportFileData, $items)
    {
        foreach (glob("$exportFileData[path]/S*.*.$exportFileData[name_suffix].{$items['first']['supplier']->getID()}.*") as $path) {
            unlink($path);
        }

        foreach (glob("$exportFileData[path]/DAT/*.$exportFileData[name_suffix].{$items['first']['supplier']->getID()}.dat") as $path) {
            unlink($path);
        }

        $exportFileData['name_suffix'] = null;
    }

    protected static function importExportFile(&$exportFileData, $items)
    {
        foreach (glob("$exportFileData[path]/S*.*.$exportFileData[name_suffix].{$items['first']['supplier']->getID()}.*") as $oldFilePath) {
            $fileNameParts = explode('.', pathinfo($oldFilePath, PATHINFO_BASENAME));
            if (count($fileNameParts) < 5) {
                continue;
            }

            $newFilePath = "$exportFileData[backup_path]/" . date("Y-m", filectime($oldFilePath)) . ".{$items['first']['supplier']->getID()}.$exportFileData[name_suffix].$fileNameParts[0].$fileNameParts[4]" ;
            rename($oldFilePath, $newFilePath);
        }

        foreach (glob("$exportFileData[path]/DAT/*.$exportFileData[name_suffix].{$items['first']['supplier']->getID()}.dat") as $oldFilePath) {
            $fileNameParts = explode('.', pathinfo($oldFilePath, PATHINFO_BASENAME));
            if (count($fileNameParts) < 4) {
                continue;
            }

            $newFilePath = "$exportFileData[backup_path]/" . date("Y-m") . ".{$items['first']['supplier']->getID()}.$exportFileData[name_suffix].D.$fileNameParts[3]" ;
            rename($oldFilePath, $newFilePath);
        }

        $exportFileData['name_suffix'] = null;
    }

    protected static function adjustExportFileSuffix(&$exportFileData, $items)
    {
        $exportFileData['name_suffixes'] = [];
        foreach (glob("$exportFileData[path]/S.*.{$items['first']['supplier']->getID()}.*") as $path) {
            $fileNameParts = explode('.', pathinfo($path, PATHINFO_FILENAME));
            if (count($fileNameParts) < 3) {
                continue;
            }

            $exportFileData['name_suffixes'][] = $fileNameParts[2];
        }

        $exportFileData['name_suffix'] = $exportFileData['name_suffix'] ?: end($exportFileData['name_suffixes']) ?: date('YmdHis');

        if (!in_array($exportFileData['name_suffix'], $exportFileData['name_suffixes'])) {
            $exportFileData['name_suffixes'][] = $exportFileData['name_suffix'];
        } else {
            foreach (glob("$exportFileData[path]/S.*.$exportFileData[name_suffix].{$items['first']['supplier']->getID()}.*") as $path) {
                $fileNameParts = explode('.', pathinfo($path, PATHINFO_FILENAME));
                if (count($fileNameParts) < 2) {
                    continue;
                }

                $exportFileData['partner_name'] = $fileNameParts[1];
                break;
            }
        }
    }

    protected static function getFrontendData(array $items, array $buttons): array
    {
        $frontendData['mailData']       = self::getMailingData($items);
        $exportFileData                 = self::getExportFileData($items);
        $frontendData['invoiceData']    = self::getInvoiceData($items, $exportFileData);
        $frontendData['exportResult']   = self::doExport($items, $exportFileData, $frontendData['invoiceData'], $buttons);
        $frontendData['exportFileData'] = $exportFileData;

        $frontendData['other_csv_line_warning'] = '';
        $frontendData['import_disabled_reason'] = '';
        $unfinishedString                       = 'nefinalizată';
        $acknowledgeOtherCsvs                   = IserviceToolBox::getInputVariable('acknowledge_other_csvs');

        if (count($exportFileData['name_suffixes']) > 1) {
            $frontendData['other_csv_line_warning'] = (count($exportFileData['name_suffixes']) > 2 ? 'alte facturi' : 'altă factură') . ' de servicii';
            $unfinishedString                       = count($exportFileData['name_suffixes']) > 2 ? 'nefinalizate' : 'nefinalizată';
        } elseif (!file_exists($exportFileData['csv_full_path'])) {
            $frontendData['import_disabled_reason'] = 'Adăugați date in fișier întâi!';
        }

        $otherCsvs = glob("$exportFileData[path]/F*.*.{$items['first']['supplier']->getID()}.*");
        if (count($otherCsvs) > 0) {
            $unfinishedString                        = (empty($frontendData['other_csv_line_warning']) && count($otherCsvs) < 2) ? 'nefinalizată' : 'nefinalizate';
            $frontendData['other_csv_line_warning'] .= (empty($frontendData['other_csv_line_warning']) ? '' : ' și ') . (count($otherCsvs) > 1 ? 'facturi' : 'factură') . ' de consumabile';
        }

        if (empty($frontendData['other_csv_line_warning'])) {
            $frontendData['other_csv_line_warning'] = '&nbsp;';
        } else {
            $frontendData['other_csv_line_warning']  = "<span style='color:red;font-weight:bold;'>ATENȚIE! Aveți $frontendData[other_csv_line_warning] $unfinishedString!</span>";
            $frontendData['other_csv_line_warning'] .= " <input name='acknowledge_other_csvs' type='checkbox' " . ($acknowledgeOtherCsvs ? 'checked' : '') . " onclick='$(\"[name=refresh]\").click();'/> Continuă";
            if (!$acknowledgeOtherCsvs) {
                $frontendData['import_disabled_reason'] = $frontendData['import_disabled_reason'] ?: 'Bifați "continuă" de lângă atenționare';
                $frontendData['add_disabled_reason']    = 'Bifați "continuă" de lângă atenționare';
            }
        }

        return $frontendData;
    }

    protected static function renderFrontend(array $items, array $frontendData)
    {
        self::renderTitle($items);

        $form = new PluginIserviceHtml();
        $form->openForm(
            [
                'id' => 'hmarfa-invoicer-form',
                'name' => 'hmarfa-invoicer-form',
                'class' => 'hmarfa-invoicer-form iservice-form',
                'method' => 'post',
            ]
        );

        self::renderInvoiceHead($form, $items, $frontendData);
        self::renderPrinterSection($form, $items, $frontendData['invoiceData']);
        self::renderRouterSection($form, $items, $frontendData['invoiceData']);
        self::renderExportSection($form, $items, $frontendData);

        $form->closeForm();
    }

    protected static function renderTitle(array $items)
    {
        echo "<h1 style='margin: 1em 0;'>Facturare pentru {$items['first']['supplier']->fields['name']} {$items['first']['state']->fields['name']}</h1>\n";
    }

    protected static function renderInvoiceHead(PluginIserviceHtml $form, array $items, array $frontendData)
    {
        $form->openTable(['class' => 'tab_cadre wide']);

        $form->displayTableRow(
            [
                'Informații partener',
                'Informații facturare',
            ], ['row_options' => ['style' => 'text-align: center'], 'column_options' => [['style' => 'width: 55%']]], 'th'
        );

        $form->openTableRow();

        $form->openTableColumn();
        self::renderPartnerInformation($form, $items, $frontendData);
        $form->closeTableColumn();

        $form->openTableColumn(['style' => 'padding-left: 20px']);
        self::renderInvoiceInformation($form, $items, $frontendData);
        $form->closeTableColumn();

        $form->closeTableRow();

        $form->closeTable();
    }

    protected static function renderPartnerInformation(PluginIserviceHtml $form, array $items, array $frontendData)
    {
        global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;

        $mailData    = $frontendData['mailData'];
        $invoiceData = $frontendData['invoiceData'];
        /*
        $magic_link_label = "Link magic partener:";
        $magic_link_button_name = "Genereaza";
        $magic_link_button_class = "";
        if (!empty($items['first']['supplier']->customfields->fields['magic_link_field'])) {
            $magic_link = $items['first']['supplier']->getMagicLink();
            $magic_link_label = "<a href='$magic_link' target='_blank'>$magic_link_label</a>";
            $magic_link_button_name .= " nou";
            $magic_link_button_class = " new";
        }
        $magic_link_button = "<input type='submit' name='generate_magic_link' class='submit$magic_link_button_class' value='$magic_link_button_name'>";
        if (empty($items['first']['supplier']->customfields->fields['uic_field'])) {
            $magic_link_button = "<span style='color: red'>Partenerul nu are CUI!</span>";
        }
        if ($items['first']['supplier']->customfields->fields['uic_field'] != $items['first']['supplier']->hMarfa_fields['cod1']) {
            $part_cui = $items['first']['supplier']->customfields->fields['uic_field'];
            $cod_cui = $items['first']['supplier']->hMarfa_fields['cod1'];
            $magic_link_button = "<span style='color: red'>CUI hMarfa si iService difera!<br>$part_cui != $cod_cui</span>";
        }
        /**/

        $form->openTable(['class' => 'no-border']);

        // $form->displayFieldTableRow($magic_link_label, $magic_link_button);
        $form->displayFieldTableRow(__('Sum of unpaid invoices', 'iservice') . ':', IserviceToolBox::getSumOfUnpaidInvoicesLink($items['first']['supplier']->getID(), $items['first']['supplier']->customfields->fields['hmarfa_code_field']));
        $form->displayFieldTableRow(
            "Nume " . $form->generateNewTabLink('partener', "$CFG_GLPI[root_doc]/front/supplier.form.php?id={$items['first']['supplier']->getID()}") . ':',
            $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", $items['first']['supplier']->fields["name"])
        );
        $form->displayFieldTableRow(
            "Număr " . $form->generateNewTabLink('contract', "$CFG_PLUGIN_ISERVICE[root_doc]/front/contract.form.php?contract_id={$items['first']['contract']['id']}") . ':',
            $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", $items['first']['contract']['num'] ?? '')
        );
        $form->displayFieldTableRow(
            "Tip contract:",
            $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", $items['first']['contract']['contract_type'])
        );
        $form->displayFieldTableRow(
            "Total factura:",
            "$items[invoice_total] + 19% TVA = <b>" . self::numberFormat($items['invoice_total'] * 1.19) . "</b> RON"
        );
        $form->displayFieldTableRow(
            "<a href='mailto:$invoiceData[email_for_invoices_field]?subject=$mailData[subject]&body=$mailData[body]'>Trimite email</a> către:",
            $invoiceData['email_for_invoices_field']
        );

        echo "<tr><td colspan='2'></td></tr>",

        $form->closeTable();
    }

    protected static function renderInvoiceInformation(PluginIserviceHtml $form, array $items, array $frontendData)
    {
        $invoiceData = $frontendData['invoiceData'];

        $form->openTable(['class' => 'no-border']);

        $form->displayFieldTableRow(
            'Nr comanda:',
            $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, "nrcmd", $invoiceData['nrcmd'])
        );
        $form->displayFieldTableRow(
            'Data facturare:',
            $form->generateField(PluginIserviceHtml::FIELDTYPE_DATE, "doc_date", $invoiceData['doc_date'])
        );
        $form->displayFieldTableRow(
            'Cod partener hMarfa:',
            $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "part_cod", $items['first']['supplier']->customfields->fields['hmarfa_code_field'])
        );

        $rateButtonStyle = 'padding: 1px 5px;border-radius: 5px;';
        $rateButtons     = "&nbsp;&nbsp;<input type='button' name='contract-currency-rate' class='submit' style='$rateButtonStyle' value='Contract: {$items['first']['contract']['currency_field']}' onClick='$(\"#invoice_rate\").val(\"{$items['first']['contract']['currency_field']}\");$(\"[name=refresh]\").click();'>";
        if ($invoiceData['invoice_rate'] > 1) {
            $official_rate = IserviceToolBox::getExchangeRate('Euro') ?? IserviceToolBox::$lastExchangeRateServiceError;
            $rateOnClick   = $official_rate === 'eroare' ? 'return false;' : "$(\"#invoice_rate\").val(\"$official_rate\");$(\"[name=refresh]\").click();";
            $rateButtons  .= "&nbsp;&nbsp;<input type='button' name='official-currency-rate' class='submit' style='$rateButtonStyle' value='BNR: $official_rate' onClick='$rateOnClick'>";
        }

        $form->displayFieldTableRow(
            'Curs valutar:',
            $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, "invoice_rate", $invoiceData['invoice_rate']) . $rateButtons
        );

        $form->displayFieldTableRow(
            'Prima linie:',
            $form->generateField(
                PluginIserviceHtml::FIELDTYPE_CHECKBOX,
                's039',
                $invoiceData['s039'],
                false,
                [
                    'label' => 'S039',
                    'onchange' => 'if(!$(this).is(":checked")){$("#_checkbox_helper_s039_include_period").prop("checked", false);$("#_checkbox_helper_s039_include_period").change();$("#_checkbox_helper_s039_include_status").prop("checked", false);$("#_checkbox_helper_s039_include_status").change();}updateFirstLine();'
                ]
            )
            . $form->generateField(
                PluginIserviceHtml::FIELDTYPE_CHECKBOX,
                's039_include_status',
                $invoiceData['s039_include_status'],
                false,
                [
                    'label' => 'Status',
                    'onchange' => 'if($(this).is(":checked")){$("#_checkbox_helper_s039").prop("checked", true);$("#_checkbox_helper_s039").change();}updateFirstLine();'
                ]
            )
            . $form->generateField(
                PluginIserviceHtml::FIELDTYPE_CHECKBOX,
                's039_include_period',
                $invoiceData['s039_include_period'],
                false,
                [
                    'label' => 'Perioada',
                    'onchange' => 'if($(this).is(":checked")){$("#_checkbox_helper_s039").prop("checked", true);$("#_checkbox_helper_s039").change();}$("#s039_period_fields").toggle($(this).is(":checked"));updateFirstLine();'
                ]
            )
            . '<span id="s039_period_fields"' . ($invoiceData['s039_include_period'] ? '' : ' style="display:none"') . '> de la '
            . $form->generateField(
                PluginIserviceHtml::FIELDTYPE_DATE,
                's039_period_from',
                $invoiceData['s039_period_from'],
                false,
                ['on_change' => 'updateFirstLine()']
            )
            . ' pana la ' . $form->generateField(
                PluginIserviceHtml::FIELDTYPE_DATE,
                's039_period_to',
                $invoiceData['s039_period_to'],
                false,
                ['on_change' => 'updateFirstLine()']
            )
            . '</span>'
        );

        $firstLineUpdaterScript = "
<script>
  function updateFirstLine() {
      let firstLine = '&nbsp;';
      if ($('#_checkbox_helper_s039').is(':checked')) {
          firstLine = 'S039-S: {$items['first']['contract']['num']}';
      }
      if ($('#_checkbox_helper_s039_include_status').is(':checked')) {
          firstLine = firstLine + ', {$items['first']['state']->fields['name']}';
      }
      if ($('#_checkbox_helper_s039_include_period').is(':checked')) {
          firstLine = firstLine + ', perioada facturata: de la ' + $('[name=s039_period_from]').val() + ' pana la ' + $('[name=s039_period_to]').val();
      }
      $('#first-line').html(firstLine);
  }
  updateFirstLine();
</script>";
        if (empty($frontendData['exportResult']['firstLine']['CODMAT'])) {
            $firstLine = '&nbsp;';
        } else {
            $firstLine = $frontendData['exportResult']['firstLine']['CODMAT'] . ': ' . $frontendData['exportResult']['firstLine']['DESCR'];
        }

        $form->displayFieldTableRow(
            '',
            "<span id='first-line'>$firstLine</span>$firstLineUpdaterScript"
        );

        $form->displayFieldTableRow(
            'In general:',
            $form->generateField(
                PluginIserviceHtml::FIELDTYPE_CHECKBOX,
                'general_include_location',
                $invoiceData['general_include_location'],
                false,
                ['label' => 'Locatie', 'onclick' => '$(".include_location").prop("checked", $(this).prop("checked")).change();']
            )
            . $form->generateField(
                PluginIserviceHtml::FIELDTYPE_CHECKBOX,
                'general_include_cost_center',
                $invoiceData['general_include_cost_center'],
                false,
                ['label' => 'Centru de cost', 'onclick' => '$(".include_cost_center").prop("checked", $(this).prop("checked")).change();']
            )
            . $form->generateField(
                PluginIserviceHtml::FIELDTYPE_CHECKBOX,
                'general_include_usageaddress',
                $invoiceData['general_include_usageaddress'],
                false,
                ['label' => 'Adresa expl.', 'onclick' => '$(".include_usageaddress").prop("checked", $(this).prop("checked")).change();']
            )
            . $form->generateField(
                PluginIserviceHtml::FIELDTYPE_CHECKBOX,
                'general_include_status',
                $invoiceData['general_include_status'],
                false,
                ['label' => 'Status', 'onclick' => '$(".include_status").prop("checked", $(this).prop("checked")).change();']
            )
            . $form->generateField(
                PluginIserviceHtml::FIELDTYPE_CHECKBOX,
                'general_include_period',
                $invoiceData['general_include_period'],
                false,
                ['label' => 'Perioada', 'onclick' => '$(".include_period").prop("checked", $(this).prop("checked")).change();']
            )
        );

        $form->closeTable();
    }

    protected static function renderPrinterSection(PluginIserviceHtml $form, array $items, array $invoiceData)
    {
        global $CFG_PLUGIN_ISERVICE;

        if (count($items['printers'] ?? []) < 1) {
            return;
        }

        echo "        <table class='tab_cadre wide' style='margin-top: 10px;'>\n";
        echo "            <tr><th style='width: 55%'>Copiatoare</th><th></th></tr>\n";
        foreach ($items['printers'] as $printer) {
            echo "        <tr><td>";
            echo "            <table class='no-border wide'>";
            echo "                <tr><td style='padding: 10px 10px 0 10px;'>";
            echo $form->generateNewTabLink(
                "{$printer->tableData['name']} | {$printer->tableData['serial']} | {$printer->tableData['otherserial']}",
                "$CFG_PLUGIN_ISERVICE[root_doc]/front/printer.form.php?id={$printer->getID()}",
                ['style' => ($printer->tableData['no_invoice'] ? 'color: red;' : '')]
            );
            echo "&nbsp;&nbsp;";
            $no_invoice_ajax_call        = "\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/managePrinter.php?id={$printer->tableData['id']}&operation=set_no_invoice&value=\" + ($(this).is(\":checked\") ? 1 : 0)";
            $no_invoice_success_function = "function(message) {if (message!=\"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);}}";
            $form->displayField(
                PluginIserviceHtml::FIELDTYPE_CHECKBOX,
                "item[printer][{$printer->tableData['id']}][no_invoice]",
                $printer->tableData['no_invoice'],
                false,
                [
                    'label' => 'Exclus din facturare',
                    'onchange' => "ajaxCall($no_invoice_ajax_call,\"\", $no_invoice_success_function);",

                ]
            );
            $lastInvoiceValueCurrency = is_numeric($printer->tableData['val_ult_fact']) ? ' RON' : '';
            echo "                <span style='float: right; cursor: pointer;' onclick='$(\".toggle_{$printer->tableData['id']}\").toggle();'>Valoare ultima factura: <b>{$printer->tableData['val_ult_fact']}</b>$lastInvoiceValueCurrency</span>";
            echo "                </td></tr><tr><td style='padding: 0px 10px 10px 10px;'>";
            $printerInfo   = [];
            $printerInfo[] = $printer->tableData['cost_center_field'] ?? '';
            $printerInfo[] = $printer->tableData['location'] ?? '';
            $printerInfo[] = $printer->tableData['usage_address_field'] ?? '';
            if (!is_numeric($printer->tableData['val_ult_fact'])) {
                $subtotalColor = 'black';
            } else {
                $subtotalDifferencePercent = abs(($printer->tableData['subtotal'] - $printer->tableData['val_ult_fact']) / $printer->tableData['val_ult_fact'] * 100);
                $subtotalColor             = $subtotalDifferencePercent > 20 ? 'red' : ($subtotalDifferencePercent > 10 ? 'orange' : 'black');
            }

            echo "                <b>" . implode(' | ', array_filter($printerInfo)) . "</b>";
            echo "                <span style='float: right; cursor: pointer;' onclick='$(\".toggle_{$printer->tableData['id']}\").toggle();'>Subtotal aparat: <b style='color:$subtotalColor'>{$printer->tableData['subtotal']}</b> RON</span>";
            echo "                </td></tr><tr class='toggle_{$printer->tableData['id']}'><td style='padding: 10px;'>";
            $form->displayField(
                PluginIserviceHtml::FIELDTYPE_CHECKBOX,
                "item[printer][{$printer->tableData['id']}][include_location]",
                $items['input'][$printer->tableData['id']]['include_location'] ?? $invoiceData['general_include_location'],
                false,
                ['label' => 'Locatie', 'class' => 'include_location']
            );
            $form->displayField(
                PluginIserviceHtml::FIELDTYPE_TEXT,
                "item[printer][{$printer->tableData['id']}][location]",
                $items['input'][$printer->tableData['id']]['location'] ?? $printer->tableData['location'],
                false,
                ['style' => 'width: 50px; margin-right: 10px']
            );
            $form->displayField(
                PluginIserviceHtml::FIELDTYPE_CHECKBOX,
                "item[printer][{$printer->tableData['id']}][include_cost_center]",
                $items['input'][$printer->tableData['id']]['include_cost_center'] ?? $invoiceData['general_include_cost_center'],
                false,
                ['label' => 'Centru cost', 'class' => 'include_cost_center']
            );
            $form->displayField(
                PluginIserviceHtml::FIELDTYPE_TEXT,
                "item[printer][{$printer->tableData['id']}][cost_center]",
                $items['input'][$printer->tableData['id']]['cost_center'] ?? $printer->tableData['cost_center_field'],
                false,
                ['style' => 'width: 50px; margin-right: 10px']
            );
            $form->displayField(
                PluginIserviceHtml::FIELDTYPE_CHECKBOX,
                "item[printer][{$printer->tableData['id']}][include_usageaddress]",
                $items['input'][$printer->tableData['id']]['include_usageaddress'] ?? $invoiceData['general_include_usageaddress'],
                false,
                ['label' => 'Adresa expl.', 'class' => 'include_usageaddress']
            );
            $form->displayField(
                PluginIserviceHtml::FIELDTYPE_TEXT,
                "item[printer][{$printer->tableData['id']}][usageaddress]",
                $items['input'][$printer->tableData['id']]['usageaddress'] ?? $printer->tableData['usage_address_field'],
                false,
                ['style' => 'width: 50px; margin-right: 10px']
            );
            $form->displayField(
                PluginIserviceHtml::FIELDTYPE_CHECKBOX,
                "item[printer][{$printer->tableData['id']}][include_status]",
                $items['input'][$printer->tableData['id']]['include_status'] ?? $invoiceData['general_include_status'],
                false,
                ['label' => 'Status', 'class' => 'include_status']
            );
            $form->displayField(
                PluginIserviceHtml::FIELDTYPE_CHECKBOX,
                "item[printer][{$printer->tableData['id']}][include_period]",
                $items['input'][$printer->tableData['id']]['include_period'] ?? $invoiceData['general_include_period'],
                false,
                ['label' => 'Perioada', 'class' => 'include_period']
            );
            echo "                </td></tr><tr class='toggle_{$printer->tableData['id']}'><td>";

            $form->openTable(['class' => 'no-border wide']);

            $form->openTableRow(['style' => 'text-align:center']);
            $form->displayTableColumn('Cod', ['style' => 'width:3em'], 'th');
            $form->displayTableColumn('PU', ['style' => 'width:3em'], 'th');
            $form->displayTableColumn('UM', ['style' => 'width:6em'], 'th');
            $form->displayTableColumn('Cantitate', ['style' => 'width:3em'], 'th');
            $form->displayTableColumn('Fixează', ['style' => 'width:3em'], 'th');
            $form->displayTableColumn('Total', ['style' => 'width:3em'], 'th');
            $form->displayTableColumn('Descriere', ['style' => ''], 'th');
            $form->closeTableRow();

            foreach ($printer->tableData['rows'] as $rowIndex => $printerRow) {
                if (!is_int($rowIndex)) {
                    continue;
                }

                $descrColor = ($printerRow['cant'] > 0 || $printerRow['fixed']) ? '' : 'color:white;';

                $form->displayTableRow(
                    [
                        $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, "item[printer][{$printer->tableData['id']}][row_$rowIndex][codmat]", $printerRow['codmat'], false, ['style' => 'width:3em']),
                        $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, "item[printer][{$printer->tableData['id']}][row_$rowIndex][val]", $printerRow['val'], false, ['style' => 'width:4em;text-align:right']),
                        $printerRow['um'],
                        $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, "item[printer][{$printer->tableData['id']}][row_$rowIndex][cant]", $printerRow['cant'], false, ['style' => 'width:4em;text-align:right']),
                        $printer->tableData['no_invoice'] ? '' : $form->generateField(PluginIserviceHtml::FIELDTYPE_CHECKBOX, "item[printer][{$printer->tableData['id']}][row_$rowIndex][fixed]", $printerRow['fixed']),
                        $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, "item[printer][{$printer->tableData['id']}][row_$rowIndex][total]", $printerRow['total'], false, ['style' => 'width:5em;text-align:right']),
                        $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, "item[printer][{$printer->tableData['id']}][row_$rowIndex][descr]", $printerRow['descr'], false, ['style' => "width:98%;$descrColor"]),
                    ], [
                        'column_options' => [[], [], ['style' => 'text-align:center'], [], ['style' => 'text-align:center'], [], []]
                    ]
                );
            }

            $form->closeTable();

            echo "                </td></tr>";
            echo "            </table>";
            echo "        </td><td  class='toggle_{$printer->tableData['id']}' style='padding-left: 20px'>";
            $form->openTable(['class' => "tab_cadre_fixe tab_cadrehov no-border no-shadow wide"]);
            $form->displayTableRow(
                [
                    'Data ultima factură emisă',
                    $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", date('Y-m-d', strtotime($printer->tableData['data_ult_fact'])), true),
                    "Număr " . $form->generateNewTabLink('contract', "$CFG_PLUGIN_ISERVICE[root_doc]/front/contract.form.php?contract_id={$printer->tableData['contract_id']}") . ':',
                    $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", $printer->tableData['contract_number'], true)
                ]
            );
            $form->displayTableRow(
                [
                    'Perioadă de la (exp. fact.)',
                    $form->generateField(PluginIserviceHtml::FIELDTYPE_DATE, "item[printer][{$printer->tableData['id']}][invoice_expiry_date_field]", $printer->tableData['invoice_expiry_date_field']),
                    'Tip contract',
                    $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", $printer->tableData['contract_type'], true)
                ]
            );
            $form->displayTableRow(
                [
                    'Data expirare factură',
                    $form->generateField(PluginIserviceHtml::FIELDTYPE_DATE, "item[printer][{$printer->tableData['id']}][data_fact_until]", $printer->tableData['rows']['until']),
                    'Divizor copii',
                    $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", $printer->tableData['divizor_copii'], true)
                ]
            );
            if ($printer->tableData['cop_bk_echiv']) {
                $additionalBlackCopies = "&nbsp;+&nbsp;<span style='color:green' title='{$printer->tableData['cop_bk_echiv']} = {$printer->tableData['counter_difference_col']} * {$printer->tableData['equalizer_coefficient']}'>{$printer->tableData['cop_bk_echiv']} copii echiv.</span>";
            } elseif ($printer->tableData['cop_col_echiv']) {
                $additionalBlackCopies = "&nbsp;(<span style='color:green'>" . ($printer->tableData['cop_bk_inclus'] + $printer->tableData['counter_difference_bk']) . " copii consumate</span>)";
            } else {
                $additionalBlackCopies = '';
            }

            $form->displayTableRow(
                [
                    'Contor bk ultima factură',
                    $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", $printer->tableData['contor_bk_uf'], true),
                    'Nr copii bk incluse',
                    $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", $printer->tableData['cop_bk_inclus'] . $additionalBlackCopies, true)
                ]
            );
            if ($printer->tableData['cop_col_echiv']) {
                $additionalColorCopies = "&nbsp;+&nbsp;<span style='color:green' title='{$printer->tableData['cop_col_echiv']} = {$printer->tableData['counter_difference_bk']} * {$printer->tableData['equalizer_coefficient']}'>{$printer->tableData['cop_col_echiv']} copii echiv.</span>";
            } elseif ($printer->tableData['cop_bk_echiv']) {
                $additionalColorCopies = "&nbsp;(<span style='color:green'>" . ($printer->tableData['included_copies_col_field'] + $printer->tableData['counter_difference_col']) . " copii consumate</span>)";
            } else {
                $additionalColorCopies = '';
            }

            $form->displayTableRow(
                [
                    'Contor bk ' . $form->generateNewTabLink('ultima intervenție', "$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?mode=" . PluginIserviceTicket::MODE_CLOSE . "&id={$printer->lastTicket()->getID()}"),
                    $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", $printer->tableData['contor_bk_ui'], true),
                    'Nr copii col incluse',
                    $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", $printer->tableData['included_copies_col_field'] . $additionalColorCopies, true)
                ]
            );
            $form->displayTableRow(
                [
                    'Contor col ultima factură',
                    $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", $printer->tableData['contor_col_uf'], true),
                    'Val copii incluse',
                    $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", $printer->tableData['included_copy_value_field'], true)
                ]
            );
            $form->displayTableRow(
                [
                    'Contor col ' . $form->generateNewTabLink('ultima intervenție', "$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?mode=" . PluginIserviceTicket::MODE_CLOSE . "&id={$printer->lastTicket()->getID()}"),
                    $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", $printer->tableData['contor_col_ui'], true),
                    'Valoare contract',
                    $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", $printer->tableData['monthly_fee_field'], true)
                ]
            );
            if (strtotime($printer->tableData['data_ui']) < strtotime('-14 days')) {
                $lastInterventionColor = 'color:red;';
            } elseif (strtotime($printer->tableData['data_ui']) < strtotime('-7 days')) {
                $lastInterventionColor = 'color:orange;';
            } else {
                $lastInterventionColor = '';
            }

            $form->displayTableRow(
                [
                    "Data " . $form->generateNewTabLink('ultima intervenție', "$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?mode=" . PluginIserviceTicket::MODE_CLOSE . "&id={$printer->lastTicket()->getID()}"),
                    $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", $printer->tableData['data_ui'], true, ['style' => $lastInterventionColor]),
                    'Tarif copie bk',
                    $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", $printer->tableData['copy_price_bk_field'], true)
                ]
            );
            $form->displayTableRow(
                [
                    '',
                    '',
                    'Tarif copie col',
                    $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, "", $printer->tableData['tarif_cop_col'], true)
                ]
            );
            $form->closeTable();
            echo "        </td></tr>";
        }

        echo "        </table>\n";
    }

    protected static function renderRouterSection(PluginIserviceHtml $form, array $items, array $invoiceData)
    {
        if (count($items['routers'] ?? []) < 1) {
            return;
        }

        echo "        <table class='tab_cadre wide' style='margin-top: 10px;'>\n";
        echo "            <tr>\n";
        echo "                <th colspan='2' style='font-weight: bold;'>\n";
        echo "                    Routere - Cod serviciu " . $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'codmat_router', $invoiceData['codmat_router']);
        echo "                </th>\n";
        echo "            </tr>\n";
        echo "            <tr>\n";
        echo "                <td>\n";
        echo "                    <table class='tab_cadre_fixe no-border wide' id='routers' table-layout='fixed'>\n";
        echo "                        <thead>\n";
        echo "                            <tr class='short' style='text-align: center;'>\n";
        echo "                                <th style='width: 35px'>Id</th>\n";
        echo "                                <th style='width: 400px'>Nume</th>\n";
        echo "                                <th style='width: 100px'>Locatie</th>\n";
        echo "                                <th style='width: 100px'>Cen. de cost</th>\n";
        echo "                                <th style='width: 100px'>Adr. de exp.</th>\n";
        echo "                                <th style='width: 70px'>Data exp.</th>\n";
        echo "                                <th style='width: 110px'>Fact. pana la</th>\n";
        echo "                                <th style='width: 135px'>Pret unitar in RON</th>\n";
        echo "                                <th style='width: 30px'>Curs</th>\n";
        echo "                                <th style='width: 30px'>Cant.</th>\n";
        echo "                                <th style='width: 30px'>Total</th>\n";
        echo "                                <th >Descriere</th></tr>\n";
        echo "                        </thead>\n";
        echo "                        <tbody>\n";
        foreach ($items['routers'] as $router) {
            echo "                        <tr class='calculates-total'>\n";
            echo "                            <td align='center'>{$router->tableData['id']}</td>\n";
            echo "                            <td><span class='name'>{$router->tableData['name']}</span></td>\n";
            echo "                            <td><span class='name'>{$router->tableData['location']}</span></td>\n";
            echo "                            <td><span class='name'>{$router->tableData['cost_center_field']}</span></td>\n";
            echo "                            <td><span class='name'>{$router->tableData['usage_address_field']}</span></td>\n";
            echo "                            <td align='center'>";
            $form->displayField(
                PluginIserviceHtml::FIELDTYPE_TEXT,
                "item[router][{$router->tableData['id']}][invoice_expiry_date_field]",
                $router->tableData['invoice_expiry_date_field'],
                true,
                [
                    'style' => 'width:70%',
                    'class' => 'invoice_expiry_date_field'
                ]
            );
            echo "                            </td>\n";
            echo "                            <td align='center'>\n";
            echo "                                <div class='aggressive datetime-field dropdown_wrapper data_fact_until'>\n";
            $form->displayField(
                PluginIserviceHtml::FIELDTYPE_DATE,
                "item[router][{$router->tableData['id']}][data_fact_until]",
                $router->tableData['data_fact_until'],
                false,
                ['clear_btn' => false]
            );
            echo "                                </div>\n";
            echo "                                <input type='hidden' class='data_fact_until_new' value='" . IserviceToolBox::addMonthToDate($router->tableData['invoice_expiry_date_field'], 1) . "'/>\n";
            echo "                                <input type='hidden' class='submit ult_tichet_effective_date_field' value='" . date("Y-m-d", strtotime($router->tableData['data_ult_fact'])) . "'/>\n";
            echo "                            </td>\n";
            echo "                            <td align='center'>\n";
            $form->displayField(
                PluginIserviceHtml::FIELDTYPE_TEXT,
                "item[router][{$router->tableData['id']}][pret_unitar]",
                $router->tableData['pret_unitar'],
                false,
                [
                    'style' => 'width:3em; text-align:right;',
                    'class' => 'calculate-product-part pret_unitar'
                ]
            );
            echo "                                <input type='button' class='submit switch-router-calculation-details' style='width: 45px' value='{$router->tableData['switch']}'/>\n";
            echo "                                <input type='hidden' class='switch-router-calculation-details-text' name='item[router][{$router->tableData['id']}][switch_text]' value='{$router->tableData['switch']}'/>\n";
            echo "                                <input type='button' class='submit switch-router-calculation-part-details' style='width: 45px' value='{$router->tableData['switch_part']}'/>\n";
            echo "                                <input type='hidden' class='switch-router-calculation-part-details-text' name='item[router][{$router->tableData['id']}][switch_part_text]' value='{$router->tableData['switch_part']}'/>\n";
            echo "                            </td>\n";
            echo "                            <td align='right'>\n";
            echo "                              {$router->tableData['invoice_rate']}";
            echo "                              <input type='hidden' class='calculate-product-part' value='{$router->tableData['invoice_rate']}'/>";
            echo "                            </td>\n";
            echo "                            <td align='center'>\n";
            $form->displayField(
                PluginIserviceHtml::FIELDTYPE_TEXT,
                "item[router][{$router->tableData['id']}][cantitate]",
                $router->tableData['cantitate'],
                true,
                [
                    'style' => 'width:70%;text-align:right;',
                    'class' => 'calculate-product-part cantitate'
                ]
            );
            echo "                            </td>\n";
            echo "                            <td align='right'>\n";
            echo "                                <span class='calculate-product total_price'>{$router->tableData['total']}</span>\n";
            echo "                                <input type='hidden' class='calculate-product-hidden total_price' name='item[router][{$router->tableData['id']}][total]' value='{$router->tableData['total']}'/>\n";
            echo "                            </td>\n";
            echo "                            <td align='center'>\n";
            $form->displayField(
                PluginIserviceHtml::FIELDTYPE_TEXT,
                "item[router][{$router->tableData['id']}][description]",
                $router->tableData['description'],
                true,
                [
                    'style' => 'width:90%',
                    'class' => 'description'
                ]
            );
            echo "                            </td>\n";
            echo "                        </tr>\n";
        }

        echo "                        </tbody>\n";
        echo "                    </table>\n";
        echo "                </td>\n";
        echo "            </tr>\n";
        echo "        </table>\n";
    }

    protected static function renderExportSection(PluginIserviceHtml $form, array $items, array $frontendData)
    {
        global $CFG_PLUGIN_ISERVICE;

        $exportResult   = $frontendData['exportResult'];
        $exportFileData = $frontendData['exportFileData'];

        /*
         * const observer = new IntersectionObserver(function (entries, observer) {
              entries.forEach((entry) => {
                if ( entry.isVisible ) {
                  console.log('????');
                }}
              });
            });

            observer.observe(document.querySelector('.my-element'));
         */

        echo "        <table class='wide'>";
        echo "            <tr>";
        echo "                <th colspan='2' style='font-weight: bold;'>Document</th>\n";
        echo "            </tr>";
        echo "            <tr><td>$frontendData[other_csv_line_warning]</td></tr>\n";
        echo "            <tr>\n";
        echo "                <td colspan='2'>\n";
        echo "                    <input type='submit' class='submit' name='refresh' value='Actualizare'/>&nbsp;&nbsp;&nbsp;\n";
        echo "                    <select onchange='$(\"[name=export_file_name_suffix]\").val($(this).val());$(\"[name=refresh]\").click();' style='width:480px;'>\n";
        foreach ($exportFileData['name_suffixes'] as $suffix) {
            echo "                  <option name='$suffix' " . ($suffix === $exportFileData['name_suffix'] ? 'selected' : '') . ">$suffix</option>\n";
        }

        echo "                    </select>";
        echo "                    <i class='clickable fa fa-trash' onclick='$(\"[name=delete]\").click();' style='color:red;'></i><input name='delete' type='submit' style='display: none;'/>";
        echo "                    <input class='submit" . (empty($frontendData['import_disabled_reason']) ? '' : ' disabled') . "' name='import' style='color:red;' title='" . ($frontendData['import_disabled_reason'] ?: 'ATENȚIE! Apăsând butonul ștergeți fișierele csv!') . "' type='submit' value='Importat în hMarfa' onclick='if ($(this).hasClass(\"disabled\")) { return false; }'/>";
        echo "                    <input class='submit" . (empty($frontendData['import_disabled_reason']) ? '' : ' disabled') . "' name='update' style='color:red;' title='" . ($frontendData['import_disabled_reason'] ?: '') . "' type='submit' value='Update facturare' onclick='if ($(this).hasClass(\"disabled\")) { return false; }'/>";
        echo "                    <a id='send_mail_2' class='vsubmit' href='mailto:{$frontendData['invoiceData']['email_for_invoices_field']}?subject={$frontendData['mailData']['subject']}&body={$frontendData['mailData']['body']}' title='Trimite email către: {$frontendData['invoiceData']['email_for_invoices_field']}'>Trimite email</a>";
        echo "                </td>\n";
        echo "            </tr>\n";
        echo "            <tr><td><br></td></tr>\n";
        echo "            <tr>\n";
        echo "                <td>\n";
        echo "                     <input class='submit" . (empty($frontendData['add_disabled_reason']) ? '' : ' disabled') . "' name='add' title='" . ($frontendData['add_disabled_reason'] ?? '') . "' type='submit' value='Adaugă in document' onclick='if ($(this).hasClass(\"disabled\")) { return false; }'/>\n";
        echo "                     <input name='overwrite' type='checkbox' /> nou &nbsp;&nbsp;&nbsp;\n";
        echo                      $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'exportfilepath', $exportFileData['path'], false, ['style' => 'width:15em;']);
        echo "                    <b>/S.$exportFileData[partner_name].</b>";
        echo "                    <input type='text' name='export_file_name_suffix' value='$exportFileData[name_suffix]' style='width:90px;' onchange='checkExportFileNameSuffix();'/>\n";
        echo "                    <b>.{$items['first']['supplier']->getID()}.csv</b>";
        echo "                </td>\n";
        echo "                <td style='text-align: right; width: 33%'>\n";
        $generate_ssx_ajaxcall_success_function = "function(message) {if (message!=\"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {alert(\"Fișierul S%2\$s generat cu succes\");}}";
        $url_encoded_exportFilePath             = urlencode($exportFileData['path']);
        $generate_ajaxcall                      = "ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/generate_ssx.php?path=%1\$s/&file_name=%2\$s\", \"\", $generate_ssx_ajaxcall_success_function);";
        $generate_ss_ajaxcall                   = sprintf($generate_ajaxcall, $url_encoded_exportFilePath, $exportFileData['csv_name']);
        echo "                    <a class='vsubmit' href='javascript:none;' onclick='$generate_ss_ajaxcall'>Generează fișier SS</a>\n";
        echo "                    &nbsp;&nbsp;Arată DAT&nbsp;&nbsp;</nbs><input name='show_dat' type='checkbox' onclick='$(\".base-dat\").toggle(this.checked); $(\".base-csv\").attr(\"colspan\", this.checked ? 1 : 2);' " . ($frontendData['invoiceData']['show_dat'] ? 'checked=\"checked\"' : '') . "/>\n";
        echo "                </td>\n";
        echo "            </tr>\n";
        echo "            <tr><td><br></td></tr>\n";
        echo "            <tr>\n";
        echo "                <td class='base-csv' colspan='" . ($frontendData['invoiceData']['show_dat'] ? 1 : 2) . "'>\n";
        echo "                    <textarea id='csv_text' rows=12 style='width:98%'>" . ($exportResult['errors']['csv'] ?? (file_exists($exportFileData['csv_full_path']) ? file_get_contents($exportFileData['csv_full_path']) : "")) . "</textarea>";
        echo "                </td>\n";
        echo "                <td class='base-dat'" . ($frontendData['invoiceData']['show_dat'] ? '' : " style='display: none;'") . ">\n";
        echo "                    <textarea id='csv_text' rows=12 style='width:98%'>" . ($exportResult['errors']['dat'] ?? (file_exists($exportFileData['dat_full_path']) ? file_get_contents($exportFileData['dat_full_path']) : "")) . "</textarea>";
        echo "                </td>\n";
        echo "            </tr>\n";
        echo "            <tr><td><br></td></tr>\n";
        echo "            <tr>\n";
        echo "                <td>\n";
        echo "                    Istoric: <select id='backup_year' name='backup_year' style='width: 55px;' onchange='refreshHistoryData();'></select> ";
        echo "                    <select id='backup_month' name='backup_month' style='width: 40px;' onchange='refreshHistoryData(true);'></select> ";
        echo "                    <select id='backup_name' name='backup_name' style='width: 377px;'></select> ";
        echo "                    <input class='submit' name='restore' type='submit' style='margin:1em 0;' value='Restabilire'>";
        echo "                </td>\n";
        echo "            </tr>\n";
        echo "        </table>";
        Html::closeForm();
        echo "<script>\n";
        echo "  $(\"#extended-chb\").click();$(\"#show-dat-chb\").click();";

        $backupData = [];
        echo "  var years = [];";
        echo "  var months = [];";
        echo "  var backup_data = [];\n";
        foreach (glob("$exportFileData[backup_path]/*.{$items['first']['supplier']->getID()}.*.S.csv") as $path) {
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

        echo "  jQuery(document).ready(function() {
                refreshHistoryData();});";
        echo "</script>\n";
    }

    protected static function numberFormat(float $number): string
    {
        return IserviceToolBox::numberFormat($number, 2, '.', '');
    }

}

<?php

// Imported from iService2, needs refactoring.
require "../inc/includes.php";

global $DB, $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
$images_source = "$CFG_PLUGIN_ISERVICE[root_doc]/images";

$ticket_id = filter_input(INPUT_GET, 'id');

if (!Session::haveRight('plugin_iservice_docgenerator', READ)) {
    Html::header(_t('Intervention report'), $_SERVER['PHP_SELF']);
    Html::displayRightError();
}

if (empty($ticket_id)) {
    Html::header(_t('Intervention report'), $_SERVER['PHP_SELF']);
    Html::displayErrorAndDie(_t('Ticket Id is missing!'));
}

$ticket = new PluginIserviceTicket();
if (!$ticket->getfromDB($ticket_id)) {
    echo "Invalid ticket id: $ticket_id";
    return false;
}

$printer = $ticket->getFirstPrinter();
if ($printer->isNewItem()) {
    echo "Ticket has no printer associated";
    return false;
}

$assigned_supplier = new Supplier();
$assigned_supplier->getFromDB($ticket->getSuppliers(Supplier_Ticket::ASSIGN)[0]['suppliers_id']);

$assigned_suppliers_customfields = new PluginFieldsSuppliersuppliercustomfield();
PluginIserviceDB::populateByItemsId($assigned_suppliers_customfields, $assigned_supplier->getID());

$requester_ids = array_column($ticket->getUsers(Ticket_User::REQUESTER), 'users_id');
$assigned_ids  = array_column($ticket->getUsers(Ticket_User::ASSIGN), 'users_id');

if (!Session::haveRight('ticket', Ticket::READALL)
    && !in_array($requester_ids, $_SESSION["glpiID"])
    && !in_array($_SESSION["glpiID"], $assigned_ids)
    && !(Session::haveRight("ticket", Ticket::READGROUP)
    && in_array($_SESSION["glpigroups"], $ticket->getGroups(Group_Ticket::REQUESTER)))
    && !(Session::haveRight("ticket", Ticket::READASSIGN)
    && in_array($_SESSION["glpigroups"], $ticket->getGroups(Group_Ticket::ASSIGN)))
    && !(Session::haveRight("plugin_iservice_ticket_assigned_printers", READ)
    && $printer->fields['users_id_tech'] == $_SESSION["glpiID"])
) {
    echo "You have no right!";
    return false;
}

// Find the data.
$model = $assigned_suppliers_customfields->fields['intervention_sheet_model_field'];
$cod   = $assigned_suppliers_customfields->fields['hmarfa_code_field'];

$printer_manufacturer = new Manufacturer();
$printer_manufacturer->getFromDB($printer->fields['manufacturers_id']);

$printer_user_tech = new User();
$printer_user_tech->getFromDB($printer->fields['users_id_tech']);

$assigned_user = new User();
if (!empty($assigned_ids[0])) {
    $assigned_user->getFromDB($assigned_ids[0]);
}

$contract = new Contract();
if (!PluginIserviceDB::populateByQuery(
    $contract,
    sprintf("where ID=(select max(contracts_id) from glpi_contracts_items where items_id = %s) limit 1", $printer->getID())
)
) {
    echo "No contract found for printer";
    return false;
}

$contract_customfields = new PluginFieldsContractcontractcustomfield();
if (!PluginIserviceDB::populateByItemsId($contract_customfields, $contract->getID())) {
    echo "No contract customfields found for printer";
    return false;
}

$curs_factura = ($contract_customfields->fields['currency_field'] > 0) ? "EUR" : "RON";

$facturi_neachitate_result = $DB->query(
    "select DATEDIFF(CURDATE(),dscad) AS zile from hmarfa_facturi where codbenef='$cod' AND tip LIKE 'TFA%' AND (valinc-valpla)>0 order by dscad ASC"
);

if (empty($facturi_neachitate_result)) {
    echo "No invoices found for printer";
    return false;
}

$numberOfUnpaidInvoices = $facturi_neachitate_result->num_rows;
$unpaidInvoicesArray    = $facturi_neachitate_result->fetch_assoc();
$invoicesDelayDays      = $unpaidInvoicesArray['zile'] ?? '';

$facturi_neachitate_result2 = $DB->query("select ROUND(SUM(valinc-valpla),2) AS suma from hmarfa_facturi where codbenef='$cod' AND tip LIKE 'TFA%' AND (valinc-valpla)>0;");
$suma_facturi_neachitate    = $facturi_neachitate_result2->fetch_assoc()['suma'];

$query_tickets = sprintf(
    "
                    select t.id
                      , t.name
                      , t.date
                      , t.effective_date_field
                      , t.total2_black_field
                      , t.total2_color_field
                      , concat(au.firstname, ' ', au.realname) assigned
                      , concat(ou.firstname, ' ', ou.realname) observer
                      , group_concat(concat(tf.content, '&nbsp;')) obs
                    from glpi_plugin_iservice_tickets t
                    join glpi_items_tickets it on it.tickets_id = t.id and it.itemtype = 'Printer' and it.items_id = %s
                    left join glpi_tickets_users ta on ta.tickets_id = t.id and ta.type = " . Ticket_User::ASSIGN . "
                    left join glpi_users au on au.id = ta.users_id
                    left join glpi_tickets_users `to` on `to`.tickets_id = t.id and `to`.type = " . Ticket_User::OBSERVER . "
                    left join glpi_users ou on ou.id = `to`.users_id
                    left join glpi_itilfollowups tf on tf.items_id = t.id AND tf.itemtype='Ticket' AND tf.is_private = 0
                    where t.is_deleted = 0 and t.id <= $ticket_id and t.itilcategories_id != " . PluginIserviceTicket::getItilCategoryId('Citire emaintenance') . "
                    group by t.id, t.effective_date_field
                    order by t.effective_date_field desc, t.id desc limit 3", $printer->getID()
);
$ticket_result = $DB->query($query_tickets);
$ticket_row    = $ticket_result->fetch_assoc();


$cartridges = PluginIserviceCartridgeItem::getForPrinterAtSupplier($printer->getID(), $assigned_supplier->getID());

$last_cartridge = new PluginIserviceCartridge();
PluginIserviceDB::populateByQuery(
    $last_cartridge,
    "WHERE suppliers_id_field = {$assigned_supplier->getID()} AND printers_id = {$printer->getID()} AND NOT date_use IS NULL ORDER BY date_use DESC LIMIT 1"
);
?>
<html>
    <head>
        <title>Ticket report</title>
        <style type="text/css">
            body {
                font-family: Helvetica, Arial;
                font-size: 11px;
                vertical-align: top;
            }
            table {
                width: 100%;
                font-size: inherit;
                border-spacing: 0px;
            }
            table.outer {
                border: 0px;
            }
            thead tr {
                height: 0px;
            }
            tr {
                line-height: 10px;
                vertical-align: top;
            }
            td.inner {
                border: 1px;
                border-style: solid;
                border-spacing: 0px;
            }
            td.inner.right {
                border-left: 0px;
            }
            td.inner.left {
                border-right: 0px;
            }
            td.outer {
                border: 3px;
                border-style: solid;
                border-spacing: 0px;
            }
            td.margin {
                border-top: 0px;
                border-bottom: 0px;
                border-left: 3px;
                border-right: 3px;
                border-style: solid;
                border-spacing: 0px;
                padding-bottom: 3px;
            }
            .table-header {
                text-align: center;
            }
            .row-placeholder {
                height: 2px;
            }
            .group {
                text-align: center;
                font-style: italic;
            }
            .center {
                text-align: center;
            }
            .break-word {
                word-wrap: break-word;
            }
        </style>
    </head>
    <body>
        <div style="margin: 0px auto; width: 708px;">
            <table class="outer">
                <tr>
                    <td class="outer"><img src="<?php echo $images_source?>/report_top<?php echo $model?>.png" style="float:left; width:708px;"></td>
                </tr>
                <tr><td class="margin"><table style="border:0px; padding: 9px;">
                            <!-- determine column widths -->
                            <thead>
                                <tr>
                                    <td style="width: 110px;"></td>
                                    <td style="width: 140px;"></td>
                                    <td style="width: 100px;"></td>
                                    <td style="width: 140px;"></td>
                                    <td style="width: 100px;"></td>
                                    <td style="width: 100px;"></td>
                                </tr>
                            </thead>

                            <!-- general info header -->
                            <tr class="group">
                                <td colspan=2>Partener</td>
                                <td colspan=2>Aparat</td>
                                <td colspan=2>Contract</td>
                            </tr>
                            <!-- general info data -->
                            <tr>
                                <td>Denumire societate:</td>
                                <td><?php echo $assigned_supplier->fields['name']; ?></td>
                                <td>Denumire aparat:</td>
                                <td><?php echo $printer->fields['original_name']; ?></td>
                                <td>Tip contract:</td>
                                <td><?php echo Dropdown::getDropdownName('glpi_contracttypes', $contract->fields['contracttypes_id']); ?></td>
                            </tr>
                            <tr>
                                <td>Nr.reg.com:</td>
                                <td><?php echo $assigned_suppliers_customfields->fields['crn_field']; ?></td>
                                <td>Numar evidenta:</td>
                                <td><?php echo $printer->fields['otherserial']; ?></td>
                                <td>Numar contract:</td>
                                <td><?php echo $contract->fields['num']; ?></td>
                            </tr>
                            <tr>
                                <td>Cod Fiscal:</td>
                                <td><?php echo $assigned_suppliers_customfields->fields['uic_field']; ?></td>
                                <td>Serie aparat:</td>
                                <td><?php echo $printer->fields['serial']; ?></td>
                                <td>Tarif lunar:</td>
                                <td><?php echo $contract_customfields->fields['monthly_fee_field'] . " $curs_factura"; ?></td>
                            </tr>
                            <tr>
                                <td rowspan=2>Adresa:</td>
                                <td rowspan=2><?php echo $assigned_supplier->fields['address']; ?></td>
                                <td>Producator:</td>
                                <td><?php echo $printer_manufacturer->fields['name']; ?></td>
                                <td>Tarif copii bk.:</td>
                                <td><?php echo $contract_customfields->fields['copy_price_bk_field'] . " $curs_factura"; ?></td>
                            </tr>
                            <tr>
                                <td>Model:</td>
                                <td><?php echo Dropdown::getDropdownName('glpi_printermodels', $printer->fields['printermodels_id']); ?></td>
                                <td>Tarif copii color:</td>
                                <td><?php echo $contract_customfields->fields['copy_price_col_field'] . " $curs_factura"; ?></td>
                            </tr>
                            <tr>
                                <td>Orasul:</td>
                                <td><?php echo $assigned_supplier->fields['town']; ?></td>
                                <td>Adresa exploatare:</td>
                                <td><?php echo $printer->customfields->fields['usage_address_field'] ?></td>
                                <td>Copii bk. incluse:</td>
                                <td><?php echo $contract_customfields->fields['included_copies_bk_field']; ?></td>
                            </tr>
                            <tr>
                                <td>Contul:</td>
                                <td></td>
                                <td>Contact:</td>
                                <td><?php echo $ticket->customfields->fields['contact_name_field']; ?></td>
                                <td>Copii col. incluse:</td>
                                <td><?php echo $contract_customfields->fields['included_copies_col_field']; ?></td>
                            </tr>
                            <tr>
                                <td>Banca:</td>
                                <td></td>
                                <td>Numar contact:</td>
                                <td><?php echo $ticket->customfields->fields['contact_phone_field']; ?></td>
                                <td>Val. copii incluse:</td>
                                <td><?php echo $contract_customfields->fields['included_copy_value_field']; ?></td>
                            </tr>
                            <tr>
                                <td>Telefon:</td>
                                <td><?php echo $assigned_supplier->fields['phonenumber']; ?></td>
                                <td>Responsabil:</td>
                                <td><?php echo $printer_user_tech->fields['realname'] . " " . $printer_user_tech->fields['firstname']; ?></td>
                                <td>Perioada facturata:</td>
                                <td><?php echo date('Y-m-d', strtotime($printer->customfields->fields['invoice_expiry_date_field'])); ?></td>
                            </tr>
                            <tr>
                                <td></td>
                                <td></td>
                                <td>E-maintenance:</td>
                                <td><?php echo $printer->customfields->fields['em_field'] ? 'Da' : 'Nu' ?></td>
                                <td></td>
                                <td></td>
                            </tr>

                            <tr class="row-placeholder"></tr>

                            <!-- invoice info header -->
                            <tr class="group">
                                <td>Info facturi:</td>
                                <td colspan=5></td>
                            </tr>
                            <!-- invoice info -->
                            <tr>
                                <td class="inner left">Nr. facturi neachitate:</td>
                                <td class="inner left"><?php echo $numberOfUnpaidInvoices; ?></td>
                                <td class="inner left">Suma totala:</td>
                                <td class="inner left"><?php echo empty($suma_facturi_neachitate) ? "" : "$suma_facturi_neachitate RON"; ?></td>
                                <td class="inner left">Intarziere:</td>
                                <td class="inner"><?php echo empty($invoicesDelayDays) ? "" : "$invoicesDelayDays zile"; ?></td>
                            </tr>

                            <tr class="row-placeholder"></tr>

                            <!-- cartridge info header -->
                            <tr class="group">
                                <td>Cartuse:</td>
                                <td colspan=5></td>
                            </tr>
                            <!-- cartridge info -->
                            <tr>
                                <td colspan=6>
                                    <div>
                                        <table style="border:1px solid; table-layout: fixed; width: 100%; word-wrap: break-word;">
                                            <?php
                                            $cartridge_data = [];
                                            foreach ($cartridges as $cartridge) {
                                                if (array_search($cartridge['ref'], array_column($cartridge_data, 'tip')) === false) {
                                                    $cartridge_data[]['tip'] = $cartridge['ref'];
                                                }

                                                $index = array_search($cartridge['ref'], array_column($cartridge_data, 'tip'));
                                                if (empty($cartridge['date_use'])) {
                                                    $cartridge_data[$index]['custodie'] = $cartridge['cpt'];
                                                } else {
                                                    $cartridge_data[$index]['instalat'] = $cartridge['cpt'];
                                                    $counters                           = $cartridge['pages_use_field'];
                                                    if ($printer->isColor()) {
                                                        $counters .= " (n)<br>$cartridge[pages_color_use_field] (c)";
                                                    }

                                                    if ($printer->isPlotter()) {
                                                        $counters .= " (cerneală consumată)<br>$cartridge[pages_color_use_field] (suprafața printată)";
                                                    }

                                                    $cartridge_data[$index]['counters'] = $counters;
                                                    $cartridge_data[$index]['date_use'] = $cartridge['date_use'];
                                                }

                                                foreach (['custodie', 'instalat', 'counters', 'date_use'] as $key) {
                                                    if (!isset($cartridge_data[$index][$key])) {
                                                        $cartridge_data[$index][$key] = '';
                                                    }
                                                }
                                            }

                                            echo "<thead><tr class='table-header'>";
                                            echo "<td class='inner' width='110px'>Tip cartus</td>";
                                            echo "<td class='inner'>In custodie</td>";
                                            echo "<td class='inner'>Instalat</td>";
                                            echo "<td class='inner' width='60px'>Contor la instalare</td>";
                                            echo "<td class='inner' width='65px'>Data instalare</td>";
                                            echo "<td class='inner' width='110px'>Tip cartus</td>";
                                            echo "<td class='inner'>In custodie</td>";
                                            echo "<td class='inner'>Instalat</td>";
                                            echo "<td class='inner' width='60px'>Contor la instalare</td>";
                                            echo "<td class='inner' width='65px'>Data instalare</td>";
                                            echo "</tr></thead>";
                                            echo "<tbody>";
                                            $cartridge_count     = count($cartridge_data) / 2;
                                            $cartridge_count_odd = count($cartridge_data) % 2;
                                            $i                   = -1;
                                            while (++$i < $cartridge_count) {
                                                echo "<tr class='inner'>";
                                                echo "<td class='inner center'>{$cartridge_data[$i]['tip']}</td>";
                                                echo "<td class='inner center'>{$cartridge_data[$i]['custodie']}</td>";
                                                echo "<td class='inner center'>{$cartridge_data[$i]['instalat']}</td>";
                                                echo "<td class='inner center'>{$cartridge_data[$i]['counters']}</td>";
                                                echo "<td class='inner center'>{$cartridge_data[$i]['date_use']}</td>";
                                                $j = $i + $cartridge_count + $cartridge_count_odd;
                                                if (isset($cartridge_data[$j]['tip'])) {
                                                    echo "<td class='inner center'>{$cartridge_data[$j]['tip']}</td>";
                                                    echo "<td class='inner center'>{$cartridge_data[$j]['custodie']}</td>";
                                                    echo "<td class='inner center'>{$cartridge_data[$j]['instalat']}</td>";
                                                    echo "<td class='inner center'>{$cartridge_data[$j]['counters']}</td>";
                                                    echo "<td class='inner center'>{$cartridge_data[$j]['date_use']}</td>";
                                                } else {
                                                    for ($k = 0; $k < 5; $k++) {
                                                        echo "<td class='inner center'></td>";
                                                    }
                                                }

                                                echo "</tr>";
                                            }

                                            echo "</tbody>";
                                            ?>
                                        </table>
                                    </div>
                                </td>
                            </tr>

                            <tr class="row-placeholder"></tr>

                            <!-- last invoice info -->
                            <tr>
                                <td class="group">Data ultima factura:</td>
                                <td colspan="2"><?php echo $printer->customfields->fields['invoice_date_field']; ?></td>
                                <td>Copii bk. facturate: <?php echo $printer->customfields->fields['invoiced_total_black_field']; ?></td>
                                <td style="text-align: right">Copii col. facturate:</td>
                                <td><?php echo $printer->customfields->fields['invoiced_total_color_field']; ?></td>
                            </tr>

                            <tr class="row-placeholder"></tr>

                            <!-- current intervention info -->
                            <tr style="border-collapse: collapse; border: 1px solid; height: 30px; vertical-align: middle;">
                                <td class="inner left">Interventie curenta:</td>
                                <td class="inner left" colspan="2"><?php echo "Tichet nr. $ticket_id din $ticket_row[date]"; ?></td>
                                <td class="inner left">Copii bk.:</td>
                                <td class="inner left" style="text-align: right">Copii color:</td>
                                <td class="inner right"></td>
                            </tr>

                            <!-- intervention history header -->
                            <tr class="group">
                                <td>Istoric interventii:</td>
                                <td colspan=5></td>
                            </tr>
                            <!-- intervention history -->
                            <tr>
                                <td colspan=6><div>
                                        <table id="tbl_intervention_history" style="border:1px solid;">
                                            <thead>
                                                <tr class="table-header">
                                                    <td class="inner" style="width: 30px;">Nr.</td>
                                                    <td class="inner" style="width: 64px;">Data efectiva</td>
                                                    <td class="inner" style="width: 51px;">Copii black</td>
                                                    <td class="inner" style="width: 50px;">Copii color</td>
                                                    <td class="inner">Atribuit la / Observator</td>
                                                    <td class="inner">Titlu</td>
                                                    <td class="inner">Observatii</td>
                                                </tr>
                                            </thead>
                                            <?php
                                            $row = 0;
                                            do {
                                                    $row++;
                                                $assigned_observer = $ticket_row['assigned'];
                                                if (!empty($assigned_observer) && !empty($ticket_row['observer'])) {
                                                    $assigned_observer .= ' / ';
                                                }

                                                $assigned_observer .= $ticket_row['observer'];
                                                echo"
                                                    <tr>
                                                        <td class='inner'>$ticket_row[id]</td>
                                                        <td class='inner'>" . date('Y-m-d', strtotime($ticket_row['effective_date_field'])) . "</td>
                                                        <td class='inner'>" . ($row === 1 ? '' : $ticket_row['total2_black_field']) . "</td>
                                                        <td class='inner'>" . ($row === 1 ? '' : $ticket_row['total2_color_field']) . "</td>
                                                        <td class='inner'>$assigned_observer</td>
                                                        <td class='inner'>$ticket_row[name]</td>
                                                        <td class='inner'>$ticket_row[obs]</td>
                                                    </tr>";
                                            } while (($ticket_row = $ticket_result->fetch_array()) != false);
                                            ?>
                                        </table>
                                    </div></td>
                            </tr>
                        </table></td></tr>
                <tr><td class="outer" style="text-align: center;"><img src="<?php echo $images_source?>/report_bottom.png" style="width: 708px;"></td></tr>
            </table>
        </div>
        <!--
        <script type="text/javascript">
            var table = document.getElementById("tbl_intervention_history");
            while (table.offsetHeight > 100) {
                table.deleteRow(table.rows.length - 1);
            }
        </script>
         -->
    </body>
</html>

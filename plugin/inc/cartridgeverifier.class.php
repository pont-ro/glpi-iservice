<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Views\Views;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

class PluginIserviceCartridgeVerifier extends CommonDBTM
{

    public static function getTable($classname = null): string
    {
        if (empty($classname)) {
            $classname = 'CartridgeVerifier';
        }

        return parent::getTable($classname);
    }

    public static function getTypeName($nb = 0): string
    {
        return _tn('Cartridge verifier', 'Cartridge verifiers', $nb);
    }

    public static function cronInfo($name): ?array
    {
        switch ($name) {
        case 'mailCartridgeVerify' :
            return [
                'description' => 'Verifica starea cartuselor si trimite email',
                'parameter'   => __('Number of emails to retrieve')
            ];
        }

        return null;
    }

    /**
     * Cron action on mailCartridgeVerify
     *
     * @param $task
     *
     * @return 1 : done with success
     * */
    public static function cronMailCartridgeVerify($task): int
    {
        if (empty(PluginIserviceConfig::getConfigValue('enabled_crons.mailCartridgeVerify'))) {
            $task->log("mailCartridgeVerify is disabled by configuration.\n");
            return -2;
        }

        $comment_parts = explode("\n", $task->fields['comment'], 2);
        $target_email  = $comment_parts[0];

        if (empty($target_email)) {
            $task->log("Invalid email address: $target_email\n");
            return -1;
        }

        self::verifyCartridges($task, $target_email);
        self::verifyPrinters($task, $target_email);

        return 1; // Done.
    }

    public static function getEmailMessageFirstLines($tech_id = null): string
    {
        if (!empty($tech_id)) {
            $tech_id = "&printercounters20[tech_id]=" . $tech_id;
        } else {
            $tech_id = '';
        }

        $siteUrl = PluginIserviceConfig::getConfigValue('site_url');
        $html    = "Vezi lista completa a aparatelor cu consumabile goale
                <a href='$siteUrl/plugins/iservice/front/views.php?view=PrinterCounters&printercounters0[order_by]=estimate_percentages&printercounters0[order_dir]=ASC$tech_id' target='_blank'>aici V2</a>
                 - 
                <a href='$siteUrl/plugins/iservice/front/views.php?view=PrinterCountersV3&printercountersv30[order_by]=min_days_to_visit&printercountersv30[order_dir]=ASC$tech_id' target='_blank'>aici V3</a>
                <br><br>Următoarele aparate au consumabile goale:<br>";

        $html .= "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        $html .= "
        <thead>
            <tr>
                <th>" . _t('Supplier') . "</th>
                <th>" . _t('Printer model') . "</th>
                <th>" . _t('Usage address') . "</th>
                <th>" . _t('Serial number') . "</th>
                <th>" . _t('Average number bk') . "</th>
                <th>" . _t('Average number color') . "</th>
                <th>" . _t('Next delivery (days)') . "</th>
            </tr>
        </thead>";

        return $html;
    }

    public static function getEmailMessageItemForCartridge($cartridge): string
    {
        $siteUrl = PluginIserviceConfig::getConfigValue('site_url');

        return "<tr>
            <td>$cartridge[supplier_name]</td>
            <td><a href='$siteUrl/plugins/iservice/front/printer.form.php?id=$cartridge[printer_id]'>$cartridge[printer_model_name]</a></td>
            <td>$cartridge[usage_address_field]</td>
            <td><a href='$siteUrl/plugins/iservice/front/printer.form.php?id=$cartridge[printer_id]'>$cartridge[printer_serial_number]</a></td>
            <td>$cartridge[dba]</td>
            <td>$cartridge[dca]</td>
            <td>" . intval($cartridge['min_days_to_visit']) . "</td>
        </tr>";
    }

    public static function verifyCartridges($task, $target_email, $regenerateCacheTables = true, $cacheTableVersion = 2, $debugMode = false): void
    {
        if (!$debugMode) {
            $task->log("Verify cartridges\n");
        }

        if ($regenerateCacheTables) {
            Views::getView('PrinterCounters')->refreshCachedData();
            Views::getView('PrinterCountersV3')->refreshCachedData();
        }

        $cacheTableName = $cacheTableVersion === 2 ? "glpi_plugin_iservice_cachetable_printercounters" : "glpi_plugin_iservice_cachetable_printercountersv3";

        $blackWhitePrinterType = IserviceToolBox::getIdentifierByAttribute('PrinterType', 'alb-negru');
        $colorPrinterType      = IserviceToolBox::getIdentifierByAttribute('PrinterType', 'color');

        $cartridges = PluginIserviceDB::getQueryResult(
            "
            SELECT cpc.*, ue.email as tech_email, pm.name as printer_model_name, p.serial as printer_serial_number
            FROM $cacheTableName cpc
            JOIN glpi_printers p ON p.id = cpc.printer_id
            JOIN glpi_printermodels pm ON pm.id = p.printermodels_id
            JOIN glpi_useremails ue ON ue.users_id = cpc.tech_id
            WHERE cpc.cm_field = 1
              AND cpc.printer_types_id in ($blackWhitePrinterType, $colorPrinterType)
              AND cpc.printer_states_id in (SELECT id FROM glpi_states WHERE name like 'COI%' OR name like 'COF%' OR name like 'Pro%')
              AND cpc.min_estimate_percentage <= 0
              AND cpc.consumable_type = 'cartridge'
              AND cpc.min_days_to_visit <= 20
             ORDER BY cpc.min_days_to_visit, cpc.usage_address_field ASC
            "
        );

        if (empty($cartridges)) {
            if (!$debugMode) {
                $task->log("No cartridges returned\n");
            }

            return; // Nothing to do.
        }

        $email_messages                = [];
        $email_messages[$target_email] = self::getEmailMessageFirstLines();

        foreach ($cartridges as $cartridge) {
            $email_messages[$target_email] .= self::getEmailMessageItemForCartridge($cartridge);

            if (!empty($cartridge['tech_email'])) {
                if (!isset($email_messages[$cartridge['tech_email']])) {
                    $email_messages[$cartridge['tech_email']] = self::getEmailMessageFirstLines($cartridge['tech_id']);
                }

                $email_messages[$cartridge['tech_email']] .= self::getEmailMessageItemForCartridge($cartridge);
            }
        }

        $task->addVolume(count($cartridges));
        if (!$debugMode) {
            $task->log(count($cartridges) . " printers have empty consumables.\n");
        }

        foreach ($email_messages as $target_email => $email_message) {
            if (!$debugMode) {
                self::sendMail('Imprimante cu consumabile goale', $target_email, $email_message . "</table>", $task);
            } else {
                echo "To: $target_email\n";
                echo $email_message . "</table>\n\n";
            }
        }
    }

    public static function verifyPrinters($task, $target_email): void
    {
        global $CFG_PLUGIN_ISERVICE;
        $urlBase = PluginIserviceConfig::getConfigValue('url_base');

        $task->log("Verify printers in CM it they have installed cartridges\n");

        $printers = PluginIserviceDB::getQueryResult(
            "
            select 
                p.id pid
              , p.name printer_name
              , s.id sid
              , s.name supplier_name
            from glpi_printers p
            join glpi_states st on st.id = p.states_id
            join glpi_printertypes pt on pt.id = p.printertypes_id
            join glpi_infocoms ic on ic.items_id = p.id and ic.itemtype = 'Printer' and ic.suppliers_id != " . IserviceToolBox::getExpertLineId() . "
            join glpi_suppliers s on s.id = ic.suppliers_id
            join glpi_plugin_fields_suppliersuppliercustomfields scf on scf.cm_field = 1 and scf.items_id = ic.suppliers_id and scf.itemtype = 'Supplier'
            left join glpi_cartridges c on c.printers_id = p.id
            where p.is_deleted = 0
              and (st.name like 'pro%' or st.name like 'co%')
              and pt.name in ('alb-negru', 'color', 'plotter')
              and c.id is null
            order by supplier_name
            ",
            'pid'
        );

        if (empty($printers)) {
            return; // Nothing to do.
        }

        $email_message = "<b>Următorii parteneri au imprimante care nu au cartușe instalate</b>:<ul>";

        $supplier_id = 0;
        foreach ($printers as $printer) {
            if ($supplier_id != $printer['sid']) {
                if ($supplier_id != 0) {
                    $email_message .= "</ul></li>";
                }

                $supplier_id    = $printer['sid'];
                $email_message .= "<li><a href='$urlBase$CFG_PLUGIN_ISERVICE[root_doc]/front/view.php?view=printers&printers0[supplier_name]=$printer[supplier_name]'>$printer[supplier_name]</a><ul>";
            }

            $email_message .= "<li><a href='$urlBase$CFG_PLUGIN_ISERVICE[root_doc]/front/printer.form.php?id=$printer[pid]'>$printer[printer_name] ($printer[pid])</a></li>";
        }

        $task->addVolume(count($printers));
        $task->log(count($printers) . " printers are in CM and have no cartridges installed.\n");

        self::sendMail('Imprimante in CM fara cartuse instalate', $target_email, $email_message . '</ul>', $task);
    }

    public static function sendMail($subject, $target_email, $email_message, $task): void
    {
        global $CFG_GLPI;
        $mmail = new GLPIMailer();
        $mmail->AddCustomHeader("Auto-Submitted: auto-generated");
        $mmail->AddCustomHeader("X-Auto-Response-Suppress: OOF, DR, NDR, RN, NRN");
        $mmail->SetFrom($CFG_GLPI["admin_email"], $CFG_GLPI["admin_email_name"], false);
        $mmail->AddAddress($target_email);
        $mmail->Subject = $subject;
        $mmail->msgHTML($email_message);
        if ($mmail->send()) {
            $task->log("Email sent to $target_email.\n");
        } else {
            $task->log("Error sending email to $target_email.\n");
        }
    }

}

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
        return "Vezi lista completa a aparatelor cu consumabile goale 
                <a href='$siteUrl/plugins/iservice/front/view.php?view=printercounters2&printercounters20[order_by]=estimate_percentages&printercounters20[order_dir]=ASC$tech_id'>aici</a>
                <br><br>Următoarele aparate au consumabile goale:<br><ul>";
    }

    public static function getEmailMessageItemForCartridge($cartridge): string
    {
        $siteUrl = PluginIserviceConfig::getConfigValue('site_url');
        return "<li>
                    Aparat: $cartridge[printer_name] - $cartridge[printer_model_name] - <a href='$siteUrl/plugins/iservice/front/ticket.form.php?mode=1&items_id[Printer][0]=$cartridge[printer_id]&_suppliers_id_assign=$cartridge[supplier_id]&_users_id_assign=$cartridge[tech_id]'>crează tichet nou</a><br>
                    Partener: $cartridge[supplier_name]<br>
                    Responsabil: $cartridge[tech_name]<br>
                    Centru de cost: $cartridge[costcenter]<br>
                    Urm. livrare în: " . intval($cartridge['min_days_to_visit']) . "<br>
                    Consumabile instalate:<br>$cartridge[estimate_percentages]<br>
                    Consumabil disponibil pentru:<br>$cartridge[days_to_visits]<br>
                    Stoc  consumabile bucăți/ Număr de aparate compatibile:<br>$cartridge[stocks]<br>
                    Numar mediu bk: $cartridge[dba]<br>
                    Numar mediu color: $cartridge[dca]<br>
               </li><br>";
    }

    public static function verifyCartridges($task, $target_email): void
    {
        Views::getView('PrinterCounters')->refreshCachedData();
        Views::getView('PrinterCountersV3')->refreshCachedData();

        $task->log("Verify cartridges\n");

        $blackWhitePrinterType = IserviceToolBox::getIdentifierByAttribute('PrinterType', 'alb-negru');
        $colorPrinterType      = IserviceToolBox::getIdentifierByAttribute('PrinterType', 'color');

        $cartridges = PluginIserviceDB::getQueryResult(
            "
            SELECT cpc.*, ue.email as tech_email, pm.name as printer_model_name FROM glpi_plugin_iservice_cachetable_printercounters cpc
            JOIN glpi_printers p ON p.id = cpc.printer_id
            JOIN glpi_printermodels pm ON pm.id = p.printermodels_id
            JOIN glpi_useremails ue ON ue.users_id = cpc.tech_id
            WHERE cpc.cm_field = 1
              AND cpc.printer_types_id in ($blackWhitePrinterType, $colorPrinterType)
              AND cpc.printer_states_id in (SELECT id FROM glpi_states WHERE name like 'COI%' OR name like 'COF%' OR name like 'Pro%')
              AND cpc.min_estimate_percentage <= 0
              AND cpc.consumable_type = 'cartridge'
             ORDER BY cpc.tech_name, cpc.min_estimate_percentage ASC
            "
        );

        if (empty($cartridges)) {
            $task->log("No cartridges returned\n");
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
        $task->log(count($cartridges) . " printers have empty consumables.\n");

        foreach ($email_messages as $target_email => $email_message) {
            self::sendMail('Imprimante cu consumabile goale', $target_email, $email_message . "</ul>", $task);
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

<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Views\Views;

class PluginIserviceCartridgeVerifier extends CommonDBTM
{

    static function getTable($classname = null)
    {
        if (empty($classname)) {
            $classname = 'CartridgeVerifier';
        }

        return parent::getTable($classname);
    }

    static function getTypeName($nb = 0)
    {
        return _n('Cartridge verifier', 'Cartridge verifiers', $nb, 'iservice');
    }

    static function cronInfo($name)
    {

        switch ($name) {
        case 'mailCartridgeVerify' :
            return [
                'description' => 'Verifica starea cartuselor si trimite email',
                'parameter'   => __('Number of emails to retrieve')
            ];
        }
    }

    /**
     * Cron action on mailCartridgeVerify
     *
     * @param $task
     *
     * @return 1 : done with success
     * */
    static function cronMailCartridgeVerify($task)
    {
        global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;

        if (empty($CFG_PLUGIN_ISERVICE['enabled_crons']['mailCartridgeVerify'])) {
            $task->log("Cartridge verification is disabled by configuration.\n");
            return -2;
        }

        Views::getView('PrinterCounters')->refreshCachedData();

        $task->log("Virify cartridges\n");

        $cartridges = PluginIserviceDB::getQueryResult(
            "
        SELECT cpc.*, ue.email as tech_email, pm.name as printer_model_name FROM glpi_plugin_iservice_cachetable_printercounters2 cpc
        JOIN glpi_printers p ON p.id = cpc.printer_id
        JOIN glpi_printermodels pm ON pm.id = p.printermodels_id
        JOIN glpi_useremails ue ON ue.users_id = cpc.tech_id
        WHERE cpc.cartridge_management = 1
          AND cpc.printer_types_id in (3, 4)
          AND cpc.printer_states_id in (SELECT id FROM glpi_states WHERE name like 'COI%' OR name like 'COF%' OR name like 'Pro%')
          AND cpc.min_estimate_percentage <= 0
         ORDER BY cpc.tech_name, cpc.min_estimate_percentage ASC
        "
        );

        if (empty($cartridges)) {
            return 0; // Nothing to do.
        }

        $email_messages = [];

        $comment_parts = explode("\n", $task->fields['comment'], 2);
        $target_email  = $comment_parts[0];

        if (empty($target_email)) {
            $task->log("Invalid email address: $target_email\n");
            return -1;
        }

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
        $task->log(count($cartridges) . " printers had empty consumables.\n");

        foreach ($email_messages as $target_email => $email_message) {
            $email_message = $email_message . "</ul>";
            $mmail         = new GLPIMailer();
            $mmail->AddCustomHeader("Auto-Submitted: auto-generated");
            $mmail->AddCustomHeader("X-Auto-Response-Suppress: OOF, DR, NDR, RN, NRN");
            $mmail->SetFrom($CFG_GLPI["admin_email"], $CFG_GLPI["admin_email_name"], false);
            $mmail->AddAddress($target_email);
            $mmail->Subject = "Imprimante cu consumabile goale";
            $mmail->msgHTML($email_message);
            if ($mmail->send()) {
                $task->log("Email sent to $target_email.\n");
            } else {
                $task->log("Error sending email to $target_email.\n");
            }
        }

        return 1; // Done
    }

    static function getEmailMessageFirstLines($tech_id = null)
    {
        if (!empty($tech_id)) {
            $tech_id = "&printercounters20[tech_id]=" . $tech_id;
        } else {
            $tech_id = '';
        }

        return "Vezi lista completa a aparatelor cu consumabile goale 
                <a href='http://iservice2.expertline-magazin.ro/plugins/iservice/front/view.php?view=printercounters2&printercounters20[order_by]=estimate_percentages&printercounters20[order_dir]=ASC$tech_id'>aici</a>
                <br><br>Următoarele aparate au consumabile goale:<br><ul>";
    }

    static function getEmailMessageItemForCartridge($cartridge)
    {
        return "<li>
                    Aparat: $cartridge[printer_name] - $cartridge[printer_model_name] - <a href='http://iservice2.expertline-magazin.ro/plugins/iservice/front/ticket.form.php?mode=1&items_id[Printer][0]=$cartridge[printer_id]&_suppliers_id_assign=$cartridge[supplier_id]&_users_id_assign=$cartridge[tech_id]'>crează tichet nou</a><br>
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

}

<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceStockVerifier extends CommonDBTM
{

    public static function getTable($classname = null): string
    {
        if (empty($classname)) {
            $classname = 'StockVerifier';
        }

        return parent::getTable($classname);
    }

    public static function getTypeName($nb = 0): string
    {
        return _n('Stock verifier', 'Stock verifiers', $nb, 'iservice');
    }

    public static function cronInfo($name): ?array
    {

        switch ($name) {
        case 'mailStockVerify' :
            return [
                'description' => 'Verifica stoc si trimite email',
                'parameter'   => __('Number of emails to retrieve')
            ];
        }

        return null;
    }

    /**
     * Cron action on mailStockVerify
     *
     * @param $task
     *
     * @return -1 : done but not finish 1 : done with success
     * */
    public static function cronMailStockVerify($task): int
    {
        if (empty(PluginIserviceConfig::getConfigValue('enabled_crons.mailStockVerify'))) {
            $task->log("mailStockVerify is disabled by configuration.\n");
            return -2;
        }

        global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;

        $comment_parts = explode("\n", $task->fields['comment'], 2);
        $target_email  = $comment_parts[0];

        if (empty($target_email)) {
            $task->log("Invalid email address: $target_email\n");
            return -1;
        }

        $task->log("Verify stocks\n");

        $consumables = PluginIserviceDB::getQueryResult(
            "
            select c.*, c.stock + coalesce(o.amount, 0) - c.minimum_stock difference, coalesce(o.amount, 0) ordered_amount
            from (
                select l.codmat, n.denum, sum(coalesce(l.stoci, 0) - coalesce(l.iesiri, 0)) stock, m.minimum_stock
                from hmarfa_lotm l
                join hmarfa_nommarfa n on n.cod = l.codmat
                left join glpi_plugin_iservice_minimum_stocks m on m.plugin_iservice_consumables_id = l.codmat
                where m.minimum_stock > 0
                group by l.codmat
                ) c
            left join (
                select sum(io.amount) amount, plugin_iservice_consumables_id
                from glpi_plugin_iservice_intorders io
                where io.plugin_iservice_orderstatuses_id < 4
                group by io.plugin_iservice_consumables_id
                ) o on o.plugin_iservice_consumables_id = c.codmat
            where c.stock + coalesce(o.amount, 0) <= c.minimum_stock
            order by difference, codmat
        "
        );

        if (empty($consumables)) {
            return 0; // Nothing to do.
        }

        $negative_difference = true;
        $email_message       = "<b>Următoarele consumabile au stocul sub limita definită</b>:<br>";
        foreach ($consumables as $consumable) {
            if (empty($consumable['difference']) && $negative_difference) {
                if (!empty($email_message)) {
                    $email_message .= "<br>";
                }

                $email_message      .= "<b>Următoarele consumabile au stoc minim</b>:<br>";
                $negative_difference = false;
            }

            $siteUrl = PluginIserviceConfig::getConfigValue('site_url');
            $email_message .=
                sprintf(
                    "<a href='$siteUrl$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=loturi_stoc&loturi_stoc0[cod]=%1\$s&loturi_stoc0[filter_description]=%1\$s (%2\$s)' target='_blank'>%1\$15s</a> (%2\$s) are stoc minim %3\$3d, %4$3s sunt numai %5\$3d în stoc + %6\$3d comenzi în derulare %7\$s<br>",
                    $consumable['codmat'],
                    $consumable['denum'],
                    $consumable['minimum_stock'],
                    $negative_difference ? 'dar' : 'și',
                    $consumable['stock'],
                    $consumable['ordered_amount'],
                    $negative_difference ? "(diferență: $consumable[difference])" : ''
                );
        }

        $task->addVolume(count($consumables));
        $task->log(count($consumables) . " consumables were below stock.\n");

        $mmail = new GLPIMailer();
        $mmail->AddCustomHeader("Auto-Submitted: auto-generated");
        $mmail->AddCustomHeader("X-Auto-Response-Suppress: OOF, DR, NDR, RN, NRN");
        $mmail->SetFrom($CFG_GLPI["admin_email"], $CFG_GLPI["admin_email_name"], false);
        foreach (preg_split("/(,|;)/", $target_email) as $to_address) {
            $mmail->AddAddress($to_address);
        }

        $mmail->Subject = "Consumabile sub sau cu limita setată";
        $mmail->msgHTML($email_message);
        // $mmail->Body = $email_message;
        // $mmail->WordWrap = 100;
        if ($mmail->send()) {
            $task->log("Email sent to $target_email.\n");
        } else {
            $task->log("Error sending email to $target_email.\n");
        }

        return 1; // Done.
    }

}

<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceInboundLotPriceDeviationVerifier extends CommonDBTM
{

    public static function getTable($classname = null): string
    {
        if (empty($classname)) {
            $classname = 'InboundLotPriceDeviationVerifier';
        }

        return parent::getTable($classname);
    }

    public static function getTypeName($nb = 0): string
    {
        return _tn('Inbound Lot Price Deviation Verifier', 'Inbound Lot Price Deviation Verifiers', $nb);
    }

    public static function cronInfo($name): ?array
    {

        switch ($name) {
        case 'inboundLotPriceDeviationVerify' :
            return [
                'description' => _t('Verifies inbound lot price deviations and sends email'),
                'parameter'   => __('Deviation threshold')
            ];
        }

        return null;
    }

    /**
     * Cron action on inboundLotPriceDeviationVerify
     *
     * @param $task
     *
     * @return -1 : done but not finish 1 : done with success
     * */
    public static function cronInboundLotPriceDeviationVerify($task): int
    {
        global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;

        $comment_parts = explode("\n", $task->fields['comment'], 2);
        $target_email  = $comment_parts[0];

        if (empty($target_email)) {
            $task->log("Invalid email address: $target_email\n");
            return -1;
        }

        $task->log("Verify inbound lot price deviations\n");

        $deviations = PluginIserviceDB::getQueryResult(
            "
                WITH date_ranks AS (
                    SELECT DISTINCT
                        l.codmat,
                        t.dataint,
                        DENSE_RANK() OVER (PARTITION BY l.codmat ORDER BY t.dataint DESC) as date_rank
                    FROM hmarfa_lotm l
                    LEFT JOIN hmarfa_tran t ON t.nrtran = l.nrtran
                    WHERE l.pcont <> 0 AND t.dataint > '2025-01-01'
                ),
                filtered_lots AS (
                    SELECT
                        l.nrtran,
                        t.dataint,
                        l.codmat,
                        l.grupa,
                        l.pcont,
                        l.stoci,
                        f.initiale as denumire_partener,
                        n.denum as denumire_material,
                        dr.date_rank
                    FROM hmarfa_lotm l
                    LEFT JOIN hmarfa_tran t ON t.nrtran = l.nrtran
                    LEFT JOIN hmarfa_firme f ON f.cod = t.furnizor
                    LEFT JOIN hmarfa_nommarfa n ON n.cod = l.codmat
                    JOIN date_ranks dr ON l.codmat = dr.codmat AND t.dataint = dr.dataint
                    WHERE l.pcont <> 0 AND t.dataint > '2025-01-01'
                    AND dr.date_rank <= 2  -- Only keep the latest and second latest dates
                )
                SELECT
                    l1.nrtran,
                    l1.dataint,
                    l1.codmat,
                    l1.grupa,
                    l1.pcont,
                    l1.stoci,
                    l1.denumire_partener,
                    l1.denumire_material,
                    l2.nrtran AS compared_nrtran,
                    l2.pcont AS compared_pcont,
                    CASE WHEN l2.pcont = 0 THEN NULL
                         ELSE ROUND((l1.pcont - l2.pcont) / l2.pcont * 100, 2)
                    END AS deviance_percentage
                FROM filtered_lots l1
                JOIN filtered_lots l2 ON 
                    l1.codmat = l2.codmat AND
                    l1.date_rank = 1 AND
                    l2.date_rank = 2
                WHERE 
                   CASE WHEN l2.pcont = 0 THEN NULL
                         ELSE ABS(l1.pcont - l2.pcont) / l2.pcont * 100 
                   END > " . (!empty($task->fields['param']) ? $task->fields['param'] : 5)
        );

        if (empty($deviations)) {
            return 0; // Nothing to do.
        }

        $email_message = "<b>" . _t('Following deviations were detected in inbound lots') . "</b>:<br>";
        foreach ($deviations as $deviation) {
            $siteUrl        = PluginIserviceConfig::getConfigValue('site_url');
            $line           = sprintf(
                _t('Mat. name: %1$s, Current price: %2$s, Previous price: %3$s, Deviation: %4$s%%, Transaction: %5$s, Other transaction: %6$s'),
                $deviation['denumire_material'],
                $deviation['pcont'],
                $deviation['compared_pcont'],
                $deviation['deviance_percentage'],
                $deviation['nrtran'],
                $deviation['compared_nrtran']
            );
            $email_message .=
                sprintf(
                    "<a href='$siteUrl$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=InboundLots&inboundlots0[codmat]=%1\$s' target='_blank'>%1\$s</a> - %2\$s<br>",
                    $deviation['codmat'],
                    $line
                );
        }

        $task->addVolume(count($deviations));
        $task->log(count($deviations) . " deviations detected.\n");

        $mmail = new GLPIMailer();
        $mmail->AddCustomHeader("Auto-Submitted: auto-generated");
        $mmail->AddCustomHeader("X-Auto-Response-Suppress: OOF, DR, NDR, RN, NRN");
        $mmail->SetFrom($CFG_GLPI["admin_email"], $CFG_GLPI["admin_email_name"], false);
        foreach (preg_split("/(,|;)/", $target_email) as $to_address) {
            $mmail->AddAddress($to_address);
        }

        $mmail->Subject = _t('Inbound lot price deviations detected');
        $mmail->msgHTML($email_message);
        if ($mmail->send()) {
            $task->log("Email sent to $target_email.\n");
        } else {
            $task->log("Error sending email to $target_email.\n");
        }

        return 1; // Done.
    }

}

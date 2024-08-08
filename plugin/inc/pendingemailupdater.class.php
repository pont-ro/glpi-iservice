<?php

// Imported from iService2, needs refactoring. Original file: "reminder.class.php".
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIservicePendingEmailUpdater extends CommonDBTM
{

    static function getTable($classname = null)
    {
        if (empty($classname)) {
            $classname = 'PluginIservicePendingEmailUpdater';
        }

        return parent::getTable($classname);
    }

    static function getTypeName($nb = 0)
    {
        return _tn('Pending email updater', 'Pending email updaters', $nb);
    }

    static function cronInfo($name)
    {

        switch ($name) {
        case 'updatePendingEmails' :
            return [
                'description' => 'Actualizeaza e-mailurile in asteptare',
            ];
        }
    }

    /**
     * Cron action on updatePendingEmails
     *
     * @param $task
     *
     * @return -1 : done but not finish 1 : done with success
     * */
    static function cronupdatePendingEmails($task)
    {
        global $DB, $CFG_PLUGIN_ISERVICE;

        if (empty(PluginIserviceConfig::getConfigValue('enabled_crons.updatePendingEmails'))) {
            $task->log("Pending email updater is disabled by configuration.\n");
            return -2;
        }

        $dbf_path_base = self::getInvoiceSearchFolder();

        if (!file_exists($dbf_path_base)) {
            $task->log("Invalid path for dbf files: $dbf_path_base\n");
        }

        $pending_emails = PluginIserviceDB::getQueryResult(
            "
            select pe.id, p.id pid, p.serial, max(fr.nrfac) nrfac
            from glpi_plugin_iservice_pendingemails pe
            join glpi_printers p on p.id = pe.printers_id 
            join hmarfa_facrind fr on fr.descr like CONCAT('%', p.serial, '%') and fr.codmat like 'S%'
            where pe.refresh_time < now() 
              and pe.attachment is null
            group by pe.id 
            "
        );

        foreach ($pending_emails as $pending_email_data) {
            foreach (glob($dbf_path_base . '/' . "I$pending_email_data[nrfac]*.*") as $invoice) {
                $attachment = basename($invoice);
                if ($DB->query("update glpi_plugin_iservice_pendingemails set invoice = '$pending_email_data[nrfac]', attachment='$attachment' where id = $pending_email_data[id]")) {
                    $task->log("Last invoice for printer $pending_email_data[pid] is $attachment");
                } else {
                    $task->log("Could not set last invoice ($attachment) for printer $pending_email_data[pid]");
                }

                break;
            }
        }

        return 1;
    }

    public static function getInvoiceSearchFolder($year = null)
    {
        $cronTasks = (new CronTask())->find(['itemtype' => 'PluginIservicePendingEmailUpdater', 'name' => 'updatePendingEmails']);
        $cronTask  = array_shift($cronTasks);

        return str_replace('[year]', $year ?: date('Y'), $cronTask['comment']);
    }

}

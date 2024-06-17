<?php

// Imported from iservice2 and ran code beautifier.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceBackupCleaner extends CommonDBTM
{

    public static function getTable($classname = null)
    {
        if (empty($classname)) {
            $classname = 'PluginIserviceBackupCleaner';
        }

        return parent::getTable($classname);
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Backup cleaner', 'Backup cleaners', $nb, 'iservice');
    }

    public static function cronInfo($name)
    {

        switch ($name) {
        case 'backupClean' :
            return [
                'description' => 'Delete old backups',
            ];
        }
    }

    /**
     * Cron action on backupClean
     *
     * @param $task
     *
     * @return -1 : done but not finish 1 : done with success
     * */
    public static function cronbackupClean($task)
    {
        try {
            $task_backup      = new PluginIserviceTask_Backup();
            $cleaning_results = $task_backup->cleanOldBackups();
            foreach ($cleaning_results as $cleaning_result) {
                $task->log($cleaning_result);
            }

            return 1;
        } catch (\Exception $e) {
            $task->log("The old backups cleaning has failed: " . $e->getMessage());
            return -1;
        }
    }

}

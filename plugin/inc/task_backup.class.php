<?php

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

// Imported from iService2, needs refactoring.
class PluginIserviceTask_Backup
{
    protected $database           = null;
    protected $backup_key         = null;
    protected $backup_dir         = null;
    protected $mysqldump_base     = null;
    protected $mysql_command_base = null;

    public function __construct()
    {
        global $DB, $CFG_PLUGIN_ISERVICE;
        $this->database   = $DB->dbdefault;
        $this->backup_key = PluginIserviceConfig::getConfigValue('backup_restore.backup_key');
        $this->backup_dir = PluginIserviceConfig::getConfigValue('backup_restore.backup_path');

        $mysql_params             = "-u $DB->dbuser -p$DB->dbpassword -h $DB->dbhost";
        $this->mysqldump_base     = "mysqldump $mysql_params $this->database";
        $this->mysql_command_base = "mysql $mysql_params";
    }

    public function getTitle()
    {
        return 'Backup';
    }

    public function execute()
    {
        $restore = IserviceToolBox::getInputVariable('restore', null);

        if (IserviceToolBox::getInputVariable('backup', null)) {
            echo $this->createBackup(), "<br><br>";
        } elseif ($restore) {
            set_time_limit(0);
            echo $this->restoreDB($restore), "<br><br>";
        }

        Html::header(__('Backup/restore', 'iservice'));
        echo TemplateRenderer::getInstance()->render(
            '@iservice/pages/admin/backup-restore.html.twig',
            [
                'backupList' => $this->getBackupsListHtml(),
            ]
        );
    }

    protected function createBackup()
    {
        global $CFG_PLUGIN_ISERVICE;

        $backup_path = $this->backup_dir . '/' . date('Y-m-d') . '/' . date('His') . '/' . 'mysql' . '/';
        if (!file_exists($backup_path)) {
            mkdir($backup_path, 0775, true);
        }

        switch ($CFG_PLUGIN_ISERVICE['backup_restore']['backup_method']) {
        case 'bz2':
            $backup_command = "$this->mysqldump_base | bzip2 -c > \"$backup_path$this->database.bz2\"";
            break;
        default:
            $backup_command = "$this->mysqldump_base > \"$backup_path$this->database.sql\"";
        }

        if (true !== ($result = $this->shellExecute($backup_command, "Could not create backup for <b>$this->database</b> database."))) {
            return $result;
        }

        return $this->getResponseDiv("Backup sucessfull.");
    }

    protected function restoreDB($backup_file_path)
    {
        if (false === ($import_command = $this->getImportCommandForFilePath($backup_file_path))) {
            return $this->getResponseDiv("The given file was not a valid backup");
        }

        if (true !== ($result = $this->shellExecute("$this->mysql_command_base -e \"drop database if exists $this->database\"", "Could not drop <b>$this->database</b> database."))) {
            return $result;
        }

        if (true !== ($result = $this->shellExecute("$this->mysql_command_base -e \"create database $this->database /*!40100 DEFAULT CHARACTER SET utf8mb4 */\"", "Could not recreate <b>$this->database</b> database."))) {
            return $result;
        }

        if (true !== ($result = $this->shellExecute($import_command, "Could not import data in <b>$this->database</b> database."))) {
            $import_failed = " but you will have to reactivate the iService plugin";
        }

        if (true !== ($result = $this->shellExecute($this->getImportCommandForFilePath(PLUGIN_ISERVICE_DIR . '/install/sql/create_stored_procedures.sql'), "Could not create stored procedures in <b>$this->database</b> database."))) {
            return $result;
        }

        return $this->getResponseDiv("Restore sucessfull" . ($import_failed ?? '') . ".");
    }

    protected function getBackupsListHtml($backups = [])
    {
        global $CFG_PLUGIN_ISERVICE;

        if (empty($backups)) {
            $backups = $this->getBackups();
        }

        $html = "<ul>";
        foreach (array_keys($backups) as $date_value) {
            $html               .= "<li class='collapsible' id='$date_value'>";
            $html               .= "<label>$date_value</label> ";
            $backup_path_encoded = urlencode($this->getBackupPath($date_value));
            $html               .= "<i class='pointer??? fa fa-trash' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageBackup.php?operation=remove_folder&id=$backup_path_encoded\", \"\", function(message) { if (message = \"" . IserviceToolBox::RESPONSE_OK . "\") { $(\"#$date_value\").remove(); } else { alert(message); }}); ' style='color:red;'></i>";
            $html               .= "<ul style='display: none'>";

            foreach (array_keys($backups[$date_value]) as $time_value) {
                $li_id               = "$date_value-$time_value";
                $backup_path_encoded = urlencode($this->getBackupPath($date_value, $time_value));
                $html               .= "<li class='collapsible' id='$li_id'>";
                $html               .= sprintf("<label>%s:%s:%s</label> ", substr($time_value, 0, 2), substr($time_value, 2, 2), substr($time_value, 4, 2));
                $html               .= "<i class='pointer??? fa fa-trash' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageBackup.php?operation=remove_folder&id=$backup_path_encoded\", \"\", function(message) { if (message = \"" . IserviceToolBox::RESPONSE_OK . "\") { $(\"#$li_id\").remove(); } else { alert(message); }}); ' style='color:red;'></i>";
                $html               .= "<ul style='display: none'>";
                foreach ($backups[$date_value][$time_value] as $backup_file) {
                    $li_id               = "$date_value-$time_value-" . IserviceToolBox::getHtmlSanitizedValue($backup_file);
                    $backup_path         = addslashes($this->getBackupPath($date_value, $time_value, $backup_file));
                    $backup_path_encoded = urlencode($this->getBackupPath($date_value, $time_value, $backup_file));
                    $html               .= "<li id='$li_id'>";
                    $html               .= "<input class ='submit' onclick='$(\"[name=restore]\").val(\"$backup_path\");' type='submit' value='Restore'> ";
                    $html               .= "$backup_file ";
                    $html               .= "<i class='pointer??? fa fa-trash' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageBackup.php?operation=remove_backup&id=$backup_path_encoded\", \"\", function(message) { if (message = \"" . IserviceToolBox::RESPONSE_OK . "\") { $(\"#$li_id\").remove(); } else { alert(message); }}); ' style='color:red;'></i>";
                    $html               .= "</li>";
                }

                $html .= "</ul></li>";
            }

            $html .= "</ul></li>";
        }

        $html .= "</ul></li>";

        return $html;
    }

    protected function getBackups()
    {
        $backups = [];

        foreach (scandir($this->backup_dir) as $date_value) {
            $date_path = $this->backup_dir . '/' . $date_value;
            if (in_array($date_value, ['.', '..']) || !is_dir($date_path)) {
                continue;
            }

            foreach (scandir($date_path) as $time_value) {
                $time_path = $date_path . '/' . $time_value . '/' . 'mysql';
                if (in_array($time_value, ['.', '..']) || !is_dir($time_path)) {
                    continue;
                }

                foreach (scandir($time_path) as $backup_file) {
                    if (in_array($backup_file, ['.', '..'])) {
                        continue;
                    }

                    if (strpos($backup_file, $this->backup_key) === false) {
                        continue;
                    }

                    if ($this->getImportCommandForFilePath($time_path . '/' . $backup_file) === false) {
                        continue;
                    }

                    $backups[$date_value][$time_value][] = $backup_file;
                }
            }
        }

        return $backups;
    }

    protected function getBackupPath($date_value, $time_value = null, $backup_file = null)
    {
        $result = $this->backup_dir . '/' . $date_value;
        if (!empty($time_value)) {
            $result .= '/' . $time_value . '/' . 'mysql';
        }

        if (!empty($backup_file)) {
            $result .= '/' . $backup_file;
        }

        return $result;
    }

    protected function getImportCommandForFilePath($file_path)
    {
        if (!file_exists($file_path) || !is_file($file_path)) {
            return false;
        }

        $drivers = [
            'sql' => sprintf("$this->mysql_command_base $this->database < \"%s\"", $file_path),
            'gz' => sprintf("zcat \"%s\" | $this->mysql_command_base $this->database", $file_path),
            'bz2' => sprintf("bzcat \"%s\" | $this->mysql_command_base $this->database", $file_path),
        ];

        return $drivers[pathinfo($file_path, PATHINFO_EXTENSION)] ?? false;
    }

    protected function shellExecute($command, $error_message = 'Could not execute command', $error_type = ERROR)
    {
        $output     = null;
        $return_var = null;
        exec($command, $output, $return_var);
        return $return_var ? $this->getResponseDiv($error_message, $error_type) : true;
    }

    protected function getResponseDiv($response, $response_type = INFO)
    {
        switch ($response_type) {
        case ERROR:
            $class = 'error';
            $icon  = 'fa-exclamation-triangle';
            break;
        case WARNING:
            $class = 'warning';
            $icon  = 'fa-exclamation-triangle';
            break;
        default:
            $class = 'info';
            $icon  = 'fa-check-circle';
        }

        return"<div class='$class'><i class='fa $icon'></i> $response</div>";
    }

    public function cleanOldBackups()
    {
        $result               = [];
        $date_now             = time();
        $monthly_first_backup = '';

        foreach ($this->getBackups() as $backup_date => $backup_hours) {
            $current_backup_time     = strtotime($backup_date);
            $current_backup_age_days = round(($date_now - $current_backup_time) / (60 * 60 * 24));

            $result[] = "Found $current_backup_age_days days old backup $backup_date folder with backups: " . implode(', ', array_keys($backup_hours));

            if (empty($monthly_first_backup) || date('Y-m', $monthly_first_backup) != date('Y-m', $current_backup_time)) {
                $monthly_first_backup = $current_backup_time;
                $result[]             = 'This is the first backup in the month';
            }

            if ($current_backup_age_days > 45) {
                if ($monthly_first_backup == $current_backup_time) {
                    foreach (array_slice(array_keys($backup_hours), 1) as $time) {
                        IserviceToolBox::unlinkRecursively($this->getBackupPath($backup_date, $time));
                        $result[] = "Removed " . $this->getBackupPath($backup_date, $time);
                    }
                } else {
                    IserviceToolBox::unlinkRecursively($this->getBackupPath($backup_date));
                    $result[] = "Removed " . $this->getBackupPath($backup_date);
                }
            } else if ($current_backup_age_days > 10) {
                foreach (array_slice(array_keys($backup_hours), 1, -1) as $time) {
                    IserviceToolBox::unlinkRecursively($this->getBackupPath($backup_date, $time));
                    $result[] = "Removed " . $this->getBackupPath($backup_date, $time);
                }
            }
        }

        return $result;
    }

}

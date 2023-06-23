<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceHMarfaImporter extends CommonDBTM
{

    static function getTable($classname = null)
    {
        if (empty($classname)) {
            $classname = 'PluginIserviceHMarfaImporter';
        }

        return parent::getTable($classname);
    }

    static function getTypeName($nb = 0)
    {
        return _n('hMarfa importer', 'hMarfa importers', $nb, 'iservice');
    }

    static function cronInfo($name)
    {

        switch ($name) {
        case 'hMarfaImport' :
            return [
                'description' => 'Importa date din hMarfa',
            ];
        }
    }

    /**
     * Cron action on hMarfaImport
     *
     * @param $task
     *
     * @return -1 : done but not finish 1 : done with success
     * */
    static function cronhMarfaImport($task)
    {
        global $DB;

        set_time_limit(120);

        if (empty(PluginIserviceConfig::getConfigValue('enabled_crons.hMarfaImport'))) {
            $task->log("hMarfa import is disabled by configuration.\n");
            return -2;
        }

        $dbf_path_base = $task->fields['comment'];

        if (!file_exists($dbf_path_base)) {
            $task->log("Invalid path for dbf files: $dbf_path_base\n");
        }

        $dbf_files = [
        // 'APLIC.DBF' => 'hmarfa_aplic',
        // 'FILES.DBF' => 'hmarfa_files',
            'FACT/FACTURI.DBF' => [
                'table' => 'hmarfa_facturi',
        // 'pk' => 'NRFAC'
            ],
            'FACT/FACRIND.DBF' => 'hmarfa_facrind',
            'INCPLA/INCPLA.DBF' => [
                'table' => 'hmarfa_incpla',
                'fields' => 'NRJUR, NRFILA, DATA, TIPIP, TIPDOC, MODEL, NRDOC, DATADOC, NRFAC, TIPFACT, CENTDOC, OBS1FAC, OBS2FAC, TIPPART, PARTENER, OBSPART, SUMAVAL, MONEDA, SUMA, SUMAFAC, SUMAREV, SUMAPRV2, SUMAREV2, TVAIP, CONTCR, STARE, CODL, NB_UPD',
            ],
            'MARFA/LOTM.DBF' => 'hmarfa_lotm',
            'MARFA/NOMMARFA.DBF' => [
                'table' => 'hmarfa_nommarfa',
                'fields' => 'COD, DENUM, DESCR, UM, MASAN, GRUPA, COD_ECH, VAMA, VAMAPOZ, TVA, TVAPOZ, ACZ, ACZPOZ, ICM, ICMPOZ, ACC, ACCPOZ, PROD_TXI, PROD_TX0, CAEN_ACT, PVINV, MONEDA, PVIN, PVINA, NB_UPD, COD_PRODUC, P_PRETURI, P_PRET1, P_PRET2, P_PRET3',
            ],
            // 'MARFA/UM.DBF' => 'hmarfa_um',
            // 'MARFA/VAMA.DBF' => 'hmarfa_vama',
            'MARFA/TRAN.DBF' => [
                'table' => 'hmarfa_tran',
                'fields' => 'NRJUR,NRTRAN,GEST,TIPDCM,MODEL,DATAINT,NRCMD,TIPFURN,FURNIZOR,NRDOC,DATADOC,FDSCAD,VALDOL,PLADOL,MONEDA,VAL,VALREV,TVA,PLA,CONT,FURVAMA,NRVAMA,DATAVAMA,VDSCAD,VALVAMA,TXVAMA,TVAVAMA,ACC,PLAVAMA,CONTVAMA,FURCHEL,NRCHEL,DATACHEL,CDSCAD,VALCHEL,TVACHEL,PLACHEL,CONTCHEL,STARE,OPTVA',
            // 'pk' => 'NRTRAN'
            ],
            'PART/FIRME.DBF' => [
                'table' => 'hmarfa_firme',
                'fields' => 'COD,INITIALE,TIP,DENUM,COD1,COD2,CONTDB,CONTCR,CODPOSTAL,LOCALITATE,ADRS1,ADRS2,ADRP1,ADRP2,TEL1,TEL2,FAX,TELEX,WEB,BANCA,CONT'
            ],
            'PART/GESTIUNI.DBF' => 'hmarfa_gestiuni',
            // 'PART/PPERS.DBF' => 'hmarfa_ppers',
            // 'PART/SECTII.DBF' => 'hmarfa_sectii',
            // 'TIP/CURSSCH.DBF' => 'hmarfa_curssch',
            // 'TIP/DICTIONA.DBF' => 'hmarfa_dictiona',
            // 'TIP/TIPDOC.DBF' => 'hmarfa_tipdoc',
            // 'TIP/TIPJUR.DBF' => 'hmarfa_tipjur',
        ];

        include_once 'classes/DBFhandler.php';

        $sql_file_name                = PluginIserviceConfig::getConfigValue('hmarfa.import.script_file');
        $import_errors_file_name      = PluginIserviceConfig::getConfigValue('hmarfa.import.errors');
        $import_errors_temp_file_name = "$import_errors_file_name.log";

        foreach ([$sql_file_name, $import_errors_file_name, $import_errors_temp_file_name] as $file) {
            $directory = dirname($file);

            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
        }

        file_put_contents($sql_file_name, '');

        foreach ($dbf_files as $file_name => $table_name) {
            // self::import_dbf_to_mysql($task, $table_name, "$dbf_path_base/$file_name", '');
            self::addImportToSql($task, $sql_file_name, $table_name, "$dbf_path_base/$file_name", '');
        }

        $task->log("Executing script file");
        shell_exec("mysql --host=$DB->dbhost --user=$DB->dbuser --password=$DB->dbpassword --database=$DB->dbdefault < \"$sql_file_name\" 2>$import_errors_temp_file_name");

        $result = file_get_contents($import_errors_temp_file_name);

        if (empty($result)) {
            file_put_contents($import_errors_file_name, "");
        } else {
            if (filesize($import_errors_file_name) === 0) {
                file_put_contents($import_errors_file_name, "###" . date('Y-m-d H:i') . "###\n");
            }

            file_put_contents($import_errors_file_name, date('[Y-m-d H:i:s] ') . $result, FILE_APPEND);
            $task->log("Script file returned error: $result");
        }

        return 1;
    }

    static function addImportToSql($task, $sql_file_name, $table, $dbf_path, $fpt_path)
    {
        if (!file_exists($dbf_path)) {
            $task->log("Dbf file does not exist: $dbf_path");
        }

        if (is_array($table)) {
            $fields      = is_array($table['fields'] ?? []) ? ($table['fields'] ?? null) : array_map('trim', explode(',', $table['fields']));
            $primary_key = $table['pk'] ?? null;
            $table       = $table['table'] ?? '';
        } else {
            $fields      = null;
            $primary_key = null;
        }

        // $task->log("Adding data for $table to script file");
        file_put_contents($sql_file_name, "TRUNCATE TABLE `$table`;\n", FILE_APPEND);

        $count              = 0;
        $sql                = '';
        $rows_per_query     = 1000;
        $primary_key_values = [];

        $dbf_data = new DBFhandler($dbf_path, $fpt_path);
        $columns  = self::getColumnsFromDbf($dbf_path, $fields);

        while (($record = $dbf_data->GetNextRecord(true)) && !empty($record) && empty($record['*'])) {
            if (($record['NRFAC'] ?? '') === '018422' && ($record['CODBENEF'] ?? '') === 'AVITUMB') {
                continue;
            }

            if ($primary_key) {
                if (in_array($record[$primary_key] ?? '###', $primary_key_values)) {
                    $task->log("Duplication detected in $table for id {$record[$primary_key]}");
                    continue;
                } elseif (isset($record[$primary_key])) {
                    $primary_key_values[] = $record[$primary_key];
                }
            }

            $has_value  = false;
            $row_values = [];

            foreach ($record as $key => $val) {
                $key = (strpos($key, chr(0x00)) !== false) ? substr($key, 0, strpos($key, chr(0x00))) : $key;

                if (!array_key_exists($key, $columns) || $val == '{BINARY_PICTURE}') {
                    continue;
                }

                $val = str_replace("'", "", $val);

                // if ($GLOBALS['from_encoding']!="") $val = mb_convert_encoding($val, 'UTF-8', $GLOBALS['from_encoding'] );
                if (trim($val)) {
                    $has_value    = true;
                    $row_values[] = "'" . addslashes(trim($val)) . "'";
                } else {
                    switch ($columns[$key]['type']) {
                    case 'F': // Float
                    case 'N': // Number
                    case 'L': // TinyInt
                    case 'D': // Date
                    case 'T': // Time
                        $row_values[] = 'NULL';
                        break;
                    default:
                        $row_values[] = "'" . substr('NULL', 0, $columns[$key]['length']) . "'";
                    }
                }
            }

            if (!$has_value) {
                continue;
            }

            $count++;

            if ($count % $rows_per_query === 1) {
                if ($count > 1) {
                    $sql .= ";\n";
                }

                $sql .= "INSERT INTO `$table` (" . implode(', ', array_keys($columns)) . ") VALUES\n";
            } else {
                $sql .= ",\n";
            }

            $sql .= "(" . implode(', ', $row_values) . ")";
        }

        file_put_contents($sql_file_name, "$sql;\n", FILE_APPEND);
        $task->log("Added $count records in script file for table $table");
    }

    protected static function getColumnsFromDbf($dbf_path, $fields = null)
    {
        $result = [];

        include_once 'classes/XBase/Table.php';
        include_once 'classes/XBase/Memo.php';
        include_once 'classes/XBase/Column.php';

        $table = new XBase\Table($dbf_path);
        foreach ($table->getColumns() as $column) {
            if (empty($fields) || in_array(strtoupper($column->name), $fields)) {
                $result[strtoupper($column->name)] = [
                    'type'   => $column->type,
                    'length' => $column->length,
                ];
            }
        }

        return $result;
    }

}

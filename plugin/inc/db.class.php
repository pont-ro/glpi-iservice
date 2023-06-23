<?php

class PluginIserviceDB extends DB
{

    public function __construct($dbHost, $dbName, $dbUser, $dbPassword)
    {
        $this->dbhost     = $dbHost;
        $this->dbuser     = $dbUser;
        $this->dbpassword = $dbPassword;
        $this->dbdefault  = $dbName;

        parent::__construct();
    }

    public static function runScriptFile($scriptPath, DB $db = null): void
    {
        if ($db === null) {
            global $DB;
            $db = $DB;
        }

        shell_exec("mysql -h $db->dbhost -u $db->dbuser -p$db->dbpassword $db->dbdefault < $scriptPath");
    }

    public static function getQueryResult($query, $id_field = 'id', ?\DBmysql $db = null): bool|array
    {
        if ($db === null) {
            global $DB;
            $db = $DB;
        }

        $query_result = [];
        if (false === ($result = $db->query($query)) || $result === true || !$db->numrows($result)) {
            return is_bool($result) ? $result : $query_result;
        }

        while ($data = $db->fetchAssoc($result)) {
            if (isset($data[$id_field])) {
                $query_result[$data[$id_field]] = $data;
            } else {
                $query_result[] = $data;
            }
        }

        return $query_result;
    }

}

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

    public static function runScriptFile($scriptPath, ?\DBmysql $db = null): void
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

    public static function populateByItemsId(CommonDBTM $object, int $id, string $itemtype = null): bool
    {
        $criteria = ['items_id' => $id];

        if (!empty($itemtype)) {
            $criteria['itemtype'] = $itemtype;
        }

        return $object->getFromDBByCrit($criteria);
    }

    public static function populateByQuery(CommonDBTM $object, string $query, $limit = false, ?\DBmysql $db = null): bool
    {
        if ($db === null) {
            global $DB;
            $db = $DB;
        }

        $tableName = $object->getTable();
        $query     = "select `$tableName`.* from `$tableName` $query" . ($limit ? " limit 1" : '');

        if (false === ($result = $db->query($query)) || $result === true || $db->numrows($result) !== 1) {
            if ($db->numrows($result) > 1) {
                trigger_error(
                    sprintf(
                        'populateByQuery expects to get one result, %1$s found in query "%2$s".',
                        $db->numrows($result),
                        $query
                    ),
                    E_USER_WARNING
                );
            }

            return false;
        }

        $object->fields = $db->fetchAssoc($result);
        $object->post_getFromDB();

        return true;
    }

}

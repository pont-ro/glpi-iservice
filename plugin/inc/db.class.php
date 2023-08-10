<?php

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
class PluginIserviceDB extends DB
{
    private static array $tableIndexes = [];

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

        $output      = [];
        $returnValue = null;

        exec("mysql -h $db->dbhost -u $db->dbuser -p$db->dbpassword $db->dbdefault < $scriptPath", $output, $returnValue);

        if ($returnValue !== 0) {
            echo "An error occurred: " . implode("\n", $output);
            trigger_error(json_encode($output), E_USER_WARNING);
        } else {
            echo IserviceToolBox::RESPONSE_OK;
        }
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

    public static function createTable(string $tableName, array $tableConfig, ?\DBmysql $db = null): bool
    {
        if ($db === null) {
            global $DB;
            $db = $DB;
        }

        if ($db->tableExists($tableName, false)) {
            return self::alterTable($tableName, $tableConfig, $db);
        }

        $query  = "create table $tableName (";
        $query .= implode(
            ', ', [
                self::getColumnsCreateModifySql($tableName, $tableConfig, $db),
                self::getIndexesCreateModifySql($tableName, $tableConfig, $db),
            ]
        );
        $query  = rtrim($query, ', ');
        $query .= ')';

        return $db->query($query) === true;
    }

    public static function deleteTable(string $tableName, ?\DBmysql $db = null): bool
    {
        if ($db === null) {
            global $DB;
            $db = $DB;
        }

        return $db->query("drop table if exists $tableName") === true;
    }

    public static function alterTable(string $tableName, array $tableConfig, ?\DBmysql $db = null): bool
    {
        if ($db === null) {
            global $DB;
            $db = $DB;
        }

        if (!$db->tableExists($tableName, false)) {
            return self::createTable($tableName, $tableConfig, $db);
        }

        $query  = "alter table $tableName";
        $query .= implode(
            ', ', [
                self::getColumnsCreateModifySql($tableName, $tableConfig, $db),
                self::getIndexesCreateModifySql($tableName, $tableConfig, $db),
            ]
        );
        $query  = rtrim($query, ', ');

        return $db->query($query) === true;
    }

    public static function getColumnsCreateModifySql(string $tableName, array $tableConfig, ?\DBmysql $db = null): string
    {
        if (empty($tableConfig['columns'])) {
            return '';
        }

        if ($db === null) {
            global $DB;
            $db = $DB;
        }

        $sql    = '';
        $action = '';

        foreach ($tableConfig['columns'] as $columnName => $columnConfig) {
            if ($db->tableExists($tableName)) {
                $action = $db->fieldExists($tableName, $columnName) ? ' modify column' : ' add column';
            }

            $sql .= "$action `$columnName` $columnConfig,";
        }

        return rtrim($sql, ',');
    }

    public static function getIndexesCreateModifySql(string $tableName, array $tableConfig, ?\DBmysql $db = null): string
    {
        if (empty($tableConfig['indexes'])) {
            return '';
        }

        if ($db === null) {
            global $DB;
            $db = $DB;
        }

        $sql    = '';
        $action = '';

        foreach ($tableConfig['indexes'] as $indexConfig) {
            if ($db->tableExists($tableName)) {
                $action = 'add';

                if (self::indexExists($tableName, $indexConfig['name'], $db)) {
                    continue;
                }
            }

            $sql .= "$action $indexConfig[type] $indexConfig[name] $indexConfig[columns],";
        }

        return rtrim($sql, ',');
    }

    public static function indexExists(string $tableName, string $indexName, $useCache = true, ?\DBmysql $db = null): bool
    {
        if ($db === null) {
            global $DB;
            $db = $DB;
        }

        if ($indexName == 'primary key') {
            $indexName = 'PRIMARY';
        }

        if ($indexes = self::listIndexes($tableName, $useCache, $db)) {
            if (isset($indexes[$indexName])) {
                return true;
            }

            return false;
        }

        return false;
    }

    public static function listIndexes($tableName, $useCache = true, ?\DBmysql $db = null): bool|array
    {
        if ($db === null) {
            global $DB;
            $db = $DB;
        }

        if ($useCache && isset(self::$tableIndexes[$tableName])) {
            return self::$tableIndexes[$tableName];
        }

        $result = $db->query("show index from `$tableName`");
        if ($result) {
            if ($db->numrows($result) > 0) {
                self::$tableIndexes[$tableName] = [];
                while ($data = $db->fetchAssoc($result)) {
                    self::$tableIndexes[$tableName][$data["Key_name"]] = $data;
                }

                return self::$tableIndexes[$tableName];
            }

            return [];
        }

        return false;
    }

}

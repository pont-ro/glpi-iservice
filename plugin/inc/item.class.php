<?php

// Imported from iService2, needs refactoring.
trait PluginIserviceItem
{
    protected static $item_cache = [];

    /*
     * @param $id int The id of the item to retrieve.
     * @param $useCache bool [optional]<p>If you want to reload from DB every time, set this to <i>FALSE</i><br>(otherwise a static cache is used).</p>
     * @param $returnObject bool [optional]<p>If you want to return <i>FALSE</i> on error, set this to <i>TRUE</i><br>(otherwise an empty object is returned).</p>
     *
     * @return mixed
     */
    public static function get($id, $useCache = true, $returnObject = true)
    {
        if (!$useCache || empty(self::$item_cache[$id])) {
            $item = new self();
            if ($item->getFromDB($id)) {
                self::$item_cache[$id] = $item;
            } else {
                self::$item_cache[$id] = false;
                return $returnObject ? new self() : false;
            }
        }

        return self::$item_cache[$id];
    }

    public function getFromDBByItemsId(int $items_id, string $itemtype = null): bool|self
    {
        $criteria = ['items_id' => $items_id];
        if (!empty($itemtype)) {
            $criteria['itemtype'] = $itemtype;
        }

        $object = new self();
        if (!$object->getFromDBByCrit($criteria)) {
            return false;
        }

        return $this->get($object->getID());
    }

    public function getFromDBByQuery(string $query, $limit = false, ?\DBmysql $db = null): bool
    {
        if ($db === null) {
            global $DB;
            $db = $DB;
        }

        $tableName = $this->getTable();
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

        return $this->get($db->fetchAssoc($result)['id']);
    }

}

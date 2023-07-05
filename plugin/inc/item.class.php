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

}

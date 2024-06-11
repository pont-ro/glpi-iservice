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

    public function add(array $input, $options = [], $history = true)
    {
        $model  = new parent;
        $result = $model->add($input, $options, $history);

        if ($result) {
            $this->post_addItem($history);

            $result = $this->updateCustomFields($model, $input);
        }

        return $result;
    }

    public function post_addItem($history = 1): void
    {
        // This should be kept here, but do not call parent::post_addItem($history) here, because it is already called in add().
    }

    public function update(array $input, $history = 1, $options = []): bool
    {
        $model = new parent;
        $model->getFromDB($this->getID());
        $result = $model->update($input, $history, $options);

        if ($result) {
            $this->post_updateItem($history);
        }

        return $result && $this->updateCustomFields($model, $input);
    }

    public function post_updateItem($history = 1): void
    {
        // This should be kept here, but do not call parent::post_updateItem($history) here, because it is already called in update().
    }

    public function updateCustomFields($model, $input, $history = 1, $options = []): bool
    {
        // If custom fields was updated by hooks, plugin_fields_data is not empty.
        if (!empty($model->plugin_fields_data)) {
            return true;
        }

        if (isset($input['items_id'])) {
            unset($input['items_id']);
        }

        $result = false;

        if ($this->loadOrCreateCustomFields($model->getID())) {
            $result = $this->customfields->update(array_merge($input, ['id' => $this->customfields->getID()]), $history, $options);
        }

        if (!$result) {
            Session::addMessageAfterRedirect('Could not save custom fields', true, ERROR);
        }

        return $result;
    }

    public function getFromDB($ID): bool
    {
        if (parent::getFromDB($ID)) {
            if (!$this->loadOrCreateCustomFields($ID) && !empty(self::$customFieldsModelName)) {
                return false;
            }

            $this->additionalGetFromDbSteps($ID);

            // Further code possibility.
            self::$item_cache[$ID] = $this;
            return true;
        }

        return false;
    }

    public function loadOrCreateCustomFields($ID): bool
    {
        if (empty(self::$customFieldsModelName)) {
            return false;
        }

        $this->customfields = new self::$customFieldsModelName;

        if (!PluginIserviceDB::populateByItemsId($this->customfields, $ID)
            && !$this->customfields->add(
                [
                    'add' => 'add',
                    'items_id' => $ID,
                    'itemtype' => $this->getType(),
                    '_no_message' => true
                ]
            )
        ) {
            return false;
        }

        return true;
    }

    public function additionalGetFromDbSteps($ID = null): void
    {
    }

}

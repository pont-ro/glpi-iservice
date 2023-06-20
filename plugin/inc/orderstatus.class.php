<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * PluginIserviceOrderStatus class
 * */
class PluginIserviceOrderStatus extends CommonDropdown
{
    const WEIGHT_STARTED   = 0;
    const WEIGHT_PROCESSED = 100;
    const WEIGHT_ORDERED   = 200;
    const WEIGHT_RECEIVED  = 300;
    const WEIGHT_DISCARDED = 800;
    const WEIGHT_CLOSED    = 900;

    public $dohistory         = false;
    public $can_be_translated = false;

    public static $rightname = 'plugin_iservice_orderstatus';
    public static $weights   = [];
    public static $full_load = false;

    public static function getTypeName($nb = 0): string
    {
        return _n('Order status', 'Order statuses', $nb, 'iservice');
    }

    public static function getWeight($id): ?int
    {
        if (!isset(self::$weights[$id])) {
            $order_status = new PluginIserviceOrderStatus();
            if (!$order_status->getFromDB($id)) {
                return null;
            }

            self::$weights[$id] = $order_status->fields['weight'];
        }

        return self::$weights[$id];
    }

    public static function getIdFromWeight($weight): int
    {
        if (($id = array_search($weight, self::$weights)) != false) {
            return $id;
        }

        $order_status = new PluginIserviceOrderStatus();
        if (!$order_status->getFromDBByCrit(['weight' => $weight])) {
            return 0;
        }

        self::$weights[$order_status->getID()] = $order_status->fields['weight'];
        return $order_status->getID();
    }

    public static function getIdsFromWeight($weight, $operator = '='): array
    {
        $status_ids = [];
        if (!self::$full_load) {
            self::getAllForDropdown();
        }

        foreach (self::$weights as $status_id => $status_weight) {
            switch ($operator) {
            case '<' :
                if ($status_weight < $weight) {
                          $status_ids[] = $status_id;
                }
                break;
            case '>' :
                if ($status_weight > $weight) {
                    $status_ids[] = $status_id;
                }
                break;
            case '<=' :
                if ($status_weight <= $weight) {
                    $status_ids[] = $status_id;
                }
                break;
            case '>=' :
                if ($status_weight >= $weight) {
                    $status_ids[] = $status_id;
                }

            case '=':
            case '==':
            case '===':
            default:
                if ($status_weight == $weight) {
                    $status_ids[] = $status_id;
                }
                break;
            }
        }

        return $status_ids;
    }

    public static function getIdStarted(): int
    {
        return self::getIdFromWeight(self::WEIGHT_STARTED);
    }

    public static function getIdProcessed(): int
    {
        return self::getIdFromWeight(self::WEIGHT_PROCESSED);
    }

    public static function getIdOrdered(): int
    {
        return self::getIdFromWeight(self::WEIGHT_ORDERED);
    }

    public static function getIdReceived(): int
    {
        return self::getIdFromWeight(self::WEIGHT_RECEIVED);
    }

    public static function getAllForDropdown(): array
    {
        global $DB;
        self::$weights = $statuses = [];
        if (($status_result = $DB->query("SELECT * FROM glpi_plugin_iservice_orderstatuses")) !== false) {
            while (($status_row = $DB->fetchAssoc($status_result)) != false) {
                $statuses[$status_row['id']]      = $status_row['name'];
                self::$weights[$status_row['id']] = $status_row['weight'];
            }

            self::$full_load = true;
        }

        return $statuses;
    }

    public function getLinks($withname = false): string
    {
        $ret = '';

        if ($withname) {
            $ret .= $this->fields["name"];
            $ret .= "&nbsp;&nbsp;";
        }

        return $ret;
    }

    public function displayHeader(): void
    {
        PluginIserviceHtml::header($this->getTypeName(Session::getPluralNumber()));
    }

    public function title(): void
    {
        echo "<a href='orderstatus.form.php'>" . __('Add') . "</a>";
    }

    public function getAdditionalFields(): array
    {

        return [['name' => 'weight',
            'label' => __('Weight', 'iservice'),
            'type' => 'text',
            'list' => true
        ]
        ];
    }

    /**
     * Get search function for the class
     *
     * @return array of search option
     * */
    public function getSearchOptions(): array
    {

        $tab = parent::getSearchOptions();

        $tab[11]['table']         = $this->getTable();
        $tab[11]['field']         = 'weight';
        $tab[11]['name']          = __('Weight', 'iservice');
        $tab[11]['datatype']      = 'int';
        $tab[11]['massiveactoin'] = false;

        return $tab;
    }

}

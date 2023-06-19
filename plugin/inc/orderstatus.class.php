<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
	die("Sorry. You can't access directly to this file");
}

/**
 * PluginIserviceOrderStatus class
 * */
class PluginIserviceOrderStatus extends CommonDropdown {
	const WEIGHT_STARTED = 0;
	const WEIGHT_PROCESSED = 100;
	const WEIGHT_ORDERED = 200;
	const WEIGHT_RECEIVED = 300;
	const WEIGHT_DISCARDED = 800;
	const WEIGHT_CLOSED = 900;
	
	public $dohistory = false;
	var $can_be_translated = false;

	static $rightname = 'plugin_iservice_orderstatus';
	static $weights = array();
	static $full_load = false;

	static function getTypeName($nb = 0) {
		return _n('Order status', 'Order statuses', $nb, 'iservice');
	}

	static function getWeight($id) {
		if (!isset(self::$weights[$id])) {
			$order_status = new PluginIserviceOrderStatus();
			if (!$order_status->getFromDB($id)) {
				return null;
			}
			self::$weights[$id] = $order_status->fields['weight'];
		}
		return self::$weights[$id];
	}

	static function getIdFromWeight($weight) {
		if (($id = array_search($weight, self::$weights)) != false) {
			return $id;
		}
		$order_status = new PluginIserviceOrderStatus();
		if (!$order_status->getFromDBByQuery("WHERE weight = $weight LIMIT 1")) {
			return 0;
		}
		self::$weights[$order_status->getID()] = $order_status->fields['weight'];
		return $order_status->getID();
	}
	
	static function getIdsFromWeight($weight, $operator = '=') {
		$status_ids = array();
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

	static function getIdStarted() {
		return self::getIdFromWeight(self::WEIGHT_STARTED);
	}
	
	static function getIdProcessed() {
		return self::getIdFromWeight(self::WEIGHT_PROCESSED);
	}
	
	static function getIdOrdered() {
		return self::getIdFromWeight(self::WEIGHT_ORDERED);
	}
	
	static function getIdReceived() {
		return self::getIdFromWeight(self::WEIGHT_RECEIVED);
	}
	
	static function getAllForDropdown() {
		global $DB;
		self::$weights = $statuses = array();
		if (($status_result = $DB->query("SELECT * FROM glpi_plugin_iservice_orderstatuses")) !== false) {
			while (($status_row = $DB->fetchAssoc($status_result)) != false) {
				$statuses[$status_row['id']] = $status_row['name'];
				self::$weights[$status_row['id']] = $status_row['weight'];
			}
			self::$full_load = true;
		}
		return $statuses;
	}

	function getLinks($withname = false) {
		$ret = '';

		if ($withname) {
			$ret .= $this->fields["name"];
			$ret .= "&nbsp;&nbsp;";
		}

		return $ret;
	}

	function displayHeader() {
		PluginIserviceHtml::header($this->getTypeName(Session::getPluralNumber()));
	}

	function title() {
		echo "<a href='orderstatus.form.php'>" . __('Add') . "</a>";
	}

	function getAdditionalFields() {

		return array(array('name' => 'weight',
						'label' => __('Weight', 'iservice'),
						'type' => 'text',
						'list' => true));
	}

	/**
	 * Get search function for the class
	 *
	 * @return array of search option
	 * */
	function getSearchOptions() {

		$tab = parent::getSearchOptions();

		$tab[11]['table'] = $this->getTable();
		$tab[11]['field'] = 'weight';
		$tab[11]['name'] = __('Weight', 'iservice');
		$tab[11]['datatype'] = 'int';
		$tab[11]['massiveactoin'] = false;

		return $tab;
	}

}

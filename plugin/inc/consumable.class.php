<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceConsumable extends CommonDBTM
{

    public static function getTypeName($nb = 0): string
    {
        return _n('Consumable', 'Consumables', $nb);
    }

    public function getFromDB($ID): bool
    {
        global $DB;
        if (strlen($ID) == 0) {
            return false;
        }

        $result = PluginIserviceDB::populateByQuery($this, "WHERE `{$this->getTable()}`.`{$this->getIndexName()}` = '$ID' LIMIT 1");
        return $result && $this->getPrice() && $this->getMinimumStock();
    }

    public function getID()
    {

        if (isset($this->fields[static::getIndexName()])) {
            return $this->fields[static::getIndexName()];
        }

        return -1;
    }

    public function getPrice(): bool
    {
        global $DB;
        $this->fields['Pret'] = $DB->result($DB->query("SELECT ROUND(pcont,2) FROM hmarfa_lotm WHERE codmat = '{$this->getID()}' ORDER BY nrtran DESC LIMIT 1"), 0, 0);
        return true;
    }

    public function getMinimumStock(): bool|mysqli_result
    {
        global $DB;
        if (null === $this->fields['minimum_stock'] = $DB->result($DB->query("SELECT minimum_stock FROM glpi_plugin_iservice_minimum_stocks WHERE plugin_iservice_consumables_id = '{$this->getID()}' LIMIT 1"), 0, 0)) {
            return $DB->query("INSERT INTO glpi_plugin_iservice_minimum_stocks (plugin_iservice_consumables_id, minimum_stock) values ('{$this->getID()}', 0)");
        }

        return true;
    }

    public function setMinimumStock($value): bool|mysqli_result
    {
        global $DB;
        return $DB->query("UPDATE glpi_plugin_iservice_minimum_stocks SET minimum_stock = " . intval($value) . " WHERE plugin_iservice_consumables_id = '{$this->getID()}'");
    }

    public function getHistoryTable($partner_cod_hmarfa, &$html_table, &$gain, &$average_delivery_price): void
    {
        global $DB;
        $history_query          = "
            SELECT
                  fa.datafac AS Data_Fact
                , fr.cant AS Cant
                , fr.puliv AS Pret_Liv
                , COALESCE(ROUND((fr.puliv / NULLIF(fr.puini, 0)), 2), 1) AS Proc
            FROM hmarfa_facrind fr
            LEFT JOIN hmarfa_facturi fa ON fa.nrfac = fr.nrfac
            WHERE NOT fr.tip IN ('AIMFS', 'TAIM')
                AND fr.codmat = '" . $this->getId() . "'
                AND fa.codbenef = '$partner_cod_hmarfa'
            ORDER BY fa.datafac DESC, fr.nrfac DESC
            LIMIT 3
            ";
        $rows                   = [];
        $gain_total             = 0;
        $delivery_price_total   = 0;
        $history_header_columns = null;
        foreach ($DB->request($history_query) as $history) {
            $rows[]                = new PluginIserviceHtml_table_row('', $history);
            $gain_total           += ($history['Proc'] ?? 1) ?: 1;
            $delivery_price_total += $history['Pret_Liv'];
            if (empty($history_header_columns)) {
                $history_header_columns = array_keys($history);
            }
        }

        if (count($rows) > 0) {
            $gain                   = number_format($gain_total / count($rows), 2);
            $average_delivery_price = number_format($delivery_price_total / count($rows), 2);
        } else {
            $gain                   = null;
            $average_delivery_price = 0;
            $rows                   = "Nu există";
        }

        $history_header = new PluginIserviceHtml_table_row('short light-header');
        $history_header->populateCells($history_header_columns, '', '', 'th');
        $html_table = new PluginIserviceHtml_table('', $history_header, $rows, 'text-align:center;');
    }

}

<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

class PluginIservicePrinterDailyAverageCalculator
{

    public static function cronInfo($name)
    {
        return [
            'description' => 'Calculates Printer Daily Averages',
        ];
    }

    /**
     * Cron action on PrinterDailyAverageCalculator
     *
     * @param $task
     *
     * @return -1 : done but not finish 1 : done with success
     * */
    public static function cronPrinterDailyAverageCalculator($task)
    {
        global $DB;
        $blackWhitePrinterType = IserviceToolBox::getIdentifierByAttribute('PrinterType', 'alb-negru');
        $colorPrinterType      = IserviceToolBox::getIdentifierByAttribute('PrinterType', 'color');

        $sql = "UPDATE glpi_plugin_fields_printerprintercustomfields
            SET daily_bk_average_field = getPrinterDailyAverage(items_id, 0),
                daily_color_average_field = getPrinterDailyAverage(items_id, 1)
            WHERE items_id IN (
                SELECT p.id
                FROM glpi_printers p
                WHERE p.printertypes_id IN ($blackWhitePrinterType, $colorPrinterType)
                AND p.states_id IN (
                    SELECT id
                    FROM glpi_states
                    WHERE name LIKE 'CO%'
                    OR name LIKE 'Gar%'
                    OR name LIKE 'Pro%'
                )
            )";

        try {
            $DB->query($sql);
            $task->log("Printer Daily Averages calculation done successfully");
            return 1;
        } catch (\Exception $e) {
            $task->log("Printer Daily Averages calculation failed: " . $e->getMessage());
            return -1;
        }
    }

}

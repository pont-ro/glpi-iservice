<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

/**
 * Creates read counter tickets automatically for the printers which were recently moved to a partner.
 *
 * After a movement the daily averages of the printer are unknown, and getPrinterDailyAverageCalculation
 * needs at least 3 closed tickets with counters to be able to calculate them again, so this task collects
 * 3 readings from the E-maintenance CSV data in the first 9 days after the movement, one in every 3 days.
 */
class PluginIservicePrinterAutoReadCounter
{

    /**
     * Number of automatic readings to be created after a movement.
     */
    const READ_COUNT = 3;

    /**
     * Number of days between two automatic readings.
     */
    const READ_INTERVAL_DAYS = 3;

    /**
     * Number of days a printer is watched after a movement.
     */
    const WATCH_DAYS = self::READ_COUNT * self::READ_INTERVAL_DAYS + 1;

    /**
     * Readings from the E-maintenance CSV older than this many days are considered stale and are not used.
     */
    const MAX_CSV_AGE_DAYS = 7;

    /**
     * The ITIL category of the automatic readings. The tickets are created with it, and only the tickets of
     * this category count as automatic readings, so the task keeps track of its own work and the manual
     * readings do not interfere with it.
     *
     * The ITIL category will be created with seed_database.sql.
     */
    const ITIL_CATEGORY_NAME = 'Citire contor - automat';

    public static function cronInfo($name): ?array
    {
        switch ($name) {
        case 'printerAutoReadCounter':
            return [
                'description' => _t('Creates read counters automatically for the printers moved to a partner'),
            ];
        }

        return null;
    }

    /**
     * Cron action on printerAutoReadCounter.
     *
     * @param $task
     *
     * @return -2 : disabled, -1 : error, 0 : nothing to do, 1 : done with success
     * */
    public static function cronPrinterAutoReadCounter($task): int
    {
        if (empty(PluginIserviceConfig::getConfigValue('enabled_crons.printerAutoReadCounter'))) {
            $task->log("printerAutoReadCounter is disabled by configuration.\n");
            return -2;
        }

        $itil_category_id = PluginIserviceTicket::getItilCategoryId(self::ITIL_CATEGORY_NAME);
        if (empty($itil_category_id)) {
            $task->log("The ITIL category '" . self::ITIL_CATEGORY_NAME . "' does not exist, it has to be created first.\n");
            return -1;
        }

        $printers = self::getWatchedPrinters($itil_category_id);
        if ($printers === null) {
            $task->log("Could not query the printers moved in the last " . self::WATCH_DAYS . " days.\n");
            return -1;
        }

        if (empty($printers)) {
            $task->log("No printer was moved to a partner in the last " . self::WATCH_DAYS . " days.\n");
            return 0;
        }

        $due_printers = array_filter($printers, [self::class, 'isReadingDue']);
        $task->log(count($printers) . " printer(s) under watch, " . count($due_printers) . " of them due for a reading.\n");

        if (empty($due_printers)) {
            return 0;
        }

        // The CSV data is read once for all the printers, the same way the global read counter view imports it.
        $csv_data      = PluginIserviceEmaintenance::getDataFromCsvs([], array_column($due_printers, 'spaceless_serial'));
        $ticket_count  = 0;
        $error_count   = 0;
        $skipped_count = 0;

        foreach ($due_printers as $printer_data) {
            $counters = self::getCountersFromCsvData($csv_data[$printer_data['spaceless_serial']] ?? null, $printer_data, $reason);
            if ($counters === null) {
                $skipped_count++;
                $task->log(self::getPrinterLogPrefix($printer_data) . ": $reason.\n");
                continue;
            }

            // createGlobalReadCounterTickets skips the printers refused by these checks silently, so they
            // are done here also, to be able to log the reason.
            $ticket_data = self::getReadCounterTicketData($printer_data, $counters, $itil_category_id);
            if (($reason = PluginIserviceTicket::getGlobalReadCounterRefusalReason($ticket_data)) !== null) {
                $skipped_count++;
                $task->log(self::getPrinterLogPrefix($printer_data) . ": $reason.\n");
                continue;
            }

            $has_open_ticket = PluginIserviceTicket::getLastIdForPrinterOrSupplier(0, $printer_data['id'], true) > 0;

            if (self::createReadCounterTicket($printer_data, $ticket_data)) {
                $ticket_count++;
                $task->log(self::getPrinterLogPrefix($printer_data) . ": reading " . ($printer_data['reads_done'] + 1) . "/" . self::READ_COUNT . " created ($counters[total2_black_field]/$counters[total2_color_field] at $counters[effective_date_field]).\n");
                if ($has_open_ticket) {
                    $task->log(self::getPrinterLogPrefix($printer_data) . ": has an open ticket, so the reading was not closed and does not count in the daily average.\n");
                }
            } else {
                $error_count++;
                $task->log(self::getPrinterLogPrefix($printer_data) . ": the read counter ticket could not be created.\n");
            }
        }

        $task->addVolume($ticket_count);
        $task->log("$ticket_count reading(s) created, $skipped_count skipped, $error_count error(s).\n");

        return $error_count > 0 ? -1 : ($ticket_count > 0 ? 1 : 0);
    }

    /**
     * Returns the printers moved to a partner in the last WATCH_DAYS days, with the data needed to decide
     * whether a reading is due and to create it. The watch list is not stored, it is derived on every run,
     * so a missed run is recovered automatically on the next one.
     *
     * The day 0 of the watch period is the work date of the delivery ticket ('livrare echipament') of the
     * movement, which is the date when the printer arrived to the partner.
     *
     * @param int $itil_category_id Id of the ITIL_CATEGORY_NAME category, the readings of this task.
     *
     * @return array|null Printer data by printer id, null on error.
     */
    protected static function getWatchedPrinters(int $itil_category_id): ?array
    {
        $expert_line_id           = IserviceToolBox::getExpertLineId();
        $black_white_printer_type = IserviceToolBox::getIdentifierByAttribute('PrinterType', 'alb-negru');
        $color_printer_type       = IserviceToolBox::getIdentifierByAttribute('PrinterType', 'color');

        if (empty($expert_line_id) || empty($black_white_printer_type) || empty($color_printer_type)) {
            return null;
        }

        $rows = PluginIserviceDB::getQueryResult(
            "
            SELECT p.id
                 , p.original_name printer_name
                 , " . PluginIservicePrinter::getSerialFieldForEM('p') . " spaceless_serial
                 , p.supplier_id
                 , p.supplier_name
                 , COALESCE(p.em_field, 0) em_field
                 , COALESCE(p.disable_em_field, 0) disable_em_field
                 , dt.effective_date_field move_date
                 , DATEDIFF(NOW(), dt.effective_date_field) days_since_move
                 , COALESCE(plct.effective_date_field, dt.effective_date_field) last_effective_date
                 , COALESCE(plct.total2_black_field, 0) last_total2_black
                 , COALESCE(plct.total2_color_field, 0) last_total2_color
                 , (
                     SELECT COUNT(*)
                     FROM glpi_plugin_iservice_tickets rt
                     JOIN glpi_items_tickets rit ON rit.tickets_id = rt.id AND rit.itemtype = 'Printer' AND rit.items_id = p.id
                     WHERE rt.is_deleted = 0
                       AND rt.status = " . Ticket::CLOSED . "
                       AND rt.itilcategories_id = $itil_category_id
                       AND rt.effective_date_field > dt.effective_date_field
                       AND (rt.total2_black_field IS NOT NULL OR rt.total2_color_field IS NOT NULL)
                   ) reads_done
            FROM glpi_plugin_iservice_movements m
            JOIN glpi_plugin_iservice_tickets dt ON dt.movement2_id_field = m.id AND dt.is_deleted = 0
            JOIN glpi_plugin_iservice_printers p ON p.id = m.items_id
            LEFT JOIN glpi_plugin_iservice_printers_last_closed_tickets plct ON plct.printers_id = p.id
            WHERE m.itemtype = 'Printer'
              AND m.moved = 1
              AND m.suppliers_id <> $expert_line_id
              AND p.is_deleted = 0
              AND p.printertypes_id IN ($black_white_printer_type, $color_printer_type)
              AND dt.effective_date_field > NOW() - INTERVAL " . self::WATCH_DAYS . " DAY
              AND dt.effective_date_field <= NOW()
            ORDER BY dt.effective_date_field ASC, m.id ASC
            "
        );

        // A printer can have more movements in the watched period, but only the last one counts, and
        // getQueryResult keys the rows by the id column, so the ordering above keeps the last movement.
        return is_array($rows) ? $rows : null;
    }

    /**
     * Decides whether a new reading is due for the printer: the automatic readings already made since the
     * movement are not enough, and the last reading of the printer (a manual one also counts here, to avoid
     * creating a reading right after one which was just made) or the movement itself is at least
     * READ_INTERVAL_DAYS days old.
     */
    protected static function isReadingDue(array $printer_data): bool
    {
        if ($printer_data['reads_done'] >= self::READ_COUNT) {
            return false;
        }

        $last_reading_date = max(strtotime($printer_data['last_effective_date']), strtotime($printer_data['move_date']));

        return floor((time() - $last_reading_date) / DAY_TIMESTAMP) >= self::READ_INTERVAL_DAYS;
    }

    /**
     * Validates the CSV data of a printer the same way the global read counter view does on import, and
     * returns the counter values to be saved.
     *
     * @param array|null $csv_row      CSV data of the printer, null if the printer is not in the CSV.
     * @param array      $printer_data Printer data from getWatchedPrinters.
     * @param string     $reason       Set to the reason of the refusal when null is returned.
     *
     * @return array|null Ticket counter values, null if the CSV data can not be used.
     */
    protected static function getCountersFromCsvData(?array $csv_row, array $printer_data, &$reason = ''): ?array
    {
        if (!empty($printer_data['disable_em_field'])) {
            $reason = 'the printer is excluded from E-maintenance, no automatic reading is possible';
            return null;
        }

        if (empty($printer_data['spaceless_serial'])) {
            $reason = 'the printer has no serial number to be identified in the E-maintenance CSV';
            return null;
        }

        if (empty($csv_row)) {
            $reason = 'the printer is not in the E-maintenance CSV' . (empty($printer_data['em_field']) ? ' (not an E-maintenance printer)' : '');
            return null;
        }

        if (!empty($csv_row['error'])) {
            $reason = 'error in the E-maintenance CSV: ' . $csv_row['error'];
            return null;
        }

        $counters = [];
        foreach (['effective_date_field', 'total2_black_field', 'total2_color_field'] as $field_name) {
            $value = $csv_row[$field_name] ?? '#empty#import#data#';
            if (is_array($value)) {
                $reason = "error in the E-maintenance CSV for $field_name: " . ($value['error'] ?? 'unknown error');
                return null;
            }

            $counters[$field_name] = $value === '#empty#import#data#' ? '' : $value;
        }

        // The black counter can be 0 on a printer which was just installed, so only a missing value counts.
        if ($counters['effective_date_field'] === '' || $counters['total2_black_field'] === '') {
            $reason = 'the E-maintenance CSV has no counter data for the printer';
            return null;
        }

        if (strtotime($counters['effective_date_field']) < strtotime('-' . self::MAX_CSV_AGE_DAYS . ' days')) {
            $reason = "the E-maintenance CSV data is older than " . self::MAX_CSV_AGE_DAYS . " days (from $counters[effective_date_field])";
            return null;
        }

        if (strtotime($counters['effective_date_field']) <= strtotime($printer_data['last_effective_date'])) {
            $reason = "the E-maintenance CSV data (from $counters[effective_date_field]) is not newer than the last reading (from $printer_data[last_effective_date])";
            return null;
        }

        return $counters;
    }

    /**
     * Returns the ticket data of a reading, the same way the global read counter view posts it.
     */
    protected static function getReadCounterTicketData(array $printer_data, array $counters, int $itil_category_id): array
    {
        return array_merge(
            $counters,
            [
                'items_id'             => ['Printer' => [$printer_data['id']]],
                '_suppliers_id_assign' => $printer_data['supplier_id'],
                'spaceless_serial'     => $printer_data['spaceless_serial'],
                'effective_date_old'   => $printer_data['last_effective_date'],
                'total2_black_old'     => $printer_data['last_total2_black'],
                'total2_color_old'     => $printer_data['last_total2_color'],
                'itilcategories_id'    => $itil_category_id,
                '_without_papers'      => 1,
                '_without_moving'      => 1,
            ]
        );
    }

    /**
     * Creates the read counter ticket of a printer.
     */
    protected static function createReadCounterTicket(array $printer_data, array $ticket_data): bool
    {
        return PluginIserviceTicket::createGlobalReadCounterTickets(
            ['printer' => [$printer_data['id'] => $ticket_data]],
            [
                'users_id_assign' => IserviceToolBox::getUserIdByName('Cititor'),
                // The reading counts in the daily average calculation only if it is closed.
                'status'          => Ticket::CLOSED,
                'name'            => _t('Automatic read counter after movement'),
                'content'         => sprintf(
                    _t('Automatic read counter %1$d of %2$d, %3$d day(s) after the movement to the partner.'),
                    $printer_data['reads_done'] + 1,
                    self::READ_COUNT,
                    $printer_data['days_since_move']
                ),
            ]
        ) > 0;
    }

    /**
     * The log entries of a cron task are truncated at 200 characters, so the prefix is kept short.
     */
    protected static function getPrinterLogPrefix(array $printer_data): string
    {
        return "Printer $printer_data[id] ($printer_data[printer_name])";
    }

}

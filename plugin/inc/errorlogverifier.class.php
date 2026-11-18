<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

class PluginIserviceErrorLogVerifier extends CommonDBTM
{

    public const LOG_FILE_NAME = 'php-errors.log';

    // On the first run (no state file yet) only the entries of this period are reported.
    public const FIRST_RUN_PERIOD = DAY_TIMESTAMP;

    // Limits the size of the email when many entries were logged.
    public const MAX_TYPES_WITH_SAMPLE = 20;
    public const MAX_SAMPLE_LENGTH     = 3000;

    // Each log entry starts with a line like "[2026-11-09 13:44:15] glpi.WARNING: ...".
    public const ENTRY_HEADER_PATTERN = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s*(.*)$/';

    public static function getTable($className = null): string
    {
        if (empty($className)) {
            $className = 'ErrorLogVerifier';
        }

        return parent::getTable($className);
    }

    public static function getTypeName($nb = 0): string
    {
        return _t('Error log verifier');
    }

    public static function cronInfo($name): ?array
    {
        switch ($name) {
        case 'mailErrorLogVerify':
            return [
                'description' => _t('Verifies php-errors.log and sends email about the new entries'),
            ];
        }

        return null;
    }

    /**
     * Cron action on mailErrorLogVerify
     *
     * @param $task
     *
     * @return int 0: nothing to do, 1: done with success, -1: done but not finished, -2: disabled
     * */
    public static function cronMailErrorLogVerify($task): int
    {
        if (empty(PluginIserviceConfig::getConfigValue('enabled_crons.mailErrorLogVerify'))) {
            $task->log("mailErrorLogVerify is disabled by configuration.\n");
            return -2;
        }

        $commentParts = explode("\n", $task->fields['comment'], 2);
        $targetEmail  = trim($commentParts[0]);

        if (empty($targetEmail)) {
            $task->log("Invalid email address: $targetEmail\n");
            return -1;
        }

        $logFile = GLPI_LOG_DIR . '/' . self::LOG_FILE_NAME;
        if (!file_exists($logFile)) {
            $task->log("$logFile does not exist, nothing was logged.\n");
            return 0;
        }

        if (!is_readable($logFile)) {
            $task->log("$logFile is not readable.\n");
            return -1;
        }

        clearstatcache(true, $logFile);
        $fileSize      = filesize($logFile);
        $firstLineHash = md5(self::getFirstLine($logFile));
        $state         = self::readState();
        $since         = null;

        if ($state === null) {
            $offset = 0;
            $since  = date('Y-m-d H:i:s', time() - self::FIRST_RUN_PERIOD);
        } elseif ($fileSize < $state['offset'] || $state['first_line_hash'] !== $firstLineHash) {
            // The log was rotated or emptied since the last run.
            $offset = 0;
        } else {
            $offset = $state['offset'];
        }

        [$entries, $newOffset] = self::readEntries($logFile, $offset, $fileSize, $since);

        if (empty($entries)) {
            self::writeState($newOffset, $firstLineHash);
            $task->log("No new entries in $logFile.\n");
            return 0;
        }

        $groups  = self::groupEntries($entries);
        $subject = sprintf('%s in %s (%s)', self::countText(count($entries), 'new entry', 'new entries'), self::LOG_FILE_NAME, self::countText(count($groups), 'error type', 'error types'));
        $result  = IserviceToolBox::sendMail($targetEmail, $subject, self::getEmailBody($entries, $groups));

        $task->addVolume(count($entries));
        if ($result !== true) {
            // The state is not saved, so the same entries are reported on the next run.
            $task->log("Error sending email to $targetEmail: $result\n");
            return -1;
        }

        self::writeState($newOffset, $firstLineHash);
        $task->log(count($entries) . " new entries, email sent to $targetEmail.\n");

        return 1;
    }

    private static function getFirstLine(string $logFile): string
    {
        $handle = fopen($logFile, 'r');
        if ($handle === false) {
            return '';
        }

        $firstLine = fgets($handle);
        fclose($handle);

        return $firstLine === false ? '' : $firstLine;
    }

    private static function readState(): ?array
    {
        $stateFile = PluginIserviceConfig::getConfigValue('error_log_verifier.state_file');
        if (!file_exists($stateFile)) {
            return null;
        }

        $state = json_decode(file_get_contents($stateFile), true);
        if (!is_array($state) || !isset($state['offset'], $state['first_line_hash'])) {
            return null;
        }

        return $state;
    }

    private static function writeState(int $offset, string $firstLineHash): void
    {
        file_put_contents(
            PluginIserviceConfig::getConfigValue('error_log_verifier.state_file'),
            json_encode(['offset' => $offset, 'first_line_hash' => $firstLineHash])
        );
    }

    /**
     * Reads the entries between $offset and $fileSize. An entry whose last line has no line break yet is still being written, so it is left for the next run.
     *
     * @return array [entries, offset after the last entry read]
     */
    private static function readEntries(string $logFile, int $offset, int $fileSize, ?string $since): array
    {
        $entries   = [];
        $newOffset = $offset;
        $handle    = fopen($logFile, 'r');
        if ($handle === false) {
            return [$entries, $newOffset];
        }

        fseek($handle, $offset);
        $current      = null;
        $currentStart = $offset;
        while (ftell($handle) < $fileSize && ($line = fgets($handle)) !== false) {
            if (!str_ends_with($line, "\n")) {
                // The entry is still being written: it is read again, whole, on the next run.
                if ($current !== null) {
                    $newOffset = $currentStart;
                    $current   = null;
                }

                break;
            }

            if (preg_match(self::ENTRY_HEADER_PATTERN, rtrim($line), $matches)) {
                if ($current !== null) {
                    $entries[] = $current;
                }

                $currentStart = $newOffset;
                $current      = ['time' => $matches[1], 'message' => $matches[2], 'text' => $line];
            } elseif ($current !== null) {
                $current['text'] .= $line;
            }

            $newOffset = ftell($handle);
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        fclose($handle);

        if ($since !== null) {
            $entries = array_values(array_filter($entries, fn ($entry) => $entry['time'] >= $since));
        }

        return [$entries, $newOffset];
    }

    /**
     * Groups the entries by error type: the message with the numbers replaced, plus the first iService frame of the backtrace.
     */
    private static function groupEntries(array $entries): array
    {
        $groups = [];
        foreach ($entries as $entry) {
            $lines = explode("\n", $entry['text']);

            // MySQL warnings have the warning itself (e.g. "1292: Truncated incorrect ...") on a separate line.
            $mysqlWarning = current(array_filter($lines, fn ($line) => preg_match('/^\d+: /', trim($line)))) ?: '';
            $frame        = current(array_filter($lines, fn ($line) => str_contains($line, 'plugins/iservice'))) ?: '';
            $signature    = preg_replace('/\d+/', 'N', $entry['message'] . ' ' . trim($mysqlWarning) . ' @ ' . trim($frame));

            if (!isset($groups[$signature])) {
                $groups[$signature] = [
                    'count'   => 0,
                    'first'   => $entry['time'],
                    'message' => $entry['message'],
                    'sample'  => $entry['text'],
                ];
            }

            $groups[$signature]['count']++;
            $groups[$signature]['last'] = $entry['time'];
        }

        uasort($groups, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $groups;
    }

    private static function countText(int $count, string $singular, string $plural): string
    {
        return $count . ' ' . ($count === 1 ? $singular : $plural);
    }

    private static function getEmailBody(array $entries, array $groups): string
    {
        global $CFG_GLPI;

        $body  = sprintf(
            "%s in %s on %s, logged between %s and %s.\n\n",
            self::countText(count($entries), 'new entry', 'new entries'),
            self::LOG_FILE_NAME,
            $CFG_GLPI['url_base'],
            $entries[0]['time'],
            end($entries)['time']
        );
        $body .= "Count | First | Last | Error\n";
        foreach ($groups as $group) {
            $body .= sprintf("%d | %s | %s | %s\n", $group['count'], $group['first'], $group['last'], $group['message']);
        }

        $body .= sprintf("\n\nOne entry of each error type (the first %d types):\n", self::MAX_TYPES_WITH_SAMPLE);
        foreach (array_slice($groups, 0, self::MAX_TYPES_WITH_SAMPLE) as $group) {
            $sample = mb_strlen($group['sample']) > self::MAX_SAMPLE_LENGTH ? mb_substr($group['sample'], 0, self::MAX_SAMPLE_LENGTH) . "\n[...]" : $group['sample'];
            $body  .= "\n----------\n" . rtrim($sample) . "\n";
        }

        return $body;
    }

}

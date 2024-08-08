<?php

// Imported from iService2, needs refactoring.
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

class PluginIserviceTask_DataIntegrityTest
{

    protected $snoozeData = [];

    protected static $testCases       = [];
    protected static $testResults     = null;
    protected static $lastTestResults = null;
    protected static $loadTimes       = [];

    public function __construct()
    {
        if (file_exists($this->getSnoozeFilePath())) {
            $this->snoozeData = unserialize(file_get_contents($this->getSnoozeFilePath()));
        }
    }

    public static function cronDataIntegrityTest($task)
    {
        if (empty(PluginIserviceConfig::getConfigValue('enabled_crons.data_integrity_test'))) {
            $task->log("Data Integrity Test is disabled by configuration.\n");
            return -2;
        }

        (new self())->execute();

        return 1;

    }

    function getTitle()
    {
        return _t('Self test');
    }

    static function getLoadTimes()
    {
        $result = [];
        foreach (self::$loadTimes as $load_time) {
            $result[] = sprintf("%s: %05.2fs", $load_time['mode'], $load_time['time']);
        }

        return $result;
    }

    function getTestCases()
    {
        if (empty(self::$testCases)) {
            foreach (glob(PluginIserviceConfig::getConfigValue('dataintegritytests.folder') . '/*.php') as $file_name) {
                self::$testCases[pathinfo($file_name)['filename']] = include $file_name;
            }
        }

        return self::$testCases;
    }

    public function getResultsForHeaderIcons(): array
    {
        $result         = [];
        $test_results   = PluginIserviceConfig::getConfigValue('enable_header_tests') ? $this->getTestResults() : ['warning' => [], 'error' => [], 'em_error' => []];
        $warning_count  = count($test_results['warning']);
        $error_count    = count($test_results['error']);
        $em_error_count = count($test_results['em_error']);
        if ($em_error_count > 0) {
            $result['em'] = [
                'color_class' => 'text-warning',
                'title' => $em_error_count . ' ' . _t('EM errors'),
            ];
        } else {
            $result['em'] = [
                'color_class' => '',
                'title' => _t('No E-Maintenance errors detected'),
            ];
        }

        $title = _t('Data integrity test returned');
        if ($warning_count + $error_count > 0) {
            $result['notEm'] = [
                'icon_class' => 'fa-exclamation-triangle badge',
                'color_class'    => ($error_count > 0 ? 'text-danger' : 'text-warning'),
                'title'    => $title . ' '
                    . ($warning_count > 0 ? $warning_count . _t(' warnings') : "")
                    . (($warning_count > 0 && $error_count > 0) ? _t(' and ') : "")
                    . ($error_count > 0 ? $error_count . _t(' errors') : ""),
                'badge'   => str_pad($warning_count + $error_count, 2, '0', STR_PAD_LEFT),
            ];
        } else {
            $result['notEm'] = [
                'icon_class' => 'fa-check-circle',
                'color_class'    => '',
                'title'    => $title . 'no errors',
                'badge'   => '',
            ];
        }

        return $result;
    }

    public function displayResults($mode = 'detailed')
    {
        $start_time             = microtime(true);
        $cache_time_minutes_ago = time() - $this->getCacheFileCreationTime();
        $cache_time_ago         = 'acum ' . (($cache_time_minutes_ago < 90) ? "$cache_time_minutes_ago secunde" : (round($cache_time_minutes_ago / 60) . ' minute'));
        switch ($mode) {
        case 'alert':
            $test_results = PluginIserviceConfig::getConfigValue('enable_header_tests') ? $this->getTestResults() : ['warning' => [], 'error' => [], 'alert' => []];
            if (count($test_results['alert']) < 1) {
                break;
            }

            echo "<table class='tab_cadre_central self-test-alert'>";
            echo "<tbody><tr><th class='warning'>";
            // echo "<div class='self-test-alert warning'>";
            echo "<ul>";
            echo "<li class='info'><b>ATENȚIE! S-au detectat următoarele probleme $cache_time_ago:</b></li>";
            foreach ($test_results['alert'] as $alert) {
                echo "<li>$alert</li>";
            }

            echo "</ul><ul>";
            if (count($test_results['error'])) {
                echo "<li class='error'>Vă recomandăm să <b>nu mai lucrați</b>, deoarece probabil datele introduse in ultima ora <b>se vor pierde</b>.</li>";
            }

            echo "<li class='info'>Vă rugăm să vă notați ultimele operațiuni efectuate și să <b>contactați administratorul (Zoli)</b>!</li>";
            echo "</ul>";
            echo "</th>";
            echo "<th class='warning'><i class='fa fa-exclamation-triangle fa-5x'></i></th>";
            echo "</tr></tbody>";
            echo "</table>";
            break;
        case 'em_alert':
            $test_results = PluginIserviceConfig::getConfigValue('enable_header_tests') ? $this->getTestResults() : ['em_alert' => []];
            if (count($test_results['em_alert']) < 1) {
                break;
            }

            echo "<table class='tab_cadre_central self-test-alert self-test-em-alert'>";
            echo "<tbody><tr><th class='warning'>";
            echo "<ul>";
            foreach ($test_results['em_alert'] as $alert) {
                echo "<li>$alert</li>";
            }

            echo "<li class='info'><b>Ultima verificare $cache_time_ago</b></li>";
            echo "</ul>";
            echo "</th>";
            echo "<th class='warning'><i class='fa fa-exclamation-triangle fa-2x'></i></th>";
            echo "</tr></tbody>";
            echo "</table>";
            break;
        default:
            if (!empty(PluginIserviceConfig::getConfigValue('enabled_crons.data_integrity_test'))) {
                $this->execute(true);
            }
            break;
        }

        self::$loadTimes[] = ['mode' => $mode, 'time' => microtime(true) - $start_time];
    }

    function execute($called_from_display = false)
    {
        if (!$called_from_display) {
            $this->displayResults();
            return;
        }

        $result_classes = [
            'ignored'  => 'fa fa-minus-circle',
            'info'     => 'fa fa-check-circle',
            'snoozed'  => 'fa fa-clock',
            'warning'  => 'fa fa-exclamation-triangle',
            'error'    => 'fa fa-exclamation-circle',
            'em_error' => 'fa fa-exclamation-triangle',
            'em_warning' => 'fa fa-exclamation-triangle',
            'em_info'  => 'fa fa-check-circle',
        ];
        $result_colors  = [
            'ignored'  => 'grey',
            'info'     => 'green',
            'snoozed'  => 'royalblue',
            'warning'  => 'orange',
            'error'    => 'red',
            'em_error' => 'orange',
            'em_warning' => 'orange',
            'em_info'  => 'green',
        ];

        if (IserviceToolBox::getInputVariable('custom_command')) {
            $this->getTestCases();
            $command = 'iservice_custom_command_' . IserviceToolBox::getInputVariable('command');
            if (function_exists($command)) {
                $command();
            }
        }

        $filter = IserviceToolBox::getInputVariable('filter');

        $test_results = $this->getTestResults();

        $html = new PluginIserviceHtml();
        echo $html->openForm(['method' => 'post']);

        if (false !== ($cache_time = $this->getCacheFileCreationTime())) {
            echo "<h3>Test results from ", date('H:i:s', $cache_time), " <input class='submit' type='submit' name='delete_cache' value='Refresh' /><br/>({$this->getCacheFilePath()})</h3>";
        }

        if (!empty($filter)) {
            if ($excluding_filter = $filter[0] === '!') {
                $filter = substr($filter, 1);
            }
        } else {
            $excluding_filter = null;
        }

        foreach ($test_results as $result_type => $results) {
            if ($excluding_filter !== null) {
                if ($excluding_filter xor strpos($result_type, $filter) !== 0) {
                    continue;
                }
            }

            if (in_array($result_type, ['', 'alert', 'em_alert', 'cases'])) {
                continue;
            }

            echo "<ul id='test-results-$result_type'>";
            foreach ($results as $result) {
                echo "<li><i class='$result_classes[$result_type]' style='color:$result_colors[$result_type];'></i> $result</li>";
            }

            echo "</ul>";
        }

        echo $html->closeForm();
    }

    function getTestResults()
    {
        if (self::$testResults === null) {
            $this->getTestResultsFromCache();
            $already_run = false;
        } else {
            $already_run = true;
        }

        if (empty(self::$testResults)) {
            self::$testResults = [
                'error'    => [],
                'em_error' => [],
                'em_warning' => [],
                'warning'  => [],
                'snoozed'  => [],
                'info'     => [],
                'em_info'  => [],
                'ignored'  => [],
                'alert'    => [],
                'em_alert' => [],
                'cases'    => [],
            ];
        }

        $test_cases = $this->getTestCases();

        foreach ($test_cases as $case_name => $case_params) {
            if (empty($case_params['test']['type'])) {
                continue;
            }

            /*
             * The 'alert type' and 'no cache' tests must be re-run even if loaded from cache.
             * But if they were re-run (not loaded from cache), do not run them again.
             * Variables created to easyer understand the logic:
             *   - if we have values (!empty(self::$testResults)) and we don't have to force the re-run, then continue
             *   OR
             *   - if we have values (!empty(self::$testResults)) and the re-run was already done, then continue
             */
            $alert_type    = !empty($case_params['test']['alert']) || !empty($case_params['test']['em_alert']);
            $no_cache_type = !empty($case_params['test']['no_cache']);
            // $force_rerun = $alert_type || $no_cache_type; /* 2021.04.08 - changed to enable caching for alert types also */
            $force_rerun = $no_cache_type;
            if (($already_run || !$force_rerun) && !empty(self::$testResults['cases'][$case_name])) {
                continue;
            }

            $case_start       = microtime(true);
            $case_result      = '';
            $case_result_type = '';

            $should_ignore = false;
            $formats       = [
                'h:m'  => 'H:i',
                'weekdays' => 'N'
            ];

            if (!empty($case_params['schedule'])) {
                $should_ignore          = false;
                $specifiedScheduleTypes = array_intersect(array_keys($case_params['schedule']), array_keys($formats));
                foreach ($specifiedScheduleTypes as $scheduleType) {
                    $scheduledDateValues = $case_params['schedule'][$scheduleType];
                    if (!$this->isInSchedule($scheduledDateValues, $scheduleType)) {
                        $should_ignore = true;
                        if (!empty($case_params['schedule']['display_last_result'])) {
                            $should_ignore = !empty(self::$lastTestResults[$case_name]);
                            if ($should_ignore) {
                                $case_result      = self::$lastTestResults[$case_name]['result'];
                                $case_result_type = self::$lastTestResults[$case_name]['result_type'];
                            }
                        } else {
                            $case_result = ($case_params['schedule']['ignore_text'][$scheduleType] ?? $case_params['schedule']['ignore_text'] ?? "$case_name ignored due to schedule");
                        }
                    }
                }
            }

            if ($this->isSnoozed($case_name)) {
                $case_result_type = 'snoozed';
                $case_result      =
                    $this->formatTestResultText([], $case_params['test']['snoozed_result']['summary_text'], '', ['snooze_time' => date('Y-m-d H:i:s', $this->isSnoozed($case_name))])
                    . " <span id='unsnooze_span_$case_name'><input class='secondary' type='submit' onclick='ajaxCall(\"" . PLUGIN_ISERVICE_DIR . "/ajax/manageDataintegrityTest.php?operation=unsnooze&id=$case_name\", \"\", function(message) { if (isNaN(message)) {alert(message);} else { $(\"#unsnooze_span_$case_name\").hide(); } }); return false;' style='padding: 2px 5px;' value='reactivate' /></span>";
                $should_ignore    = true;
            }

            if (!$should_ignore) {
                if (!empty($case_params['command_before']) && function_exists("iservice_custom_command_$case_params[command_before]")) {
                    $command = "iservice_custom_command_$case_params[command_before]";
                    $command();
                    $case_params = include PluginIserviceConfig::getConfigValue('dataintegritytests.folder') . "/$case_name.php";
                }

                if (empty($case_params['query'])) {
                    $case_query_result = '';
                } else {
                    $case_query_result = PluginIserviceDB::getQueryResult($case_params['query']);
                }

                $case_result_type = null;
                switch ($case_params['test']['type']) {
                case 'compare_query_count':
                    $this->processCompareQueryCountResult($case_result, $case_result_type, $case_query_result ?: [], $case_name, $case_params['test']);
                    break;
                case 'string_begins':
                    $this->processStringBeginsResult($case_result, $case_result_type, $case_params['string'] ?? '', $case_name, $case_params['test']);
                    break;
                case 'file_modified':
                    $file_modification_date = (empty($case_params['file_name']) || !file_exists($case_params['file_name'])) ? '0000-00-00' : date('Y-m-d', filemtime($case_params['file_name']));
                    $this->processFileModificationResult($case_result, $case_result_type, $file_modification_date, $case_name, $case_params['test']);
                    break;
                case 'read_file':
                    if (empty($case_params['file_name']) || !file_exists($case_params['file_name'])) {
                        $file_data = [];
                    } else {
                        $file_contents = file_get_contents($case_params['file_name']);
                        $data          = explode('###', $file_contents, 3);
                        if (count($data) === 3) {
                            $file_creation_date = $data[1];
                            $file_contents      = $data[2];
                        } else {
                            $file_creation_date = date('Y-m-d H:i:s', filectime($case_params['file_name']));
                        }

                        $old_error_reporting_level = error_reporting(error_reporting() ^ E_NOTICE);
                        if ($file_contents === 'b:0') {
                            $file_data['content'] = false;
                        } elseif (false === ($file_data = @unserialize($file_contents))) {
                            $file_data['content'] = $file_contents;
                        }

                        error_reporting($old_error_reporting_level);

                        $file_data['file_creation_date']     = $file_creation_date;
                        $file_data['file_modification_date'] = date('Y-m-d H:i:s', filemtime($case_params['file_name']));
                    }

                    $this->processReadFileResult($case_result, $case_result_type, $file_data, $case_name, $case_params['test']);
                    break;
                default:
                    break;
                }
            }

            global $CFG_PLUGIN_ISERVICE;

            if (!empty($case_result_type)) {
                $last_checked = empty($should_ignore) ? date('Y-m-d H:i:s') : (self::$lastTestResults[$case_name]['last_checked'] ?? 'never');

                $clear_data      = "<span id='delete_last_saved_data_span_$case_name'><input class='secondary' type='submit' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageDataintegrityTest.php?operation=delete_last_result&id=$case_name\", \"\", function(message) { if (isNaN(message)) {alert(message);} else { $(\"#delete_last_saved_data_span_$case_name\").hide(); } }); return false;' value='clear cached data' /></span>";
                $additional_info = sprintf(
                    "<span class='hide-for-non-admin'>%s %s <pre id='test_results_$case_name' style='display:none'>%s</pre></span>",
                    !empty($case_params['schedule']['display_last_result']) ? "(last checked at <b>$last_checked</b>) $clear_data" : '',
                    in_array($case_result_type, ['warning', 'error']) && ($case_params['enable_snooze'] ?? null) ? $this->getSnoozeHtml($case_name, $case_params['enable_snooze']) : '',
                    $case_params['query'] ?? $case_params['string'] ?? $case_params['file_name'] ?? ''
                );

                $case_result = str_replace('{additional_info}', $additional_info, $case_result);

                self::$lastTestResults[$case_name] = [
                    'result_type'  => $case_result_type,
                    'result'       => $case_result,
                    'last_checked' => $last_checked,
                ];

                $case_result = sprintf(
                    "<a class='hide-for-non-admin' href='javascript:void(null);' onclick='$(\"#test_results_$case_name\").toggle(); return false;'>[%05.2fs]</a> %s",
                    microtime(true) - $case_start,
                    $case_result
                );
            }

            self::$testResults['cases'][$case_name]           = $case_result_type;
            self::$testResults[$case_result_type][$case_name] = $case_result;
            if (!empty($case_params['test']['alert'])) {
                if (in_array($case_result_type, ['error', 'warning'])) {
                    self::$testResults['alert'][$case_name] = $case_result;
                } else {
                    unset(self::$testResults['alert'][$case_name]);
                }
            }

            if (!empty($case_params['test']['em_alert'])) {
                if (in_array($case_result_type, ['em_error', 'em_warning'])) {
                    self::$testResults['em_alert'][$case_name] = $case_result;
                } else {
                    unset(self::$testResults['em_alert'][$case_name]);
                }
            }
        }

        $this->cacheTestResults();

        return self::$testResults;
    }

    public function isInSchedule($scheduledDateValues, $scheduleType): bool
    {
        switch ($scheduleType) {
        case 'h:m':
            foreach ($scheduledDateValues as $scheduledDateValue) {
                list($hour, $minute) = explode(':', $scheduledDateValue);

                if (strpos($hour, '-') !== false) {
                    list($hourStart, $hourEnd) = explode('-', $hour);
                    $hourMatch                 = $hourStart <= date('H') && $hourEnd >= date('H');
                } else {
                    $hourMatch = $hour == date('H') || $hour == '*';
                }

                if (strpos($minute, '-') !== false) {
                    list($minuteStart, $minuteEnd) = explode('-', $minute);
                    $minuteMatch                   = $minuteStart <= date('i') && $minuteEnd >= date('i');
                } else {
                    $minuteMatch = $minute == date('i') || $minute == '*';
                }

                return $hourMatch && $minuteMatch;
            }

        case 'weekdays':
            return in_array(date('w'), $scheduledDateValues);
        default:
            return false;
        }
    }

    function processCompareQueryCountResult(&$result, &$result_type, $case_result, $case_name, $case_test_params)
    {
        if (count($case_result) > 0) {
            if (empty($case_test_params['positive_result']['result_type'])) {
                $case_test_params['positive_result']['result_type'] = 'warning';
            }

            if (empty($case_test_params['positive_result']['summary_text'])) {
                $case_test_params['positive_result']['summary_text'] = "$case_name query has non 0 result";
            }

            if (empty($case_test_params['positive_result']['iteration_text'])) {
                $case_test_params['positive_result']['iteration_text'] = "";
            }

            $result      = $this->formatTestResultText($case_result, $case_test_params['positive_result']['summary_text'], $case_test_params['positive_result']['iteration_text']);
            $result_type = $case_test_params['positive_result']['result_type'];
        } else {
            if (empty($case_test_params['zero_result']['result_type'])) {
                $case_test_params['zero_result']['result_type'] = 'info';
            }

            if (empty($case_test_params['zero_result']['summary_text'])) {
                $case_test_params['zero_result']['summary_text'] = "$case_name query returns no rows";
            }

            if (empty($case_test_params['zero_result']['iteration_text'])) {
                $case_test_params['zero_result']['iteration_text'] = "";
            }

            $result      = $this->formatTestResultText($case_result, $case_test_params['zero_result']['summary_text'], $case_test_params['zero_result']['iteration_text']);
            $result_type = $case_test_params['zero_result']['result_type'];
        }
    }

    function processStringBeginsResult(&$result, &$result_type, $string, $case_name, $case_test_params)
    {
        if (empty($case_test_params['parameters'])) {
            $result      = "Parameters cannot be empty for case $case_name";
            $result_type = 'error';
            return;
        }

        if (empty($string) || strpos($string, $case_test_params['parameters']) !== 0) {
            if (empty($case_test_params['negative_result']['result_type'])) {
                $case_test_params['negative_result']['result_type'] = 'info';
            }

            if (empty($case_test_params['negative_result']['summary_text'])) {
                $case_test_params['negative_result']['summary_text'] = "String $string does not begin with $case_test_params[parameters]";
            }

            $result      = $this->substituteValues($case_test_params['negative_result']['summary_text'], ['string' => $string, 'parameters' => $case_test_params['parameters']]);
            $result_type = $case_test_params['negative_result']['result_type'];
        } else {
            if (empty($case_test_params['positive_result']['result_type'])) {
                $case_test_params['positive_result']['result_type'] = 'info';
            }

            if (empty($case_test_params['positive_result']['summary_text'])) {
                $case_test_params['positive_result']['summary_text'] = "String $string begins with $case_test_params[parameters]";
            }

            $result      = $this->substituteValues($case_test_params['positive_result']['summary_text'], ['string' => $string, 'parameters' => $case_test_params['parameters']]);
            $result_type = $case_test_params['positive_result']['result_type'];
        }
    }

    function processReadFileResult(&$result, &$result_type, $file_data, $case_name, $case_test_params, $enable_snooze = false)
    {
        if (empty($file_data) || empty($file_data['content'])) {
            if (empty($case_test_params['zero_result']['result_type'])) {
                $case_test_params['zero_result']['result_type'] = 'info';
            }

            if (empty($case_test_params['zero_result']['summary_text'])) {
                $case_test_params['zero_result']['summary_text'] = "$case_name file is empty";
            }

            if (empty($case_test_params['zero_result']['iteration_text'])) {
                $case_test_params['zero_result']['iteration_text'] = "";
            }

            $result      = $this->formatTestResultText([], $case_test_params['zero_result']['summary_text'], $case_test_params['zero_result']['iteration_text'], $file_data);
            $result_type = $case_test_params['zero_result']['result_type'];
        } else {
            if (empty($case_test_params['positive_result']['result_type'])) {
                $case_test_params['positive_result']['result_type'] = 'warning';
            }

            if (empty($case_test_params['positive_result']['summary_text'])) {
                $case_test_params['positive_result']['summary_text'] = "$case_name file is not empty";
            }

            if (empty($case_test_params['positive_result']['iteration_text'])) {
                $case_test_params['positive_result']['iteration_text'] = "";
            }

            $result      = $this->formatTestResultText([], $case_test_params['positive_result']['summary_text'], $case_test_params['positive_result']['iteration_text'], $file_data);
            $result_type = $case_test_params['positive_result']['result_type'];
        }
    }

    function processFileModificationResult(&$result, &$result_type, $file_modification_date, $case_name, $case_test_params, $enable_snooze = false)
    {
        if (empty($case_test_params['compare'])) {
            $case_test_params['compare'] = '';
        }

        if ($file_modification_date === '0000-00-00') {
            $comparison_result = 'no_file_result';
            if (empty($case_test_params['no_file_result']['result_type'])) {
                $case_test_params['no_file_result']['result_type'] = 'error';
            }

            if (empty($case_test_params['no_file_result']['summary_text'])) {
                $case_test_params['no_file_result']['summary_text'] = "$case_name file does not exist";
            }
        } elseif ($file_modification_date < $case_test_params['compare']) {
            $comparison_result = 'negative_result';
        } elseif ($file_modification_date > $case_test_params['compare']) {
            $comparison_result = 'positive_result';
        } else {
            $comparison_result = 'zero_result';
        }

        $result      = ($case_test_params[$comparison_result]['summary_text'] ?? "File was last modified on $file_modification_date") . " {additional_info}";
        $result_type = $case_test_params[$comparison_result]['result_type'] ?? 'info';
    }

    function formatTestResultText($results, $summary_format, $iteration_format = '', $summary_params = [])
    {
        $result_text = str_replace("{count}", count($results), $summary_format);
        if (!empty($summary_params)) {
            $result_text = $this->substituteValues($result_text, $summary_params);
        }

        $result_text .= " {additional_info}";
        if (!empty($iteration_format)) {
            $result_text .= "<ul>";
            foreach ($results as $result) {
                $result_text .= "<li class='highlight-on-hover'>" . $this->substituteValues($this->getIterationFormat($iteration_format, $result), $result) . "</li>";
            }

            $result_text .= "</ul>";
        }

        return $result_text;
    }

    function deleteLastResult($case_name)
    {
        if (self::$lastTestResults === null) {
            $this->getTestResultsFromCache();
        }

        unset(self::$lastTestResults[$case_name]);
        $this->saveLastResults();
        $this->getTestResultsFromCache();
    }

    protected function getTestResultsFromCache()
    {
        $last_results_file_path = $this->getLastResultsFilePath();
        if (file_exists($last_results_file_path)) {
            self::$lastTestResults = unserialize(file_get_contents($last_results_file_path));
        } else {
            self::$lastTestResults = [];
        }

        $cache_file_path = $this->getCacheFilePath();
        $delete_cache    = IserviceToolBox::getInputVariable('delete_cache');
        if (empty($delete_cache)) {
            $args         = getopt('', ['delete_cache::']);
            $delete_cache = $args['delete_cache'] ?? null;
        }

        self::$testResults = [];
        if (file_exists($cache_file_path)) {
            if (!$delete_cache && time() - $this->getCacheFileCreationTime() < PluginIserviceConfig::getConfigValue('dataintegritytests.cache_timeout')) {
                return self::$testResults = unserialize(file_get_contents($cache_file_path));
            } else {
                unlink($cache_file_path);
            }
        }

        return false;
    }

    protected function cacheTestResults()
    {
        if (!file_exists($this->getCacheFilePath())) {
            file_put_contents($this->getCacheTimeFilePath(), date('Y.m.d H:i:s'));
        }

        file_put_contents($this->getCacheFilePath(), serialize(self::$testResults));
        $this->saveLastResults();
    }

    protected function saveLastResults()
    {
        file_put_contents($this->getLastResultsFilePath(), serialize(self::$lastTestResults));
    }

    protected function getCacheFilePath()
    {
        return PLUGIN_ISERVICE_CACHE_DIR . "/test_results";
    }

    protected  function getCacheTimeFilePath()
    {
        return $this->getCacheFilePath() . '_time';
    }

    protected function getLastResultsFilePath()
    {
        return PLUGIN_ISERVICE_CACHE_DIR . "/last_results";
    }

    protected function getCacheFileCreationTime()
    {
        if (!file_exists($this->getCacheTimeFilePath())) {
            return false;
        } else {
            return filemtime($this->getCacheTimeFilePath());
        }
    }

    public function snoozeTestCase($testCase, $seconds)
    {
        $this->snoozeData[$testCase] = strtotime("+ $seconds seconds");
        $this->saveSnoozeData();
    }

    public function unSnoozeTestCase($testCase)
    {
        unset($this->snoozeData[$testCase]);
        $this->saveSnoozeData();
    }

    protected function getSnoozeFilePath()
    {
        return PLUGIN_ISERVICE_CACHE_DIR . "/test_snoozes";
    }

    protected function isSnoozed($testCase)
    {
        return empty($this->snoozeData[$testCase]) ? false : (time() < $this->snoozeData[$testCase] ? $this->snoozeData[$testCase] : false);
    }

    protected function saveSnoozeData()
    {
        file_put_contents($this->getSnoozeFilePath(), serialize($this->snoozeData));
    }

    protected function getSnoozeHtml($case_name, $enable_snooze = true)
    {
        global $CFG_PLUGIN_ISERVICE;

        if (!$enable_snooze) {
            return '';
        }

        $snooze_data = explode(' ', $enable_snooze, 2);
        if (is_numeric($snooze_data[0])) {
            $default_snooze_time = intval($snooze_data[0]);
        } else {
            $default_snooze_time = 1;
        }

        $snooze_unit = count($snooze_data) > 1 ? $snooze_data[1] : $enable_snooze;

        if (!is_string($snooze_unit) || !in_array($snooze_unit, ['seconds', 'minutes', 'hours', 'days'])) {
            $snooze_unit = 'hours';
        }

        return "<span id='snooze_span_$case_name'><input class='secondary' type='submit' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageDataintegrityTest.php?operation=snooze&id=$case_name&snooze=\" + $(\"#snooze_$case_name\").val() + \" $snooze_unit\", \"\", function(message) { if (isNaN(message)) {alert(message);} else { $(\"#snooze_span_$case_name\").hide(); } }); return false;' style='padding: 2px 5px;' value='snooze' /> for <input type='text' id='snooze_$case_name' style='height: 1.1em;width: 1em;' value='$default_snooze_time'> $snooze_unit</span>";
    }

    protected function getIterationFormat($iteration_format, $result)
    {
        if (is_array($iteration_format)) {
            foreach ($iteration_format as $condition => $format) {
                if (eval($this->substituteValues($condition, $result))) {
                    return $format;
                }
            }
        } else {
            return $iteration_format;
        }
    }

    protected function substituteValues($string, $params, $opening_param_separator = '[', $closing_param_separator = ']')
    {
        foreach ($params as $param_name => $param_value) {
            $string = str_replace("$opening_param_separator$param_name$closing_param_separator", $param_value, $string);
        }

        return $string;
    }

}

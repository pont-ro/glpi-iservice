<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceView {

    const FILTERTYPE_CHECKBOX = 'checkbox';
    const FILTERTYPE_DATE = 'date';
    const FILTERTYPE_DATETIME = 'datetime';
    const FILTERTYPE_HIDDEN = 'hidden';
    const FILTERTYPE_INT = 'int';
    const FILTERTYPE_LABEL = 'label';
    const FILTERTYPE_SELECT = 'select';
    const FILTERTYPE_TEXT = 'text';
    const FILTERTYPE_TICKET_STATUS = 'ticket_status';
    const FILTERTYPE_USER = 'user';
    const WIDGET_THIS_WEEK = 'this_week';
    const WIDGET_LAST_WEEK = 'last_week';
    const WIDGET_THIS_MONTH = 'this_month';
    const WIDGET_LAST_MONTH = 'last_month';
    const WIDGET_LAST_6_MONTH = 'last_6_month';

    static protected $machine_name = '';
    static protected $rightname_base = 'plugin_iservice_view_';
    protected $table_prefix = '';
    protected $table_suffix = '';
    protected $widgets = array();
    // Setting variables
    protected $prefix;
    protected $name;
    protected $description;
    protected $instant_display;
    protected $postfix;
    protected $query;
    protected $params;
    protected $id_field;
    protected $itemtype;
    protected $sub_view;
    protected $default_limit;
    protected $mass_actions;
    protected $class;
    protected $style;
    protected $show_filter_buttons;
    protected $filter_buttons_align;
    protected $show_limit;
    protected $show_export;
    protected $row_class;
    protected $insert_empty_rows;
    protected $use_cache;
    protected $cache_timeout;
    protected $cache_timeout_warning;
    protected $cache_query;
    protected $filters;
    protected $columns;
    protected $actions;
    protected $settings_defaults = array(
        'prefix' => '',
        'name' => '',
        'description' => '',
        'instant_display' => true,
        'postfix' => '',
        'query' => '',
        'params' => array(),
        'id_field' => 'id',
        'itemtype' => '###error###', // this should be overriden as soon as possible!
        'sub_view' => false,
        'default_limit' => 0,
        'mass_actions' => array(),
        'class' => array(),
        'style' => array(),
        'show_filter_buttons' => true,
        'filter_buttons_align' => 'left',
        'show_limit' => true,
        'show_export' => false,
        'row_class' => '',
        'insert_empty_rows' => false,
        'use_cache' => false,
        'ignore_control_hash' => false,
        'cache_timeout' => 600, // 10 min in seconds
        'cache_timeout_warning' => null,
        'cache_query' => '',
        'filters' => array(),
        'columns' => array(),
        'actions' => array(),
    );
    // Request variables
    protected $reset;
    protected $limit;
    protected $detail;
    protected $order_by;
    protected $order_dir;
    protected $detail_row;
    protected $detail_key;
    protected $from_cache;
    protected $export_type;
    protected $export_format;
    protected $filter_description;
    protected $request_variable_defaults = array(
        'reset' => null,
        'limit' => 0,
        'detail' => '',
        'order_by' => '',
        'order_dir' => 'ASC',
        'detail_row' => 0,
        'detail_key' => 0,
        'from_cache' => 0,
        'export_type' => '',
        'export_format' => 'csv',
        'filter_description' => '',
    );
    // Internal variables
    protected $exporting = false;
    protected $export_data = array();
    protected $query_count = 0;
    protected $control_hash = '';
    protected $settings_loaded = false;
    protected $detail_displaying = false;
    protected $ignore_control_hash = false;
    protected $visible_columns_count = 0;
    protected $import_data = null;

    function __construct($load_settings = true, $table_prefix = '', $table_suffix = '') {
        $this->table_prefix = $table_prefix;
        $this->table_suffix = $table_suffix;
        if ($load_settings) {
            $this->loadSettings();
        }
    }

    static function getName() {
        return "Override getName to statically get the name of this view";
    }

    static function compareByOrder($view1, $view2) {
        if (!isset($view1::$order)) {
            return !isset($view2::$order) ? 0 : -1;
        }
        if (!isset($view2::$order)) {
            return 1;
        }
        return $view1::$order > $view2::$order ? 1 : -1;
    }

    static function getShortenedDisplay($text, $length = 50, $offset = 0) {
        if (strlen($text) <= $length) {
            return $text;
        }
        return "<span title='$text'>" . substr($text, $offset, $length - 3) . "...</span>";
    }

    /**
     * @return \PluginIserviceView
     */
    static function createFromSettings($settings, $table_prefix = '', $table_suffix = '') {
        $class = get_called_class();
        $view = new $class(false, $table_prefix, $table_suffix);
        $view->loadSettings(true, $settings);
        return $view;
    }

    function getUrlSafeString($string) {
        return urlencode($string);
    }

    function getMachineName() {
        $class_name = get_class($this);
        return strtolower(strpos($class_name, "PluginIserviceView_") === 0 ? substr($class_name, strlen("PluginIserviceView_")) : $class_name);
    }

    function getRightName() {
        return self::$rightname_base . $this->getMachineName();
    }

    function getRequestArrayName($add_detail_level = 0) {
        return $this->getMachineName() . ($this->detail_displaying + $add_detail_level);
    }

    function getHeadingLevel() {
        return $this->detail_displaying + 1;
    }

    function getTitle() {
        return empty($this->name) ? __('Error') : $this->name;
    }

    public function customize($params = array()) {

    }

    protected function getSettings() {
        return array();
    }

    protected static function inProfileArray($profiles) {
        return PluginIserviceCommon::inProfileArray(func_get_args());
    }

    protected function getWidgets() {
        if (empty($this->widgets)) {
            $this_week_start = date("Y-m-d", strtotime("-" . (date('w') - 1) . " days"));
            $this_week_start_time = strtotime($this_week_start);
            $this_week_end = date("Y-m-d", strtotime("+6 days", $this_week_start_time));
            $last_week_start = date("Y-m-d", strtotime("-7 days", $this_week_start_time));
            $last_week_end = date("Y-m-d", strtotime("-1 days", $this_week_start_time));
            $this_month_start = date("Y-m-01");
            $this_month_end = date("Y-m-t");
            $last_month_start = date("Y-m-d", strtotime("first day of -1 month", strtotime(date("Y-m-01"))));
            $last_month_end = date("Y-m-t", strtotime("first day of -1 month", strtotime(date("Y-m-01"))));
            $last_6month_start = date("Y-m-d", strtotime("first day of -5 month", strtotime(date("Y-m-01"))));
            $this->widgets = array(
                self::WIDGET_THIS_WEEK => "<input type='button' class='submit' value='Săptămâna curentă' onclick=\"setDateFilters($(this), '$this_week_start', '$this_week_end');\" />",
                self::WIDGET_LAST_WEEK => "<input type='button' class='submit' value='Săptămâna trecută' onclick=\"setDateFilters($(this), '$last_week_start', '$last_week_end');\" />",
                self::WIDGET_THIS_MONTH => "<input type='button' class='submit' value='Luna curentă' onclick=\"setDateFilters($(this), '$this_month_start', '$this_month_end');\" />",
                self::WIDGET_LAST_MONTH => "<input type='button' class='submit' value='Luna precedentă' onclick=\"setDateFilters($(this), '$last_month_start', '$last_month_end');\" />",
                self::WIDGET_LAST_6_MONTH => "<input type='button' class='submit' value='Ultimele 6 luni' onclick=\"setDateFilters($(this), '$last_6month_start', '$this_month_end');\" />",
            );
        }
        return $this->widgets;
    }

    protected function loadSettings($reload = false, $settings = null) {
        if ($this->settings_loaded && !$reload) {
            return;
        }
        if (empty($settings)) {
            $settings = $this->getSettings();
        }
        $this->settings_defaults['itemtype'] = $this->getMachineName();
        foreach ($this->settings_defaults as $setting_name => $setting_default_value) {
            $this->$setting_name = is_array($setting_default_value) ? $this->getArraySetting($settings, $setting_name, $setting_default_value) : $this->getSetting($settings, $setting_name, $setting_default_value);
        }

        $this->mass_actions_column = 0;

        $this->control_hash = md5(serialize($settings['query']));
        $this->settings_loaded = true;
    }

    protected function loadRequestVariables() {
        $request_array = PluginIserviceCommon::getArrayInputVariable($this->getRequestArrayName(), array());
        foreach ($this->request_variable_defaults as $variable_name => $variable_default_value) {
            $this->$variable_name = isset($request_array[$variable_name]) ? $request_array[$variable_name] : $variable_default_value;
        }
    }

    protected function adjustQueryOrderBy() {
        if (empty($this->order_by)) {
            foreach ($this->columns as $field_name => $column) {
                if (isset($column['default_sort'])) {
                    $this->order_by = $field_name;
                    $this->order_dir = $column['default_sort'];
                    break;
                }
            }
        }

        if (!empty($this->order_by)) {
            $this->query .= " ORDER BY $this->order_by $this->order_dir";
        }
        $this->params['order_by'] = $this->order_by;
        $this->params['order_dir'] = $this->order_dir;
    }

    protected function getQueryCount() {
        global $DB;
        if (($count_result = $DB->query("select count(*) as count from ($this->query) t")) === false) {
            echo $DB->error();
            return;
        }
        while (($row = $DB->fetchAssoc($count_result)) !== null) {
            return $row['count'];
        }
    }

    protected function adjustQueryLimit() {
        if (empty($this->limit)) {
            $this->limit = $this->default_limit;
        }
        if (!empty($this->limit) && (!$this->exporting || $this->export_type == 'visible')) {
            $this->query .= " LIMIT $this->limit";
        }
    }

    protected function displayFilters() {
        foreach ($this->mass_actions as &$mass_action) {
            $this->mass_actions_column |= self::ensureArrayKey($mass_action, 'visible', true) ? 1 : 0;
        }
        if (empty($this->filters)) {
            return true;
        }

        if (!empty($this->filters)) {
            $filter = "<div class='view-filter noprint" . ($this->detail_displaying ? " detail" : "") . "'>";
            ob_flush();
            ob_start();
            if (isset($this->filters['prefix'])) {
                $filter .= $this->filters['prefix'];
            }
            $request_values = PluginIserviceCommon::getArrayInputVariable($this->getRequestArrayName(), array());
            foreach ($this->filters as $filter_name => $filter_data) {
                if (in_array($filter_name, $this->getIgnoredFilterNames())) {
                    continue;
                }

                if (isset($filter_data['header']) && isset($this->columns[$filter_data['header']])) {
                    $filter_in_header = true;
                    if (!isset($this->columns[$filter_data['header']]['filter'])) {
                        $this->columns[$filter_data['header']]['filter'] = '';
                    }
                } else {
                    $filter_in_header = false;
                }

                $filter_widget = $this->getFilterWidget($filter_data, $filter_name, $filter_in_header ? 'header' : 'normal', $request_values);
                if ($filter_in_header) {
                    $this->columns[$filter_data['header']]['filter'] .= (empty($filter_data['no_break_before']) ? '<br/>' : '') . "$filter_widget";
                } else {
                    $filter .= $filter_widget;
                }
            }

            $filter .= isset($this->filters['postfix']) ? $this->filters['postfix'] : '';
            $filter .= "</div>"; // view-filter
            ob_end_clean();
        }
        if (!$this->exporting) {
            echo $filter;
        }
        return true;
    }

    protected function getFilterWidget($filter_data, $filter_name, $filter_position = 'normal', $params = array()) {
        global $CFG_GLPI;
        $filter_in_header = $filter_inline = false;
        switch ($filter_position) {
            case 'inline':
                $filter_inline = true;
                break;
            case 'header':
                $filter_in_header = true;
                break;
        }
        if ($this->use_cache && !empty($filter_data['cache_override'])) {
            foreach ($filter_data['cache_override'] as $attr_name => $attr_value) {
                $filter_data[$attr_name] = $attr_value;
            }
        }
        $required = !empty($filter_data['required']);
        $filter_reset = $this->reset !== null || PluginIserviceCommon::getInputVariable('reset') !== null;
        $filter_value = ($filter_reset && !$required) ? null : ($params[$filter_name] ?? $filter_data['default'] ?? null);
        if ($required && empty($filter_value)) {
            ob_end_clean();
            echo "<div class='error'>";
            echo isset($filter_data['required_error']) ? $filter_data['required_error'] : (__('Empty') . ' ' . $filter_data['caption']);
            echo "</div>";
            die;
        }

        if ($this->import_data !== null && isset($filter_data['import']['id']) && isset($filter_data['import']['index'])) {
            $import_data = $this->import_data[$filter_data['import']['id']] ?? [];

            $error_text = empty($import_data['error']) ? false : $import_data['error'];
            $warning_text = empty($import_data['warning']) ? false : implode("\n", $import_data['warning']);

            if (empty($import_data)) {
                $estimate_text = $this->evalIfFunction($filter_data['import']['estimate_text'] ?? '', ['param_data' => $params]);
                $filter_value = '#empty#import#data#';
            } elseif (!empty($import_data[$filter_data['import']['index']])) {
                if (!empty($import_data[$filter_data['import']['index']]['error'])) {
                    $additional_info = $this->evalIfFunction($filter_data['import']['error_text'] ?? '', ['param_data' => $params]);
                    $error_text = $import_data[$filter_data['import']['index']]['error'] . (empty($additional_info) ? '' : "\n$additional_info");
                    $filter_value = '#empty#import#data#';
                } else {
                    $filter_value = $import_data[$filter_data['import']['index']];
                    if ($import_data['data_luc'] < date('Y-m-d', strtotime('-7days'))) {
                        $estimate_text = "Datele din CSV sunt mai vechi de 7 zile (din " . date('Y-m-d', strtotime($import_data['data_luc'])) . '). ' . $this->evalIfFunction($filter_data['import']['estimate_text'] ?? '', ['param_data' => $params]);
                        $filter_value = '#empty#import#data#';
                    } elseif (!empty($filter_data['min_value']) && $filter_value !== '#empty#import#data#' && $filter_value < $filter_data['min_value']) {
                        $error_hint = $this->evalIfFunction($filter_data['import']['minimum_error_hint'] ?? "Click pentru a seta", ['param_data' => $params]);
                        $error_text = "Valoarea minimă: $filter_data[min_value] ($error_hint)";
                        $error_handler = $this->evalIfFunction(
                            $filter_data['import']['minimum_error_handler'] ?? "onclick='$(this).parent().find(\"input\").val(\"$filter_data[min_value]\");$(this).hide();'",
                            ['param_data' => $params]
                        );
                    }
                }
            }

            $filter_data['pre_widget'] = ($filter_data['pre_widget'] ?? '') . "<div class='no-wrap' style='position:relative; display: inline-block'>";
            $filter_data['post_widget'] = "</div>" . ($filter_data['post_widget'] ?? '');

            foreach (['error', 'warning', 'estimate'] as $badge_type) {
                $text_variable = $badge_type . '_text';
                $handler_variable = $badge_type . '_handler';
                if (!empty($$text_variable)) {
                    if (empty($$handler_variable)) {
                        $$handler_variable = $this->evalIfFunction($filter_data['import'][$handler_variable] ?? '', ['param_data' => $params]);
                    }
                    $filter_data['post_widget'] = $this->generateWidgetBadge(
                            $badge_type,
                            "$params[row_id]-" . PluginIserviceCommon::getHtmlSanitizedValue($filter_data['name']),
                            $$text_variable,
                            $$handler_variable,
                            empty($$handler_variable)
                        ) . $filter_data['post_widget'];
                }
            }
        }

        if (($filter_value == null || $filter_value == '') && isset($filter_data['empty_value'])) {
            $filter_value = $filter_data['empty_value'];
        } elseif ($filter_value === '#empty#import#data#') {
            $filter_value = '';
        }

        $filter_data['old_type'] = $filter_data['type'];
        if (!self::ensureArrayKey($filter_data, 'visible', true)) {
            $filter_data['type'] = self::FILTERTYPE_HIDDEN;
        }

        $filter_widget = '';
        if ($filter_data['visible']) {
            if (isset($filter_data['pre_widget'])) {
                $filter_widget .= $filter_data['pre_widget'];
            }
            if ($filter_in_header) {
                if (isset($filter_data['header_caption'])) {
                    $filter_widget .= "<label>$filter_data[header_caption]</label>";
                }
            } else {
                if (isset($filter_data['caption'])) {
                    $filter_widget .= "<label>$filter_data[caption]</label>";
                }
            }
        }

        if (empty($filter_data['name'])) {
            $filter_data['name'] = $filter_name;
        }

        $html = new PluginIserviceHtml();
        $param_array_name = $this->getRequestArrayName();
        $default_format = '%s';
        $filter_options = array();
        if (!empty($filter_data['style'])) {
            $filter_options['style'] = $filter_data['style'];
        }
        if (!empty($filter_data['class'])) {
            $filter_options['class'] = is_array($filter_data['class']) ? $filter_data['class'] : [$filter_data['class']];
        }
        if (!empty($filter_data['min_value'])) {
            $filter_options['data-required-minimum'] = $filter_data['min_value'];
        }
        if (!empty($filter_data['ignore_min_value_if_not_set'])) {
            $filter_options['data-ignore-min-value-if-not-set'] = $filter_data['ignore_min_value_if_not_set'];
        }
        if (!empty($filter_data['label'])) {
            $filter_options['data-label'] = $filter_data['label'];
        }
        $type_mapper = array(
            self::FILTERTYPE_HIDDEN => PluginIserviceHtml::FIELDTYPE_HIDDEN,
            self::FILTERTYPE_LABEL => PluginIserviceHtml::FIELDTYPE_TEXT,
            self::FILTERTYPE_CHECKBOX => PluginIserviceHtml::FIELDTYPE_CHECKBOX,
            self::FILTERTYPE_DATE => PluginIserviceHtml::FIELDTYPE_DATE,
            self::FILTERTYPE_DATETIME => PluginIserviceHtml::FIELDTYPE_DATETIME,
            self::FILTERTYPE_USER => PluginIserviceHtml::FIELDTYPE_DROPDOWN,
            self::FILTERTYPE_SELECT => PluginIserviceHtml::FIELDTYPE_DROPDOWN,
            self::FILTERTYPE_INT => PluginIserviceHtml::FIELDTYPE_TEXT,
            self::FILTERTYPE_TEXT => PluginIserviceHtml::FIELDTYPE_TEXT,
        );
        switch ($filter_data['type']) {
            case self::FILTERTYPE_HIDDEN:
            case self::FILTERTYPE_CHECKBOX:
                self::ensureArrayKey($filter_data, 'empty_format', '%s');
                break;
            case self::FILTERTYPE_DATE:
                $default_format = 'Y-m-d';
                $filter_options['class'][] = 'noprint';
                break;
            case self::FILTERTYPE_DATETIME:
                $default_format = 'Y-m-d H:i:s';
                $filter_options['class'][] = 'noprint';
                break;
            case self::FILTERTYPE_USER:
                $filter_data['glpi_class'] = 'User';
                self::ensureArrayArrayKey($filter_data, 'glpi_class_params', array('right' => 'interface'));
            case self::FILTERTYPE_SELECT:
                if (isset($filter_data['glpi_class'])) {
                    $default_format = '%d';
                    $glpi_class_params = array('addicon' => false, 'comments' => false, 'width' => '100%');
                    if (!empty($filter_data['glpi_class_params'])) {
                        $glpi_class_params = array_merge($glpi_class_params, $filter_data['glpi_class_params']);
                    }
                    $filter_options['type'] = $filter_data['glpi_class'];
                    $filter_options['options'] = $glpi_class_params;
                } else {
                    $filter_options['method'] = 'showFromArray';
                    $filter_options['values'] = $filter_data['options'];
                }
                $filter_options['class'][] = 'noprint';
                $filter_options['class'][] = $filter_in_header ? 'full' : '';
                break;
            case self::FILTERTYPE_INT:
                if (!is_numeric($filter_value)) {
                    $filter_value = isset($filter_data['empty_value']) ? $filter_data['empty_value'] : '';
                }
            default:
                $filter_options['class'][] = 'noprint';
                break;
        }
        $field_type = array_key_exists($filter_data['type'], $type_mapper) ? $type_mapper[$filter_data['type']] : PluginIserviceHtml::FIELDTYPE_TEXT;
        $field_name = $param_array_name . ($filter_inline ? "[$params[itemtype]][$params[row_id]]" : "") . "[$filter_data[name]]";
        $filter_widget .= $html->generateField($field_type, $field_name, $filter_value, $filter_data['type'] === self::FILTERTYPE_LABEL, $filter_options);
        if ($filter_data['visible'] && isset($filter_data['post_widget'])) {
            $filter_widget .= $filter_data['post_widget'];
        }

        self::ensureArrayKey($filter_data, 'format', $default_format);
        self::ensureArrayKey($filter_data, 'empty_format', $filter_data['format']);
        switch ($filter_data['type']) {
            case self::FILTERTYPE_HIDDEN:
                if (in_array($filter_data['old_type'], array(self::FILTERTYPE_DATE, self::FILTERTYPE_DATETIME))) {
                    $replacement_value = date($filter_data['format'], strtotime($filter_value));
                } else {
                    $replacement_value = empty($filter_value) ? sprintf($filter_data['empty_format'], $filter_value) : sprintf($filter_data['format'], $filter_value);
                }
                break;
            case self::FILTERTYPE_DATE:
            case self::FILTERTYPE_DATETIME:
                $replacement_value = empty($filter_value) ? sprintf($filter_data['empty_format'], $filter_value) : date($filter_data['format'], strtotime($filter_value));
                break;
            case self::FILTERTYPE_USER:
            case self::FILTERTYPE_SELECT:
            case self::FILTERTYPE_CHECKBOX:
                self::ensureArrayKey($filter_data, 'zero_is_empty', true);
                $filter_value_empty = empty($filter_value);
                if (!$filter_data['zero_is_empty']) {
                    $filter_value_empty &= $filter_value !== 0 && $filter_value !== "0";
                }
                $replacement_value = $filter_value_empty ? (isset($filter_data['empty_value']) ? $filter_data['empty_value'] : '') : sprintf($filter_data['format'], $filter_value);
                break;
            default:
                $replacement_value = empty($filter_value) ? sprintf($filter_data['empty_format'], $filter_value) : sprintf($filter_data['format'], $filter_value);
                break;
        }
        $this->query = str_replace("[$filter_name]", $replacement_value, $this->query);
        return $filter_widget;
    }

    protected function displayTotalRow($top_row) {
        echo $top_row ? "<tr>" : "<tr class='tab_bg_1' style='font-weight:bold;'>";
        $tag = $top_row ? "th" : "td";
        if ($this->mass_actions_column) {
            echo "<$tag></$tag>";
        }
        foreach ($this->columns as $field_name => $column) {
            if (isset($column['visible']) && !$column['visible']) {
                continue;
            }
            $class = self::adjustAttribute('class', $top_row ? $column['header_class'] : (isset($column['footer_class']) ? $column['footer_class'] : $column['class']));
            if (isset($column['align'])) {
                if ($top_row) {
                    $column['header_style'][] = "text-align:$column[align]";
                } else {
                    $column['style'][] = "text-align:$column[align]";
                }
            }
            $style = self::adjustAttribute('style', $top_row ? $column['header_style'] : (isset($column['footer_style']) ? $column['footer_style'] : $column['style']), ';');
            echo "<$tag$class$style>";
            if (isset($column['total']) && $column['total']) {
                echo "Total: ";
                $total_span_name = "{$this->getRequestArrayName()}_total_$field_name";
                if ($top_row) {
                    echo "<span id='$total_span_name'></span>";
                } else {
                    if (isset($column['format'])) {
                        echo sprintf($column['format'], $this->totals[$field_name]);
                    } else {
                        echo $this->totals[$field_name];
                    }
                    echo "<script>document.getElementById('$total_span_name').innerHTML = '" . $this->totals[$field_name] . "';</script>";
                }
            }
            echo "</$tag>";
        }
        if (!empty($this->actions)) {
            echo "<$tag></$tag>";
        }
        echo "</tr>";
    }

    protected function displayRowData($row, $row_num, $columns) {
        if (!empty($this->mass_actions_column) && !$this->exporting) {
            echo "<td style='text-align: center'>" . Html::getMassiveActionCheckBox($this->itemtype, $row[$this->id_field], ['readonly' => !eval('return ' . ($columns['#mass_action#']['enable'] ?? 'true') . ';')]) . "</td>";
        }
        foreach ($columns as $field_name => $column) {
            if (isset($column['visible']) && !$column['visible']) {
                continue;
            }
            if (empty($row[$field_name]) && isset($column['empty'])) {
                $column = $column['empty'];
            }
            if (isset($column['value'])) {
                $row[$field_name] = $column['value'];
            }
            $tooltip = self::adjustAttribute('title', isset($column['tooltip']) ? $column['tooltip'] : null);
            $align = self::adjustAttribute('align', isset($column['align']) ? $column['align'] : null);
            $class = self::adjustAttribute('class', isset($column['class']) ? $column['class'] : null);
            $style = self::adjustAttribute('style', isset($column['style']) ? $column['style'] : null, ';');
            if (!$this->exporting) {
                echo "<td $align$class$style$tooltip>";
            }
            self::ensureArrayKey($column, 'format', '%s');
            if ($this->exporting && isset($column['export_format'])) {
                $column['format'] = $column['export_format'];
            }
            if (!empty($column['edit_field']) && is_array($column['edit_field'])) {
                $data_to_print = $this->getFilterWidget($column['edit_field'], $field_name, 'inline', ['itemtype' => $this->itemtype, 'row_id' => $row[$this->id_field], 'row_data' => $row]);
            } else {
                $data_to_print = $row[$field_name] ?? '';
            }
            $to_print = sprintf($column['format'], $data_to_print);
            if (strpos($to_print, 'function:default') === 0) {
                $to_print = eval('return ' . get_called_class() . '::get' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $field_name))) . 'Display($row);');
            } elseif (strpos($to_print, 'function:') === 0) {
                $to_print = eval('return ' . substr($to_print, strlen('function:')));
            }
            if ($this->exporting) {
                $data_for_export[] = $to_print;
            }
            if (isset($column['link'])) {
                $title = self::adjustAttribute('title', isset($column['link']['title']) ? $column['link']['title'] : null);
                $target = self::adjustAttribute('target', isset($column['link']['target']) ? $column['link']['target'] : null);
                $link_class = self::adjustAttribute('class', isset($column['link']['class']) ? $column['link']['class'] : null);
                $link_style = self::adjustAttribute('style', isset($column['link']['style']) ? $column['link']['style'] : null, ';');
                $detail_key = self::ensureArrayKey($column['link'], 'detail_key', 0);
                switch (isset($column['link']['type']) ? $column['link']['type'] : null) {
                    case 'detail':
                        $link = "#";
                        $onclick = "onclick='detailSubmit(\$(this), \"{$this->getRequestArrayName()}\", \"$field_name\", \"$row_num\", \"$detail_key\", \"{$this->getRequestArrayName(1)}\");'";
                        break;
                    default:
                        if (!isset($column['link']['href'])) {
                            $link = "#";
                        } else {
                            $link = $column['link']['href'];
                        }
                        $onclick = "";
                        break;
                }
                if (!isset($column['link']['visible']) || $column['link']['visible']) {
                    $to_print = "<a$link_class href='$link'$onclick$link_style$target$title>$to_print</a>";
                }
            }
            if (!empty($column['editable'])) {
                $to_print = $this->addEditWidget($to_print, $field_name, $data_to_print, $row[$this->id_field], $column['edit_settings']);
            }
            if (isset($column['total']) && $column['total']) {
                if (!isset($this->totals[$field_name])) {
                    $this->totals[$field_name] = $row[$field_name];
                } else {
                    $this->totals[$field_name] += $row[$field_name];
                }
            }
            if (!$this->exporting) {
                echo $to_print;
                echo "</td>";
            }
        }
        if (!empty($data_for_export)) {
            $this->export_data[] = $data_for_export;
        }
    }

    protected function displayRowActions($settings) {
        if (!is_array($settings)) {
            return;
        }
        echo "<td><div class='actions'>";
        foreach ($settings as $setting) {
            self::ensureArrayKey($setting, 'title', '*');
            if (isset($setting['icon'])) {
                $icon = "<img class='noprint' src='$setting[icon]' alt='$setting[title]' title='$setting[title]' />";
            } else {
                $icon = $setting['title'];
            }
            if (isset($setting['link']) && !empty($setting['link'])) {
                echo "<a href='$setting[link]'>$icon</a>";
            } else {
                echo $icon;
            }
        }
        echo "</div></td>";
    }

    protected function displayDataRows($data) {

        $row_num = 0;
        $this->totals = array();
        foreach ($data as $row) {
            $new_column_settings = array();
            $new_actions_settings = array();
            foreach ($this->columns as $column_index => $column_value) {
                $new_column_settings[$column_index] = $column_value;
            }
            foreach ($this->actions as $actions_index => $actions_value) {
                $new_actions_settings[$actions_index] = $actions_value;
            }
            foreach ($row as $field_name => $field_value) {
                $this->processSettings($new_column_settings, $field_name, $field_value);
                $this->processSettings($new_actions_settings, $field_name, $field_value);
            }

            $row_class = $this->evalIfFunction($this->row_class ?? '', ['row_data' => $row]);

            if (!$this->exporting) {
                echo "<tr class='tab_bg_" . ($row_num++ % 2 + 1) . " result-row $row_class'>";
            }

            $row['__row_id__'] = $row_num;
            $this->displayRowData($row, $row_num, $new_column_settings);

            if ($this->exporting) {
                continue;
            }

            if (!empty($new_actions_settings)) {
                $this->displayRowActions($new_actions_settings);
            }

            echo "</tr>";

            if (!empty($this->insert_empty_rows)) {
                if (!is_array($this->insert_empty_rows)) {
                    $this->insert_empty_rows['count'] = 1;
                    $this->insert_empty_rows['content'] = $this->insert_empty_rows;
                }
                if (!isset($this->insert_empty_rows['content'])) {
                    $this->insert_empty_rows['content'] = '&nbsp;';
                }
                for ($i = 0; $i < $this->insert_empty_rows['count']; $i++) {
                    $row_class = self::adjustAttribute('class', isset($this->insert_empty_rows['row_class']) ? $this->insert_empty_rows['row_class'] : null);
                    $row_style = self::adjustAttribute('style', isset($this->insert_empty_rows['row_style']) ? $this->insert_empty_rows['row_style'] : null, ';');
                    $cell_class = self::adjustAttribute('class', isset($this->insert_empty_rows['cell_class']) ? $this->insert_empty_rows['cell_class'] : null);
                    $cell_style = self::adjustAttribute('style', isset($this->insert_empty_rows['cell_style']) ? $this->insert_empty_rows['cell_style'] : null, ';');
                    echo "<tr$row_class$row_style><td colspan='$this->visible_columns_count'$cell_class$cell_style>{$this->insert_empty_rows['content']}</td></tr>";
                }
            }
        }
    }

    protected function displayResultsTable($data, $readonly = false) {
        global $CFG_GLPI;
        $table_name = $this->getMachineName();
        $this->class[] = 'tab_cadrehov';
        $this->class[] = 'wide';
        $this->class[] = 'view-table';
        $this->class[] = "detail$this->detail_displaying";
        $table_class = self::adjustAttribute('class', $this->class);
        $table_style = self::adjustAttribute('style', $this->style, ';');
        $this->visible_columns_count = $this->mass_actions_column + (empty($this->actions) ? 0 : 1);
        foreach ($this->columns as $column) {
            $this->visible_columns_count += !isset($column['visible']) || $column['visible'] ? 1 : 0;
        }
        if (!$this->exporting) {
            echo "<table id='$table_name'$table_class$table_style>";
            echo "<thead>";
            if (!$readonly && $this->query_count !== null) {
                echo "<tr>";
                echo "<th class='view-header' colspan='$this->visible_columns_count'>";
                if (isset($this->filters['filter_buttons_prefix'])) {
                    echo "<span class='filter-buttons-prefix'>" . $this->filters['filter_buttons_prefix'] . "</span>";
                }
                $style = empty($this->filter_buttons_align) || $this->filter_buttons_align === 'left' ? '' : "style='float:$this->filter_buttons_align;'";
                echo "<div class='view-filter-buttons'$style>";
                if ($this->show_filter_buttons) {
                    echo " <input type='submit' class='submit noprint' name='filter' value='" . __('Filter', 'views') . "'/>";
                    echo " <input type='submit' class='submit noprint' name='{$this->getRequestArrayName()}[reset]' value='" . __('Reset filters', 'views') . "'/>";
                }
                if (isset($this->filters['filter_buttons_postfix'])) {
                    echo "<span class='filter-buttons-prefix'>" . $this->filters['filter_buttons_postfix'] . "</span>";
                }
                if ($this->show_limit) {
                    echo " " . __("Show", "iservice") . " <input type='text' name='{$this->getRequestArrayName()}[limit]' value='$this->limit' style='text-align:right;width:40px'/> din $this->query_count";
                }
                if ($this->show_export) {
                    echo " <input type='submit' class='submit noprint' name='export' value='" . __('Export', 'views') . "'/> ";
                    echo "<select name='export_type'>";
                    echo "<option value='visible'" . ($this->export_type == 'visible' ? " selected" : "") . ">vizibile</option>";
                    echo "<option value='full'" . ($this->export_type == 'full' ? " selected" : "") . ">toate</option>";
                    echo "</select>";
                }
                echo "</div>";
                echo "<div class='view-mass-action'>";
                if (!empty($this->mass_actions_column)) {
                    echo "<img class='mass_action' src='$CFG_GLPI[root_doc]/pics/arrow-left-top.png'>";
                }
                foreach ($this->mass_actions as $mass_action_key => &$mass_action) {
                    self::ensureArrayKey($mass_action, 'name', $mass_action_key);
                    self::ensureArrayKey($mass_action, 'caption');
                    self::ensureArrayKey($mass_action, 'action');
                    self::ensureArrayKey($mass_action, 'prefix');
                    self::ensureArrayKey($mass_action, 'suffix');
                    self::ensureArrayKey($mass_action, 'class', '', ' submit noprint');
                    self::ensureArrayKey($mass_action, 'style');
                    self::ensureArrayKey($mass_action, 'new_tab', true);
                    $mass_action['action'] .= $mass_action['new_tab'] ? (strpos($mass_action['action'], '?') ? '&kcsrft=1' : '?kcsrft=1') : '';
                    $mass_action_on_click = 'var old_action=$(this).closest("form").attr("action");$(this).closest("form").attr("action","' . $mass_action['action'] . '");';
                    $mass_action_on_click .= $mass_action['new_tab'] ? '$(this).closest("form").attr("target","_blank");' : '';
                    $mass_action_on_click .= 'var button=$(this);setTimeout(function(){if (old_action) {button.closest("form").attr("action",old_action);}else{button.closest("form").removeAttr("action");}button.closest("form").attr("target","");}, 1000);';
                    //$mass_action_on_click .= '$(this).closest("form").delay(1000).attr("action",old_action);$(this).closest("form").delay(1000).attr("target","");';
                    echo "<div class='mass_action'>";
                    echo $mass_action['prefix'];
                    echo " <input type='submit' class='$mass_action[class]' id='mass_action_$mass_action[name]' name='mass_action_$mass_action[name]' onclick='$mass_action_on_click' style='$mass_action[style]' value='$mass_action[caption]'> ";
                    echo $mass_action['suffix'];
                    echo "</div>";
                }
                echo "</div>";
                echo "</th>";
                echo "</tr>";
            }
            echo "<tr>";
            if ($this->mass_actions_column) {
                echo "<th>" . Html::getCheckAllAsCheckbox($table_name) . "</th>";
            }
        }
        foreach ($this->columns as $field_name => &$column) {
            self::ensureArrayArrayKey($column, 'class', array());
            self::ensureArrayArrayKey($column, 'header_class', $column['class']);
            self::ensureArrayArrayKey($column, 'style', array());
            self::ensureArrayArrayKey($column, 'header_style', $column['style']);

            if (isset($column['visible']) && !$column['visible']) {
                continue;
            }

            self::ensureArrayKey($column, 'sortable', true);
            self::ensureArrayKey($column, 'sort_default_dir', 'ASC');
            $new_order_dir = $column['sort_default_dir'];
            $order_sign = '';
            if ($this->order_by !== null && $this->order_by == $field_name) {
                $new_order_dir = ($this->order_dir === 'ASC') ? 'DESC' : 'ASC';
                $order_sign = "<img src='$CFG_GLPI[root_doc]/pics/puce-" . ($new_order_dir === 'ASC' ? 'down' : 'up') . ".png' /> ";
            }
            if (!$readonly && isset($column['filter']) && !empty($column['filter'])) {
                $column['header_class'][] = "with-filter";
                $filter = $column['filter'];
            } else {
                $filter = '';
            }
            $class = self::adjustAttribute('class', $column['header_class']);
            $style = self::adjustAttribute('style', $column['header_style'], ';');
            if ($this->exporting) {
                $header_export_data[] = $column['title'];
            } else {
                echo "<th$class$style>";
                if ($readonly || !$column['sortable']) {
                    echo "$order_sign $column[title]";
                } else {
                    echo "<a href='#' onclick='orderSubmit($(this), \"{$this->getRequestArrayName()}\", \"$field_name\", \"$new_order_dir\");'>$order_sign $column[title]</a>";
                }
                echo "$filter</th>";
            }
        }
        if ($this->exporting) {
            $this->export_data[] = $header_export_data;
            $this->displayDataRows($data);
        } else {
            if (!empty($this->actions)) {
                echo "<th></th>";
            }
            echo "</tr>";
            $this->displayTotalRow(true);
            echo "</thead>";
            echo "<tbody>";
            $this->displayDataRows($data);
            echo "<tr></tr>";
            $this->displayTotalRow(false);
            echo "</tbody>";
            echo "</table>";
        }
    }

    protected function displayDetail($data, $readonly = false, $export = false) {
        $detail_row = intval($this->detail_row);
        if (!empty($this->detail) && $detail_row > 0 && isset($this->columns[$this->detail]['link']) && isset($data[--$detail_row])) {
            $detail_view_settings = $this->columns[$this->detail]['link'];
            $this->processSettings($detail_view_settings, '#detail#key#', $this->detail_key);
            foreach ($data[$detail_row] as $field_name => $field_value) {
                $this->processSettings($detail_view_settings, $field_name, $field_value);
            }
            $detail_view = self::createFromSettings($detail_view_settings);
            $detail_view->display($readonly, $export, $this->detail_displaying + 1);
        }
    }

    protected function generateParams($type) {
        $glues = array(
            'get' => '&',
            'post' => '',
        );
        if (!array_key_exists($type, $glues)) {
            return;
        }
        $param_array_name = $this->getRequestArrayName();
        if (!isset($this->params['detail'])) {
            $this->params['detail'] = $this->detail;
            $this->params['detail_row'] = $this->detail_row;
            $this->params['detail_key'] = $this->detail_key;
        }
        foreach ($this->params as $param_name => $param_value) {
            if ($param_value !== null) {
                switch ($type) {
                    case "post":
                        $param_pieces[] = "<input type='hidden' name='{$param_array_name}[$param_name]' value='$param_value' />";
                        break;
                    default:
                        $param_pieces[] = "{$param_array_name}[$param_name]=$param_value";
                        break;
                }
            }
        }
        return implode($glues[$type], $param_pieces);
    }

    function display($readonly = false, $export = false, $detail = 0, $generate_form = true) {
        $html = new PluginIserviceHtml();
        $this->exporting = $export;
        $this->export_data = array();
        if (($this->detail_displaying = $detail) > 0) {
            $generate_form = false;
        }
        $this->loadSettings();
        $this->loadRequestVariables();

        if ($this->from_cache) {
            $this->use_cache = true;
        }

        if ($this->use_cache) {
            if ($this->cache_timeout_warning === null) {
                $this->cache_timeout_warning = $this->cache_timeout / 2;
            }
            $this->prepareCache();
            $this->query = empty($this->cache_query) ? "SELECT * FROM {$this->use_cache['table_name']}" : $this->cache_query;
            if (strpos($this->query, '}')) {
                foreach ($this->use_cache as $cache_variable_name => $cache_variable_value) {
                    $this->query = str_replace('{' . $cache_variable_name . '}', $cache_variable_value, $this->query);
                }
            }
            $style = date('Y-m-d H:i:s') > $this->use_cache['data_expire_warning'] ? "style='color:red'" : "";
            $title = sprintf(__("Expires on %s", 'iservice'), $this->use_cache['data_expires']);
            $refresh_button = "<input class='submit' onclick='$(\"#cache-refresh\").val(1);$(\".refresh-target\").submit();' $style type='submit' value='" . __('Refresh', 'iservice') . "'>";
            $this->name .= " [<span $style title='$title'>" . sprintf(__('from cache %s', 'iservice'), $this->use_cache['data_cached']) . " $refresh_button</span>]";
        }

        $this->adjustQueryOrderBy();

        if (!$this->exporting) {
            // Keep this before the form opening to be able to put a separate form in the prefix
            echo empty($this->prefix) ? "" : $this->prefix;
            echo "<h{$this->getHeadingLevel()} id='view-query-{$this->getRequestArrayName()}'>$this->name" . (empty($this->filter_description) ? "" : " - $this->filter_description") . "</h{$this->getHeadingLevel()}>";
            echo empty($this->description) ? "" : "<div class='filter-description'>$this->description</div>";
            if ($generate_form) {
                $html->openForm(array('method' => 'post', 'class' => 'iservice-form refresh-target', 'enctype' => 'multipart/form-data'));
                // Default behaviour for enter is to filter
                echo "<input type='submit' name='filter' value='filter' style='display:none' />";
            }
            echo "<input type='hidden' name='filtering' value='filtering' />";
            echo "<input id='cache-refresh' type='hidden' name='cache_refresh' value='' />";
        }

        if (!$readonly && !$this->displayFilters()) {
            return;
        }

        $data = array();
        if ($this->instant_display || PluginIserviceCommon::getInputVariable('filtering')) {
            $this->query_count = $this->getQueryCount();
            $this->adjustQueryLimit();

            global $DB;
            if (($result = $DB->query($this->query)) === false) {
                echo $DB->error(), '<br>', $this->query;
                $html->closeForm();
                return;
            }

            while (($row = $DB->fetchAssoc($result)) !== null) {
                $data[] = $row;
            }
        }

        $this->displayResultsTable($data, $readonly);

        if ($this->exporting) {
            header("Cache-Control: public"); // needed for i.e.
            header("Content-Transfer-Encoding: Binary");
            header("Content-Disposition: attachment; filename=export.$this->export_format");
            header("Pragma: no-cache");
            header("Expires: 0");
            switch ($this->export_format) {
                case 'csv':
                    header("Content-Type: text/csv");
                    $output = fopen("php://output", 'w');
                    foreach ($this->export_data as $export_row) {
                        fputcsv($output, $export_row);
                    }
                    fclose($output);
                    break;
                default:
                    break;
            }
        } else {
            echo $this->generateParams('post');
            if ($_SESSION['glpi_use_mode'] === Session::DEBUG_MODE) {
                echo "<a href='javascript:none' onclick='$(\".query$this->detail_displaying\").toggle();'>Show view query</a>";
                if (!empty($this->detail_row)) {
                    echo " (detail_key: $this->detail_key, detail_row: $this->detail_row)";
                }
            }
            echo "<div style='text-align:center'><textarea class='query$this->detail_displaying' style='display:none;width:90%;height:20em;'>$this->query</textarea></div>";
            echo empty($this->postfix) ? '' : $this->postfix;
            $this->displayDetail($data, $readonly, $export);
                if ($generate_form) {
                $html->closeForm();
            }
        }
    }

    function export() {
        return $this->display(false, true);
    }

    protected function getCacheFileName(): string
    {
        return PluginIserviceViews::$default_views_directory . "/{$this->getMachineName()}.cache";
    }

    public function getCachedData()
    {
        $cache_file_name = $this->getCacheFileName();
        return file_exists($cache_file_name) ? unserialize(file_get_contents($cache_file_name)) : [];
    }

    public function refreshCachedData()
    {
        $this->use_cache = $this->refreshCacheFile($this->getCacheFileName());
    }

    protected function prepareCache() {
        $cache_data =$this->getCachedData();
        $force_refresh = empty($this->force_refresh) ? PluginIserviceCommon::getInputVariable('cache_refresh') : $this->force_refresh;
        if (empty($this->ignore_control_hash) && ($cache_data['control_hash'] ?? '' != $this->control_hash)) {
            $force_refresh = true;
        }
        if ($force_refresh || empty($cache_data) || empty($cache_data['data_expires']) || $cache_data['data_expires'] < date('Y-m-d H:i:s')) {
            $this->refreshCachedData();
        } else {
            $this->use_cache = $cache_data;
        }
    }

    protected function refreshCacheFile($file_name) {
        global $DB;
        $table_name = 'glpi_plugin_iservice_cachetable_' . $this->getMachineName();
        $DB->query("DROP TABLE $table_name");
        if (!$DB->query("CREATE TABLE $table_name AS {$this->getFilterlessQuery()}")) {
            echo $DB->error(), "<br>CREATE TABLE $table_name AS {$this->getFilterlessQuery()}";
            return;
        }
        $cache_data = [
            'control_hash' => $this->control_hash,
            'table_name' => $table_name,
            'data_cached' => date('Y-m-d H:i:s'),
            'data_expires' => date('Y-m-d H:i:s', time() + $this->cache_timeout),
            'data_expire_warning' => date('Y-m-d H:i:s', time() + $this->cache_timeout_warning),
        ];
        file_put_contents($file_name, serialize($cache_data));
        return $cache_data;
    }

    protected function getFilterlessQuery() {
        if (!strpos($this->query, ']')) {
            return $this->query;
        }
        $original_query = $this->query;
        foreach ($this->filters as $filter_name => $filter_data) {
            if (in_array($filter_name, $this->getIgnoredFilterNames())) {
                continue;
            }
            $this->getFilterWidget($filter_data, $filter_name);
        }
        $filterless_query = $this->query;
        $this->query = $original_query;
        return $filterless_query;
    }

    protected function getIgnoredFilterNames() {
         return ['prefix', 'postfix', 'filter_buttons_prefix', 'filter_buttons_postfix'];
    }

    protected function getSetting($settings, $setting_key, $default_value = '') {
        if (!is_array($setting_key)) {
            $setting_key = array($setting_key);
        }
        foreach ($setting_key as $key) {
            if (array_key_exists($key, $settings)) {
                $settings = $settings[$key];
            } else {
                return $default_value;
            }
        }
        return $settings;
    }

    protected function getArraySetting($settings, $setting_key, $default_value = array()) {
        if (!empty($default_value) && !is_array($default_value)) {
            return null;
        }
        $result = $this->getSetting($settings, $setting_key, $default_value);
        return is_array($result) ? $result : array($result);
    }

    protected function processSettings(&$new_settings, $search, $replace) {
        if (is_array($new_settings)) {
            foreach ($new_settings as &$setting) {
                $this->processSettings($setting, $search, $replace);
            }
        } else if (strpos($new_settings, "[$search]") !== false) {
            $new_settings = str_replace("[$search]", $replace, $new_settings);
        }
    }

    static function getActionButtons($buttons_config, $collapsible = false) {
        $wrapper_id = empty($buttons_config['_wrapper']['id']) ? '' : "id='{$buttons_config['_wrapper']['id']}'";
        $result = "<div class='actions" . ($collapsible ? ' collapsible' : '') . "' $wrapper_id>";
        foreach ($buttons_config as $key => $button_config) {
            if ($key[0] === '_') {
                continue;
            }
            if (is_string($button_config)) {
                $result .= $button_config;
                continue;
            }
            if (isset($button_config['visible']) && !$button_config['visible']) {
                continue;
            }
            $link = empty($button_config['link']) ? '' : $button_config['link'];
            $prefix = empty($button_config['prefix']) ? '' : $button_config['prefix'];
            $suffix = empty($button_config['suffix']) ? '' : $button_config['suffix'];
            if (empty($button_config['onclick'])) {
                $onclick = '';
            } elseif ($button_config['onclick'] === 'ajaxCall') {
                $confirm = empty($button_config['confirm']) ? '' : $button_config['confirm'];
                $success = empty($button_config['success']) ? '' : $button_config['success'];
                $onclick = "ajaxCall(\"$link\", \"$confirm\", $success);return false;";
                $link = 'javascript:void();';
            } else {
                $onclick = $button_config['onclick'];
                $link = 'javascript:void();';
            }
            if (!empty($onclick)) {
                $onclick = "onclick='$onclick'";
            }
            if (!empty($button_config['html'])) {
                $html = $button_config['html'];
            } else {
                $img_src = empty($button_config['icon']) ? '' : $button_config['icon'];
                $title = empty($button_config['title']) ? '' : $button_config['title'];
                $html = "<img alt='$title' class='noprint view_action_button' src='$img_src' title='$title' />";
            }
            $result .= "$prefix<a href='$link' $onclick>$html</a>$suffix\n";
        }
        return $result;
    }

    static function adjustAttribute($attribute_name, $attribute_value = null, $attribute_separator = ' ') {
        if (!is_array($attribute_value) && !empty($attribute_value)) {
            $attribute_value = array($attribute_value);
        }
//        return empty($attribute_value) ? "" : " $attribute_name='" . implode($attribute_separator, $attribute_value) . "'";
        return empty($attribute_value) ? "" : " $attribute_name='" . str_replace("'", "&#39;", implode($attribute_separator, $attribute_value)) . "'";
    }

    static function ensureArrayKey(&$array, $key, $default_value = '', $add_value = '') {
        if (!isset($array[$key])) {
            $array[$key] = $default_value;
        }
        if (!empty($add_value)) {
            $array[$key] .= $add_value;
        }
        return $array[$key];
    }

    static function ensureArrayArrayKey(&$array, $key, $default_value, $add_values = []) {
        if (!isset($array[$key])) {
            $array[$key] = $default_value;
        } elseif (!is_array($array[$key])) {
            $array[$key] = array($array[$key]);
        }
        if (!empty($add_values)) {
            if (!is_array($add_values)) {
                $add_values = [$add_values];
            }
            foreach ($add_values as $add_key => $add_value) {
                if (!isset($array[$key][$add_key])) {
                    $array[$key][$add_key] = '';
                }
                $array[$key][$add_key] .= $add_value;
            }
        }
        return $array[$key];
    }

    protected function addEditWidget($display, $field_name, $field_value, $item_id, $settings)
    {
        global $CFG_PLUGIN_ISERVICE;

        $edit_field = "<input id='$field_name-input-$item_id' style='vertical-align: middle;' value='$field_value' />";
        $on_edit_click = $on_cancel_click = "$(\"#$field_name-edit-$item_id\").toggle();$(\"#$field_name-value-$item_id\").toggle();$(\"#$field_name-icon-$item_id\").toggle();";
        $on_accept_click = "ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/$settings[callback].php?id=$item_id&operation=$settings[operation]&value=\" + $(\"#$field_name-input-$item_id\").val(), \"\", function(message) {if (message !== \"" . PluginIserviceCommon::RESPONSE_OK . "\") {alert(message);} else {\$(\"#$field_name-value-$item_id\").html(\$(\"#$field_name-input-$item_id\").val());$on_edit_click}});";
        $accept_button = "<a class='fa fa-check-circle' href='javascript:void(0);' onclick='$on_accept_click' style='color: green; vertical-align: middle;'></a>";
        $cancel_button = "<a class='fa fa-times-circle' href='javascript:void(0);' onclick='$on_cancel_click' style='color: red; vertical-align: middle;'></a>";
        $edit_span = "<span id='$field_name-edit-$item_id' style='display: none;'>$edit_field $accept_button $cancel_button</span>";


        return "<i id='$field_name-icon-$item_id' class='fa fa-edit' onclick='$on_edit_click'></i> <span id='$field_name-value-$item_id'>$display</span>$edit_span";
    }

    protected function generateWidgetBadge($badge_type, $badge_id, $title, $click, $disabled = false)
    {
        $badge_classes = [
            'error' => 'fa-exclamation-circle',
            'warning' => 'fa-exclamation-triangle',
            'estimate' => 'fa-calculator',
        ];
        $badge_colors = [
            'error' => 'red',
            'warning' => 'orange',
            'estimate' => 'blue',
        ];
        $badge_color = $disabled ? 'grey' : ($badge_colors[$badge_type] ?? 'black');
        return "<i id='badge-$badge_type-$badge_id' class='fa {$badge_classes[$badge_type]} badge-error' title='$title' style='color:$badge_color;' $click></i>";
    }

    protected function evalIfFunction($string, $global_variables = [])
    {
        if (strpos($string, 'function:') === 0) {
            $eval_before = '';
            foreach (array_keys($global_variables) as $variable_name) {
                $eval_before .= "global \$$variable_name; \$$variable_name = \$global_variables['$variable_name'];";
            }
            return eval($eval_before . 'return ' . substr($string, strlen('function:')));
        }

        return $string;
    }
}

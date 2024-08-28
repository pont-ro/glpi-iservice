<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
class PluginIserviceHtml {

    const FIELDTYPE_CHECKBOX = 'checkbox';
    const FIELDTYPE_DATE = 'date';
    const FIELDTYPE_DATETIME = 'datetime';
    const FIELDTYPE_DROPDOWN = 'dropdown';
    const FIELDTYPE_HIDDEN = 'hidden';
    const FIELDTYPE_LABEL = 'label';
    const FIELDTYPE_TEXT = 'text';
    const FIELDTYPE_MEMO = 'memo';
    const FIELDTYPE_RICHMEMO = 'richmemo';
    const CHECKBOXPOSITION_LEFT = 'left';
    const CHECKBOXPOSITION_RIGHT = 'right';
    const DEFAULT_CHECKBOXPOSITION = self::CHECKBOXPOSITION_LEFT;

    function generateField($type, $name, $value = '', $readonly = false, $options = []) {
        $use_default = false;
        if (is_array($readonly)) {
            die("This is the old use of generateField! Be aware that the 4th parameter should be boolean and only the 5th should be the options array!");
        }
        if (!is_array($options)) {
            die('Parameter $options should be an array!');
        }
        if (!empty($options['class']) && !is_array($options['class'])) {
            $options['class'] = [$options['class']];
        }
        if (!empty($options['style']) && !is_array($options['style'])) {
            $options['style'] = [$options['style']];
        }
        $class = $this->adjustAttribute('class', $options['class'] ?? null);
        $style = $this->adjustAttribute('style', $options['style'] ?? null, ';');
        $title = $this->adjustAttribute('title', $options['title'] ?? null);
        $onclick = $this->adjustAttribute('onclick', $options['onclick'] ?? null, ';');
        $onchange = $this->adjustAttribute('onchange', $options['onchange'] ?? null, ';');
        $data_attributes = $this->getDataAttributesFromArray($options);
        $output = isset($options['prefix']) ? $options['prefix'] : "";

        $required = ($options['required'] ?? false) ? ' required' : '';
        switch ($type) {
            case self::FIELDTYPE_CHECKBOX:
                $onchange_value = $options['onchange'] ?? [];
                if (!is_array($onchange_value)) {
                    $onchange_value = [$onchange_value];
                }
                $onchange = $this->adjustAttribute('onchange', array_merge(['$("#" + $(this).data("for")).val($(this).is(":checked") ? 1 : 0)'], $onchange_value), ';');

                $checked = !empty($value) ? " checked='1'" : "";
                $label = $options['label'] ?? "";
                $valid_positions = [self::CHECKBOXPOSITION_LEFT, self::CHECKBOXPOSITION_RIGHT];
                $position = (isset($options['position']) && in_array($options['position'], $valid_positions)) ? $options['position'] : self::DEFAULT_CHECKBOXPOSITION;
                if ($position === self::CHECKBOXPOSITION_RIGHT) {
                    $output .= "$label ";
                }
                if ($readonly) {
                    $read_only = " disabled='disabled'";
                } else {
                    $read_only = "";
                }
                $output .= "<input type='hidden' id='$name' name='$name' value='" . (empty($checked) ? 0 : 1) . "' />";
                $options['class'][] = 'checkbox-helper';
                $options['data-for'] = $name;
                $class = $this->adjustAttribute('class', $options['class']);
                $data_attributes = $this->getDataAttributesFromArray($options);
                $output .= "<input$checked$class$data_attributes id='_checkbox_helper_$name' name='_checkbox_helper_$name'$onchange$onclick$read_only$style$title type='checkbox' value='1'/>";
                if ($position === self::CHECKBOXPOSITION_LEFT) {
                    $output .= " $label ";
                }
                break;
            case self::FIELDTYPE_DATE:
            case self::FIELDTYPE_DATETIME:
                if ($readonly) {
                    return $this->generateField(self::FIELDTYPE_TEXT, $name, $value, $readonly, $options);
                }
                $field_options = [
                    'value' => $value,
                    'display' => false,
                    'min' => $options['min'] ?? '',
                    'max' => $options['max'] ?? '',
                    'mindate' => $options['mindate'] ?? '',
                    'maxdate' => $options['maxdate'] ?? '',
                    'mintime' => $options['mintime'] ?? '',
                    'maxtime' => $options['maxtime'] ?? '',
                    'warning' => $options['warning'] ?? '',
                    'on_change' => $options['on_change'] ?? '',
                ];
                if ($type == self::FIELDTYPE_DATE) {
                    $options['class'][] = 'date-field';
                    $datetime_selector = Html::showDateField($name, $field_options);
                } elseif ($type == self::FIELDTYPE_DATETIME) {
                    $options['class'][] = 'datetime-field';
                    $datetime_selector = Html::showDateTimeField($name, $field_options);
                }
                $options['class'][] = 'dropdown_wrapper';
                $class = $this->adjustAttribute('class', $options['class']);
                $buttons = '';
                if (isset($options['buttons']) && is_array($options['buttons'])) {
                    foreach ($options['buttons'] as $button_caption => $button_value) {
                        $buttons .= " <input type='button' class='submit' value='$button_caption' onclick ='setGlpiDateField($(this).closest(\".dropdown_wrapper\"), \"$button_value\");' />";
                    }
                }
                $warning = $field_options['warning'] ? " <span class='warning'>$field_options[warning]</span>" : '';
                $output .= "<div$class$data_attributes$style>$datetime_selector$buttons$warning</div>";
                break;
            case self::FIELDTYPE_DROPDOWN:
                if (!isset($options['type'])) {
                    $options['type'] = 'Dropdown';
                }
                if (!isset($options['method'])) {
                    $options['method'] = $options['type'] === 'Dropdown' ? 'showFromArray' : 'dropdown';
                }
                $use_default = true;
                if (class_exists($options['type']) && method_exists(new $options['type'], $options['method'])) {
                    if ($readonly) {
                        if (!isset($options['readonly_type'])) {
                            $options['readonly_type'] = $options['type'];
                        }
                        $dropdown_class = $options['readonly_type'];
                        $dropdown_object = new $dropdown_class;
                        if (isset($options['readonly_method']) && class_exists($options['readonly_type'])) {
                            if (method_exists($options['readonly_type'], $options['readonly_method'])) {
                                $value = forward_static_call(array($options['readonly_type'], $options['readonly_method']), $value);
                            }
                        } else if (method_exists($dropdown_object, 'getFromDB')) {
                            if ($dropdown_object->getFromDB($value)) {
                                unset($options['type']);
                                $options['title'] = $dropdown_object->fields['completename'] ?? $dropdown_object->fields['name'] ?? $value;
                                return
                                    $this->generateField(self::FIELDTYPE_TEXT, "_readonly_field_display_$name", $options['title'], $readonly, $options) .
                                    $this->generateField(self::FIELDTYPE_HIDDEN, $name, $value, $readonly, $options);
                            }
                        } elseif ($options['type'] === 'Dropdown' && isset($options['options']['unit'])) {
                            $value = Dropdown::getValueWithUnit($value, $options['options']['unit']);
                        } elseif (!empty($options['values'][$value])) {
                            $value = $options['values'][$value];
                        }
                    } else {
                        if (!isset($options['options'])) {
                            $options['options'] = [];
                        }
                        $options['options']['name'] = $name;
                        $options['options']['value'] = $value;
                        if (!isset($options['options']['display'])) {
                            $options['options']['display'] = false;
                        }
                        if ($options['type'] === 'Dropdown' && $options['method'] === 'showNumber') {
                            $arguments = [$name, $options['options']];
                        } elseif ($options['type'] === 'Dropdown' && $options['method'] === 'showYesNo') {
                            $arguments = [$name, $value, -1, $options['options']];
                            $options['options']['force_return'] = true;
                        } elseif ($options['type'] === 'Dropdown' && $options['method'] === 'showFromArray') {
                            $arguments = [$name, $options['values'], $options['options']];
                        } elseif (isset($options['arguments'])) {
                            $arguments = $options['arguments'];
                        } else {
                            $arguments = [$options['options']];
                        }
                        if (isset($options['options']['force_return']) && $options['options']['force_return']) {
                            ob_start();
                            forward_static_call_array([$options['type'], $options['method']], $arguments);
                            $temp_output = ob_get_contents();
                            ob_end_clean();
                        } else {
                            $temp_output = forward_static_call_array([$options['type'], $options['method']], $arguments);
                        }
                        $options['class'][] = 'dropdown_wrapper';
                        $class = $this->adjustAttribute('class', $options['class']);
                        $output .= "<div$class>$temp_output</div>";
                        $use_default = false;
                    }
                }
                break;
            case self::FIELDTYPE_HIDDEN:
            case self::FIELDTYPE_TEXT:
                if ($readonly) {
                    $read_only = ' readonly';
                } else {
                    $read_only = '';
                }
                $output .= "<input$class$data_attributes id='$name' name='$name'$onchange$onclick$read_only$required$style$title type='$type' value='$value' />";
                break;
            case self::FIELDTYPE_MEMO:
            case self::FIELDTYPE_RICHMEMO:
                if ($readonly) {
                    $read_only = ' readonly';
                } else {
                    $read_only = '';
                }
                $output .= "<textarea$class$data_attributes name='$name'$read_only$style>$value</textarea>";
                if ($type === self::FIELDTYPE_RICHMEMO) {
                    $output .= Html::initEditorSystem($name, '', false, $readonly);
                }
                break;
            case self::FIELDTYPE_LABEL:
                if (empty($class)) {
                    $class = " class='label-input'";
                } else {
                    $class = substr($class, 0, -1) . " label-input'";
                }
            default:
                $use_default = true;
                break;
        }
        $output .= $use_default ? "<span$class$data_attributes$style>$value</span>" : "";
        $output .= isset($options['postfix']) ? $options['postfix'] : "";
        return $output;
    }

    function generateNewTabLink($text, $href = '#', $options = []): string
    {
        return $this->generateLink($text, $href, '_blank', $options);
    }

    function generateLink($text, $href = '#', $target = '', $options = []): string
    {
        return $this->openTag('a', array_merge(['href' => $href, 'target' => $target], $options), true) . $text . $this->closeTag('a', true);
    }

    function generateSubmit($name, $caption, $options = []): string
    {
        $options['type'] = 'submit';
        $options['class'] = $options['class'] ?? 'submit';
        return $this->generateButton($name, $caption, $options);
    }

    function generateButton($name, $caption, $options = []): string
    {
        $data_attributes = $this->getDataAttributesFromArray($options);
        $type = $options['type'] ?? 'button';
        $id = $this->adjustAttribute('id', $options['id'] ?? "btn_$name");
        $class = $this->adjustAttribute('class', $options['class'] ?? 'submit');
        $style = $this->adjustAttribute('style', $options['style'] ?? null, ';');
        $title = $this->adjustAttribute('title', $options['title'] ?? null);
        $onclick = $this->adjustAttribute('onclick', $options['onclick'] ?? null);
        if (!empty($options['disabled'])) {
            $disabled = "disabled='true'";
        } else {
            $disabled = '';
        }
        $output = isset($options['prefix']) ? $options['prefix'] : "";
        $output .= "<input$id$class$data_attributes name='$name'$style$title type='$type' value='$caption' $onclick$disabled/>";
        $output .= isset($options['postfix']) ? $options['postfix'] : "";
        return $output;
    }

    function generateFieldTableRow($label, $field, $options = []) {
        $data_attributes = $this->getDataAttributesFromArray($options);
        $output = isset($options['prefix']) ? $options['prefix'] : "";
        $output .= "<tr" . $this->adjustAttribute('class', isset($options['row_class']) ? $options['row_class'] : null) . "$data_attributes>";
        $output .= "<td" . $this->adjustAttribute('class', isset($options['label_class']) ? $options['label_class'] : 'label') . ">$label</td>";
        $output .= "<td" . $this->adjustAttribute('class', isset($options['field_class']) ? $options['field_class'] : 'field') . ">$field</td>";
        $output .= "</tr>";
        $output .= isset($options['postfix']) ? $options['postfix'] : "";
        return $output;
    }

    function generateButtonsTableRow($buttons, $options = []) {
        $data_attributes = $this->getDataAttributesFromArray($options);
        $colspan = intval($options['colspan'] ?? 2);
        $output = $options['prefix'] ?? "";
        $output .= "<tr" . $this->adjustAttribute('class', isset($options['class']) ? $options['class'] : 'buttons') . "$data_attributes>";
        $output .= "<td colspan='$colspan'>";
        $output .= $options['label'] ?? '';
        if (is_array($buttons)) {
            foreach ($buttons as $button) {
                $output .= $button;
            }
        }
        $output .= "</td>";
        $output .= "</tr>";
        $output .= isset($options['postfix']) ? $options['postfix'] : "";
        return $output;
    }

    function generateTableRow(array $columns = [], array $options = [], $columnTag = 'td'): string
    {
        $result = $this->openTableRow($options['row_options'] ?? [], true);
        foreach ($columns as $index => $column) {
            $result .= $this->generateTableColumn($column, $options['column_options'][$index] ?? [], $columnTag);
        }
        $result .= $this->closeTableRow();

        return $result;
    }

    function generateTableColumn(string $column, array $options = [], string $tag = 'td'): string
    {
        return $this->openTag($tag, $options, true) . $column . $this->closeTag($tag, true);
    }

    function generateEmptyTableRow(int $colspan = 1): string
    {
        return $this->generateTableRow(['<br>'], ['column_options' => $colspan > 1 ? [['colspan' => $colspan]] : []]);
    }

    function displayTag($content, $tag = 'div')
    {
        echo $this->generateTag();
    }

    function displayField($type, $name, $value = '', $readonly = false, $options = []) {
        echo $this->generateField($type, $name, $value, $readonly, $options);
    }

    function displayFieldTableRow($label, $field, $options = []) {
        echo $this->generateFieldTableRow($label, $field, $options);
    }

    function displayButton($name, $caption, $options = []) {
        echo $this->generateButton($name, $caption, $options);
    }

    function displaySubmit($name, $caption, $options = []) {
        echo $this->generateSubmit($name, $caption, $options);
    }

    function displayButtonsTableRow($buttons, $options = []) {
        echo $this->generateButtonsTableRow($buttons, $options);
    }

    function displayTableRow($columns = [], $options = [], $columnTag = 'td') {
        echo $this->generateTableRow($columns, $options, $columnTag);
    }

    function displayTableColumn(string $column, array $options = [], string $tag = 'td')
    {
        echo $this->generateTableColumn($column, $options, $tag);
    }

    function displayEmptyTableRow(int $colspan = 1)
    {
        echo $this->generateEmptyTableRow($colspan);
    }

    function openForm($options = [], $return = false)
    {
        if (isset($options['multipart']) && $options['multipart']) {
            $options['enctype'] = 'multipart/form-data';
            unset($options['multipart']);
        }

        return $this->openTag('form', IserviceToolBox::addKeysToArray(['id', 'name', 'class', 'action', 'method', 'enctype', 'style'], $options), $return);
    }

    function closeForm($return = false) {
        if ($return) {
            return Html::closeForm(false);
        } else {
            Html::closeForm();
        }
    }

    function openTable($options = [], $return = false)
    {
        return $this->openTag('table', $options, $return);
    }

    function closeTable($return = false)
    {
        return $this->closeTag('table', $return);
    }

    function openTableRow($options = [], $return = false)
    {
        return $this->openTag('tr', $options, $return);
    }

    function closeTableRow($return = false)
    {
        return $this->closeTag('tr', $return);
    }

    function openTableColumn($options = [], $return = false)
    {
        return $this->openTag('td', $options, $return);
    }

    function closeTableColumn($return = false)
    {
        return $this->closeTag('td', $return);
    }

    protected function openTag($tag = 'div', $options = [], $return = false)
    {
        $result = '';

        foreach ($options as $key => $value) {
            $result .= $this->adjustAttribute($key, $value);
        }

        $result = empty($tag) ? '' : "<$tag$result>";

        if ($return) {
            return $result;
        } else {
            echo $result;
        }
    }

    protected function closeTag($tag = 'div', $return = false)
    {
        $result = empty($tag) ? '' : "</$tag>";
        if ($return) {
            return $result;
        } else {
            echo $result;
        }
    }

    static function getDataAttributesFromArray($array) {
        $data_attributes = "";
        if (empty($array) || !is_array($array)) {
            return $data_attributes;
        }
        foreach ($array as $key => $value) {
            if (strpos($key, 'data') === 0) {
                $data_attributes .= " $key='$value'";
            }
        }
        return $data_attributes;
    }

    static function adjustAttribute($attribute_name, $attribute_value = null, $attribute_separator = ' '): string
    {
        if (!is_array($attribute_value) && !empty($attribute_value)) {
            $attribute_value = [$attribute_value];
        }
        return empty($attribute_value) ? "" : " $attribute_name='" . implode($attribute_separator, $attribute_value) . "'";
    }

    /**
      function generateTable($prefix, $table=[], $suffix = '', $options=[]) {
      if (empty($options)) {
      $options = [];
      }
      if (isset($options['table_class']) && !empty($options['table_class'])) {
      if (is_array($options['table_class'])) {
      $options['table_class'] = implode(' ', $options['table_class']);
      }
      $options['table_class'] = " class='$options[table_class]'";
      }
      if (!isset($options['trailing_newline'])) {
      $options['trailing_newline'] = true;
      }
      if ($options['trailing_newline']) {
      $trailing_newline = '\r';
      } else {
      $trailing_newline = '';
      }
      if (!empty($table) && is_array($table) && !isset($table['head']) && !isset($table['body'])) {
      $table['body'] = $table;
      }

      $out = "$prefix<table$options[table_class]>$trailing_newline";
      if (is_array($table)) {
      if (isset($table['head'])) {
      $out .= "<thead>$trailing_newline";
      if (is_array($table['head'])) {
      foreach ($table['head'] as $row) {
      $out .= $row . $trailing_newline;
      }
      } else {
      $out .= $table['head'] . $trailing_newline;
      }
      $out .= "</thead>$trailing_newline";
      }
      if (isset($table['body'])) {
      $out .= "<tbody>";
      if (is_array($table['body'])) {
      foreach ($table['body'] as $row) {
      $out .= $row . $trailing_newline;
      }
      } else {
      $out .= $table['body'] . $trailing_newline;
      }
      $out .= "</tbody>";
      }
      } else {
      $out .= $table . $trailing_newline;
      }
      $out .= "</table>$suffix$trailing_newline";
      return $out;
      }

      function displayTable($prefix, $table, $suffix = '', $options=[]) {
      echo $this->generateTable($prefix, $table, $suffix, $options);
      }
      /* */
    static function header($title, $url = '', $calling_object = null, $popup = '') {
        global $CFG_GLPI, $CFG_PLUGIN_ISERVICE, $HEADER_LOADED, $DB;

        if (!empty($CFG_GLPI['maintenance_mode']) && IserviceToolBox::getInputVariable('skipMaintenance', null) === null) {
            echo "Siteul este in mentenanță, vă rugăm reveniți.";
            die;
        }

        plugin_iservice_check_status();

        // If in modal : display popHeader
        if (isset($_REQUEST['_in_modal']) && $_REQUEST['_in_modal']) {
            return self::popHeader($title, $url);
        }
        // Print a nice HTML-head for every page
        if ($HEADER_LOADED) {
            return;
        }
        $HEADER_LOADED = true;

        Html::includeHeader($title);

        $body_class = "layout_" . $_SESSION['glpilayout'];
        if ((strpos($_SERVER['REQUEST_URI'], ".form.php") !== false) && isset($_GET['id']) && ($_GET['id'] > 0)) {
            if (!CommonGLPI::isLayoutExcludedPage()) {
                $body_class .= " form";
            } else {
                $body_class = "";
            }
        }

        $admin_class = IserviceToolBox::inProfileArray(['admin', 'super-admin']) ? 'admin' : 'non-admin';

        // Body
        echo "<body id='iservice-body' class='$body_class $admin_class'>";
        // Generate array for menu and check right

        if (!empty($popup)) {
            echo "<script>var w=window.open('$popup', '_blank');w.focus();</script>";
        }

        echo "<div id='header'>";
        echo "<div id='c_logo'>";
        $interface = $_SESSION["glpiactiveprofile"]["iservice_interface"];
        echo Html::link('', $CFG_GLPI["root_doc"] . "/plugins/iservice/front/$interface.php", ['accesskey' => '1',
            'title' => __('Home')]);
        echo "</div>";
        echo "<div id='header_top'>";

        $post = filter_var_array($_POST);
        if (!empty($post)) {
            echo "<div id='back-link'><a href='" . filter_input(INPUT_SERVER, 'HTTP_REFERRER') . "'>«««</a></div>";
        }

        /// Prefs / Logout link
        echo "<div id='c_preference' >";
        echo "<ul>";

        echo "<li id='show_c_menu'><a id='menu_all_button' class='button-icon' href='#' onclick='$(\"#c_menu\").toggle();return false;'>&nbsp;</a></li>";

        if (Session::getLoginUserID()) {
            echo "<li id='deconnexion'>";
            echo "<a href='" . $CFG_GLPI["root_doc"] . "/front/logout.php";
            /// logout witout noAuto login for extauth
            if (isset($_SESSION['glpiextauth']) && $_SESSION['glpiextauth']) {
                echo "?noAUTO=1";
            }
            echo "' title=\"" . __s('Logout') . "\" class='fa fa-sign-out-alt'>";
            // check user id : header used for display messages when session logout
            echo "<span class='sr-only'>" . __s('Logout') . "></span>";
            echo "</a>";
            echo "</li>\n";

            // Profile selector
            Html::showProfileSelecter($CFG_GLPI["root_doc"] . "/plugins/iservice/front/$interface.php");

            echo "<li id='preferences_link'>";
            if (Session::haveRight('plugin_iservice_user_preferences', READ)) {
                echo "<a href='" . $CFG_GLPI["root_doc"] . "/front/preference.php' title=\"" . __s('My settings') . "\" class='fa fa-cog'>";
                echo "<span class='sr-only'>" . __s('My settings') . "</span>";
            }

            echo "<span id='myname'>";
            echo formatUserName(0, $_SESSION["glpiname"], $_SESSION["glpirealname"], $_SESSION["glpifirstname"], 0, 20);
            echo "</span>";
            if (Session::haveRight('plugin_iservice_user_preferences', READ)) {
                echo "</a>";
            }
            echo "</li>";

            if (Config::canUpdate()) {
                $current_mode = $_SESSION['glpi_use_mode'];
                $class = 'debug' . ($current_mode == Session::DEBUG_MODE ? 'on' : 'off');
                $title = sprintf(
                        __s('Debug mode %1$s'), ($current_mode == Session::DEBUG_MODE ? __('on') : __('off'))
                );
                echo "<li id='debug_mode'>";
                echo "<a href='{$CFG_GLPI['root_doc']}/ajax/switchdebug.php' class='fa fa-bug $class'
                title='$title'>";
                echo "<span class='sr-only'>" . __('Change mode') . "</span>";
                echo "</a>";
                echo "</li>";
            }

            if (Session::haveRight('plugin_iservice_admintask_DataIntegrityTest', READ)) {
                $pending_emails = $DB->request([
                    'FROM' => PluginIservicePendingEmail::getTable()
                ])->count();
                if ($pending_emails) {
                    $pending_emails = str_pad($pending_emails, 2, '0', STR_PAD_LEFT);
                }
                echo "<li><a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=PendingEmails' data-badge='$pending_emails' class='fa far fa-envelope fa-2x" . ($pending_emails ? ' badge' : '') . "'></a></li>";
            }

            $tester_task = new PluginIserviceTask_DataIntegrityTest();

            $hmarfa_import_lastrun_array = PluginIserviceDB::getQueryResult("select lastrun from glpi_crontasks where itemtype='PluginIserviceHMarfaImporter' and name='hMarfaImport'");
            $hmarfa_import_lastrun = $hmarfa_import_lastrun_array['0']['lastrun'];
            $hmarfa_button_color = '';

            $hmarfa_import_time_diff = abs(time() - strtotime($hmarfa_import_lastrun))/(60*60);

            if ($hmarfa_import_time_diff > 1) {
                $hmarfa_button_color = ($hmarfa_import_time_diff > 2) ? 'red' : 'orange';
            }

            $hmarfa_action_fields = [
                'execute' => 'hMarfaImport',
                '_glpi_csrf_token' => Session::getNewCSRFToken(),
                '_glpi_simple_form' => 1
            ];

            $hmarfa_action_javascriptArray = [];
            foreach ($hmarfa_action_fields as $name => $value) {
                $hmarfa_action_javascriptArray[] = "'$name': '".urlencode($value)."'";
            }
            $hmarfa_action  = " submitGetLink('" . $CFG_GLPI['root_doc'] . "/front/crontask.form.php', {" . implode(', ', $hmarfa_action_javascriptArray) . "});";

            echo "<li><a class='fa fa-upload fa-2x pointer' style='color:$hmarfa_button_color' title='Ultima execuție hMarfa import: " . $hmarfa_import_lastrun . "' onclick=\"$hmarfa_action\"></a></li>";

            /// Bookmark load
            echo "<li id='bookmark_link'>";
            Ajax::createSlidePanel(
                    'showSavedSearches', [
                'title' => __('Saved searches'),
                'url' => $CFG_GLPI['root_doc'] . '/ajax/savedsearch.php?action=show',
                'icon' => '/pics/menu_config.png',
                'icon_url' => SavedSearch::getSearchURL(),
                'icon_txt' => __('Manage saved searches')
                    ]
            );
            echo "<a href='#' id='showSavedSearchesLink' class='fa fa-star' title=\"" .
            __s('Load a bookmark') . "\">";
            echo "<span class='sr-only'>" . __('Saved searches') . "</span>";
            echo "</a></li>";

            if (Session::haveRight('plugin_iservice_user_preferences', READ)) {
                echo "<li id='language_link'><a href='" . $CFG_GLPI["root_doc"] .
                "/front/preference.php?forcetab=User\$1' title=\"" .
                addslashes(Dropdown::getLanguageName($_SESSION['glpilanguage'])) . "\">" .
                Dropdown::getLanguageName($_SESSION['glpilanguage']) . "</a></li>";
            }

            if (Session::haveRight('plugin_iservice_interface_original', READ) && $CFG_GLPI['allow_search_global']) {
                echo "<li id='c_recherche'>\n";
                echo "<form method='get' action='" . $CFG_GLPI["root_doc"] . "/front/search.php'>\n";
                echo "<span id='champRecherche'><input size='15' type='text' name='globalsearch'
                                      placeholder='" . __s('Search') . "'>";
                echo "</span>";
                Html::closeForm();
                echo "</li>";
            }
        }

        if (!empty($calling_object) && method_exists($calling_object, 'displayPreferenceData')) {
            $calling_object->displayPreferenceData();
        }

        echo "</ul>";
        echo "</div>\n";

        echo "</div>";

        ///Main menu
        echo "<div id='c_menu'>";
        echo "<ul>";
        PluginIserviceCentral::displayButtonsOnHeader(null);
        if (!empty($calling_object) && method_exists($calling_object, 'displayMenuData')) {
            $calling_object->displayMenuData();
        }
        echo "</ul>";
        echo "</div>";

        echo "</div>\n"; // fin header

        echo "<div id='page' >";

        if ($DB->isSlave() && !$DB->first_connection) {
            echo "<div id='dbslave-float'>";
            echo "<a href='#see_debug'>" . __('MySQL replica: read only') . "</a>";
            echo "</div>";
        }

        if (Session::haveRight('plugin_iservice_admintask_DataIntegrityTest', READ)) {
            $tester_task->displayResults('em_alert');
            $tester_task->displayResults('alert');
        }

        // call static function callcron() every 5min
        CronTask::callCron();
        Html::displayMessageAfterRedirect();
    }

    static function displayDebugInfos($with_session = true, $ajax = false) {
        global $CFG_GLPI, $DEBUG_SQL, $SQL_TOTAL_REQUEST, $SQL_TOTAL_TIMER, $DEBUG_AUTOLOAD;

        // Only for debug mode so not need to be translated
        if ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE) { // mode debug
            $rand = mt_rand();
            echo "<div class='debug " . ($ajax ? "debug_ajax" : "") . "'>";
            if (!$ajax) {
                echo "<span class='fa-stack fa-lg' id='see_debug'>
                     <i class='fa fa-circle fa-stack-2x primary-fg-inverse'></i>
                     <a href='#' class='fa fa-bug fa-stack-1x primary-fg' title='" . __s('Display iService debug informations') . "'>
                        <span class='sr-only'>See iService DEBUG</span>
                     </a>
            </span>";
            }

            echo "<div id='debugtabs$rand'><ul>";
            if ($CFG_GLPI["debug_sql"]) {
                echo "<li><a href='#debugsql$rand'>SQL REQUEST</a></li>";
            }
            if ($CFG_GLPI["debug_vars"]) {
                echo "<li><a href='#debugautoload$rand'>AUTOLOAD</a></li>";
                echo "<li><a href='#debugpost$rand'>POST VARIABLE</a></li>";
                echo "<li><a href='#debugget$rand'>GET VARIABLE</a></li>";
                if ($with_session) {
                    echo "<li><a href='#debugsession$rand'>SESSION VARIABLE</a></li>";
                }
                echo "<li><a href='#debugserver$rand'>SERVER VARIABLE</a></li>";
            }
            echo "</ul>";

            if ($CFG_GLPI["debug_sql"]) {
                foreach (array_keys($DEBUG_SQL['queries']) as $i) {
                    $sql_debug_data[] = [
                        'ord' => $i,
                        'time' => $DEBUG_SQL['times'][$i],
                        'query' => $DEBUG_SQL['queries'][$i],
                        'error' => empty($DEBUG_SQL['errors'][$i]) ? '' : $DEBUG_SQL['errors'][$i],
                    ];
                }
                usort($sql_debug_data, function ($a, $b) {
                    return -strcmp($a["time"], $b["time"]);
                });

                echo "<div id='debugsql$rand'>";
                echo "<div class='b'>" . $SQL_TOTAL_REQUEST . " Queries ";
                echo "took  " . array_sum($DEBUG_SQL['times']) . "s</div>";

                echo "<table class='tab_cadre'><tr><th>N&#176; </th><th>Queries</th><th>Time</th>";
                echo "<th>Errors</th></tr>";

                foreach ($sql_debug_data as $num => $data) {
                    echo "<tr class='tab_bg_" . (($num % 2) + 1) . "'><td>$data[ord]</td><td>";
                    echo Html::cleanSQLDisplay($data['query']);
                    echo "</td><td>$data[time]</td><td>";
                    echo empty($data['error']) ? "&nbsp;" : $data['error'];
                    echo "</td></tr>";
                }
                echo "</table>";
                echo "</div>";
            }
            if ($CFG_GLPI["debug_vars"]) {
                echo "<div id='debugautoload$rand'>" . implode(', ', $DEBUG_AUTOLOAD) . "</div>";
                echo "<div id='debugpost$rand'>";
                Html::printCleanArray($_POST, 0, true);
                echo "</div>";
                echo "<div id='debugget$rand'>";
                Html::printCleanArray($_GET, 0, true);
                echo "</div>";
                if ($with_session) {
                    echo "<div id='debugsession$rand'>";
                    Html::printCleanArray($_SESSION, 0, true);
                    echo "</div>";
                }
                echo "<div id='debugserver$rand'>";
                Html::printCleanArray($_SERVER, 0, true);
                echo "</div>";
            }

            echo Html::scriptBlock("
            $('#debugtabs$rand').tabs({
               collapsible: true
            }).addClass( 'ui-tabs-vertical ui-helper-clearfix' );

            $('<li class=\"close\"><button id= \"close_debug$rand\">close debug</button></li>')
               .appendTo('#debugtabs$rand ul');

            $('#close_debug$rand').button({
               icons: {
                  primary: 'ui-icon-close'
               },
               text: false
            }).click(function() {
                $('#debugtabs$rand').css('display', 'none');
            });

            $('#see_debug').click(function(e) {
               e.preventDefault();
               console.log('see_debug #debugtabs$rand');
               $('#debugtabs$rand').css('display', 'block');
            });
         ");

            echo "</div></div>";
        }
    }

    static function footer($keepDB = false) {
        global $CFG_GLPI, $FOOTER_LOADED, $TIMER_DEBUG;

        $in_modal = IserviceToolBox::getInputVariable('_in_modal', false);
        // If in modal : display popHeader
        if ($in_modal) {
            return Html::popFooter();
        }

        // Print foot for every page
        if ($FOOTER_LOADED) {
            return;
        }
        $FOOTER_LOADED = true;
        echo "</div>"; // fin de la div id ='page' initiée dans la fonction header

        echo "<div id='footer' >";
        echo "<table width='100%'><tr><td class='left'><span class='copyright'>";
        $timedebug = sprintf(_n('%s second', '%s seconds', $TIMER_DEBUG->getTime()), $TIMER_DEBUG->getTime());

        if (function_exists("memory_get_usage")) {
            $timedebug = sprintf(__('%1$s - %2$s'), $timedebug, Toolbox::getSize(memory_get_usage()));
        }
        echo $timedebug;
        if (Session::haveRight('plugin_iservice_admintask_DataIntegrityTest', READ)) {
            echo " - Selftest [", implode(', ', PluginIserviceTask_DataIntegrityTest::getLoadTimes()), "]";
        }
        echo "</span></td>";

        echo "<td class='right'>" . self::getCopyrightMessage() . "</td>";
        echo "</tr></table></div>";

        if ($_SESSION['glpi_use_mode'] == Session::TRANSLATION_MODE) { // debug mode traduction
            echo "<div id='debug-float'>";
            echo "<a href='#see_debug'>iService TRANSLATION MODE</a>";
            echo "</div>";
        }

        if ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE) { // mode debug
            echo "<div id='debug-float'>";
            echo "<a href='#see_debug'>iService DEBUG MODE</a>";
            echo "</div>";
        }
        if (!empty($CFG_GLPI['maintenance_mode'])) { // mode maintenance
            echo "<div id='maintenance-float'>";
            echo "<a href='#see_maintenance'>iService MAINTENANCE MODE</a>";
            echo "</div>";
        }
        self::displayDebugInfos();
        Html::loadJavascript();
        echo "</body></html>";

        if (!$keepDB) {
            closeDBConnections();
        }
    }

    static function getCopyrightMessage() {
        $message_template = '<a href="%s" title="Powered by hupu" class="copyright">iService %s - Copyright (C) 2010-%d Expert Line</a>';
        return sprintf($message_template, 'http://expertline.ro/', isset($CFG_GLPI["version"]) ? $CFG_GLPI['version'] : GLPI_VERSION, GLPI_YEAR);
    }

    static function displayErrorAndDie(string $message)
    {
        TemplateRenderer::getInstance()->display('display_and_die.html.twig', [
            'title'   => __('Access denied'),
            'message' => $message,
        ]);

        Html::nullFooter();
        exit();
    }
}

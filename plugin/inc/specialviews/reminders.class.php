<?php

// Imported from iService2, needs refactoring. Original file: "Reminders.php".
namespace GlpiPlugin\Iservice\Specialviews;

use Glpi\Toolbox\Sanitizer;
use GlpiPlugin\Iservice\Views\View;
use Html;
use Planning;
use PluginIserviceReminder;
use \Session;
use Toolbox;

class Reminders extends View
{
    public static $rightname = 'plugin_iservice_view_reminders';

    public static $icon = 'fa-fw ti ti-note';

    public static function getName(): string
    {
        return _n('Reminder', 'Reminders', Session::getPluralNumber());
    }

    public static function getRowBackgroundClass($row_data): string
    {
        return $row_data['state'] == Planning::TODO ? "tab_bg_3" : "tab_bg_1";
    }

    public static function getVisibilityDisplay($row_data): string
    {
        switch ($row_data['visibility']) {
        case 1:
            return "Da";
        default:
            return "Nu";
        }
    }

    public static function getStatusDisplay($row_data): string
    {
        return Planning::getState($row_data['state']);
    }

    public static function getTextDisplay($row_data): string
    {
        return Toolbox::stripTags(implode(' ', Sanitizer::decodeHtmlSpecialCharsRecursive([$row_data['text']])));
    }

    protected function getSettings(): array
    {
        global $CFG_GLPI;
        $iservice_front   = $CFG_GLPI['root_doc'] . "/plugins/iservice/front/";
        $reminder_buttons = [];
        if (Session::haveRight(PluginIserviceReminder::$rightname, CREATE)) {
            $reminder_buttons[] = "<a class='submit noprint' href='{$iservice_front}reminder.form.php'>" . __('Add') . " " . _n('Reminder', 'Reminders', 1) . "</a>";
        }

        $visibility_conditions[] = "er.entities_id = 0";
        $visibility_conditions[] = "r.begin_view_date IS NULL OR r.begin_view_date < NOW()";
        $visibility_conditions[] = "r.end_view_date IS NULL OR r.end_view_date > NOW()";
        $visibility_condition    = "r.users_id = $_SESSION[glpiID] OR ((" . implode(") AND (", $visibility_conditions) . "))";

        return [
            'name' => self::getName(),
            'prefix' => implode('&nbsp;&nbsp;&nbsp;', $reminder_buttons),
            'query' => "
						SELECT
						    r.id
							, r.name
							, r.state
							, r.text
							, r.date
							, CASE er.entities_id WHEN 0 THEN 1 ELSE 0 END visibility
							, CONCAT(IFNULL(CONCAT(u.realname, ' '),''), IFNULL(u.firstname, '')) user_name
						FROM glpi_reminders r
						LEFT JOIN glpi_entities_reminders er ON er.reminders_id = r.id
						LEFT JOIN glpi_users u ON u.id = r.users_id
						WHERE ($visibility_condition)
							AND date <= '[date]'
							AND r.name LIKE '[name]'
							AND r.state IN ([state])
							AND r.text LIKE '[text]'
							[visibility]
							[user_id]
						",
            'default_limit' => 50,
            'row_class' => 'function:\GlpiPlugin\Iservice\Specialviews\Reminders::getRowBackgroundClass($row_data);',
            'filters' => [
                'date' => [
                    'type' => self::FILTERTYPE_DATE,
                    'caption' => '',
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => date('Y-m-d'),
                    'header' => 'date',
                    'header_caption' => '< ',
                ],
                'name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'header' => 'name',
                    'format' => '%%%s%%',
                ],
                'visibility' => [
                    'type' => self::FILTERTYPE_SELECT,
                    'header' => 'visibility',
                    'options' => [
                        '' => 'Toate',
                        '0' => 'Nu',
                        '1' => 'Da',
                    ],
                    'zero_is_empty' => false,
                    'format' => 'AND CASE er.entities_id WHEN 0 THEN 1 ELSE 0 END = %d',
                ],
                'user_id' => [
                    'type' => self::FILTERTYPE_USER,
                    'format' => 'AND u.id = %d',
                    'header' => 'user_name',
                ],
                'state' => [
                    'type' => self::FILTERTYPE_SELECT,
                    'header' => 'state',
                    'options' => [
                        implode(',', [Planning::INFO, Planning::TODO]) => 'Info + ' . __('To do'),
                        Planning::INFO => _n('Information', 'Information', 1),
                        Planning::TODO => __('To do'),
                        Planning::DONE => __('Done'),
                        implode(',', [Planning::INFO, Planning::TODO, Planning::DONE]) => 'Toate',
                    ],
                    'zero_is_empty' => false,
                    'format' => '%s',
                    'empty_value' => implode(',', [Planning::INFO, Planning::TODO]),
                ],
                'text' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'header' => 'text',
                    'format' => '%%%s%%',
                ],
            ],
            'columns' => [
                'date' => [
                    'title' => 'Dată',
                    'default_sort' => 'DESC',
                ],
                'name' => [
                    'title' => 'Titlu',
                    'link' => [
                        'href' => $iservice_front . 'reminder.form.php?id=[id]',
                    ],
                ],
                'visibility' => [
                    'title' => 'Public',
                    'format' => 'function:\GlpiPlugin\Iservice\Specialviews\Reminders::getVisibilityDisplay($row);'
                ],
                'user_name' => [
                    'title' => 'Redactor',
                ],
                'state' => [
                    'title' => 'Status',
                    'format' => 'function:\GlpiPlugin\Iservice\Specialviews\Reminders::getStatusDisplay($row);',
                ],
                'text' => [
                    'title' => 'Descriere',
                    'format' => 'function:\GlpiPlugin\Iservice\Specialviews\Reminders::getTextDisplay($row);',
                ],
            ],
        ];
    }

}

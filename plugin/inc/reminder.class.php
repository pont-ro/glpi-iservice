<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use Glpi\RichText\RichText;
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

/**
 * PluginIserviceReminder Class
 * */
class PluginIserviceReminder extends Reminder
{

    public static $rightname = 'plugin_iservice_view_reminders';

    public static function getTable($classname = null)
    {
        return Reminder::getTable($classname);
    }

    public function display($options = [])
    {
        $options = IserviceToolBox::filterVarArray(INPUT_GET);
        if (isset($options['id']) && !$this->isNewID($options['id'])) {
            if (!$this->getFromDB($options['id'])) {
                Html::displayNotFoundError();
            }
        } else {
            $options['id'] = '';
        }

        $valid_display_options = ['mode'];
        $display_options       = [];
        foreach ($options as $key => $value) {
            if (in_array($key, $valid_display_options)) {
                $display_options[$key] = $value;
                unset($_REQUEST[$key]);
            }
        }

        $this->showForm($options['id'], $display_options);
    }

    public function showForm($ID, $options = [])
    {
        global $CFG_GLPI;

        $html = new PluginIserviceHtml();

        $html->openForm(
            [
                'action' => $this->getFormURL(),
                'class' => ['iservice-form', 'two-column', 'reminder'],
                'method' => 'post',
            ]
        );

        $buttons = [];
        $canedit = $this->can($ID, UPDATE);

        Html::initEditorSystem('text');

        if ($this->isNewID($ID)) {
            $table_head_text = sprintf(__('%1$s - %2$s'), __('New item'), $this->getTypeName(1));
            $buttons[]       = $html->generateSubmit('add', __('Add'));
        } else {
            $html->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'id', $this->getID());
            $table_head_text = sprintf(__('%1$s - ID %2$d'), $this->getTypeName(1), $ID);
            if ($canedit) {
                $buttons[] = $html->generateSubmit('update', __('Save'));
            }
        }

        $form_header[] = "<tr><th colspan=2>$table_head_text</th></tr>";

        // Name.
        $form_rows[] = $html->generateFieldTableRow(__('Title'), $html->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'name', $this->fields['name']));

        // Visibility.
        $visibility_dropdown_options = [
            'method' => 'showFromArray',
            'values' => [
                '0' => 'Nu',
                '1' => 'Da',
            ],
        ];
        $visibility                  = array_key_exists(0, $this->entities) ? '1' : '0';
        $form_rows[]                 = $html->generateFieldTableRow('Public', $html->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'visibility', $visibility, false, $visibility_dropdown_options));

        // User.
        $user_dropdown_options = [
            'type' => 'User',
        ];
        if (empty($this->fields['users_id'])) {
            $this->fields['users_id'] = $_SESSION['glpiID'];
        }

        $form_rows[] = $html->generateFieldTableRow('Redactor', $html->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'users_id', $this->fields['users_id'], true, $user_dropdown_options));

        // State.
        $state_dropdown_options = [
            'type' => 'Planning',
            'method' => 'dropdownState',
            'arguments' => [
                'name' => 'state',
                'value' => $this->fields['state'],
                'display' => false,
            ],
        ];
        $form_rows[]            = $html->generateFieldTableRow(__('Status'), $html->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'state', $this->fields['state'], false, $state_dropdown_options));

        // Description.
        $form_rows[] = $html->generateFieldTableRow(
            __('Description'), Html::textarea(
                ['name'              => 'text',
                    'value'             => RichText::getSafeHtml($this->fields["text"], true),
                    'enable_richtext'   => true,
                    'display'           => false,
                ]
            )
        );

        // Buttons.
        $form_rows[] = "<tr class='buttons'><td colspan=2>" . implode('', $buttons) . "</td></tr>";

        // Create the table.
        $form_table = new PluginIserviceHtml_table('tab_cadre_fixe', $form_header, $form_rows);
        echo $form_table;

        $html->closeForm();
        return true;
    }

    public function post_addItem()
    {
        parent::post_addItem();
        $this->adjustVisibility();
    }

    public function post_updateItem($history = 1)
    {
        parent::post_updateItem($history);
        $this->adjustVisibility();
    }

    public function adjustVisibility()
    {
        if (isset($this->input['visibility'])) {
            $entity_reminder = new Entity_Reminder();
            $already_visible = PluginIserviceDB::populateByQuery($entity_reminder, "WHERE reminders_id = {$this->getID()}  LIMIT 1");
            if ($this->input['visibility']) {
                if (!$already_visible) {
                    $entity_reminder->add(['add' => 'add', 'reminders_id' => $this->getID(), 'entities_id' => 0, '_no_message' => true]);
                }
            } elseif ($already_visible) {
                $entity_reminder->delete([$entity_reminder->getIndexName() => $entity_reminder->getID()]);
            }
        }
    }

    public function haveVisibilityAccess()
    {
        return parent::haveVisibilityAccess() || in_array($_SESSION["glpiactiveprofile"]["name"], ['super-admin']);
    }

    public function canUpdateItem()
    {
        return ($this->fields['users_id'] == Session::getLoginUserID() || in_array($_SESSION["glpiactiveprofile"]["name"], ['super-admin'])) && parent::canUpdateItem();
    }

    public static function canCreate()
    {
        return (Session::haveRightsOr(self::$rightname, [CREATE, self::PERSONAL]));
    }

}

<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use GlpiPlugin\Iservice\Views\Views;

class PluginIserviceTicket extends Ticket
{

    use PluginIserviceItem, PluginIserviceCommonITILObject;

    /*
     *
     * @var PluginFieldsTicketcustomfield
     */
    public $customfields = null;

    const MODE_NONE                = 0;
    const MODE_CREATENORMAL        = 1;
    const MODE_READCOUNTER         = 2;
    const MODE_CREATEINQUIRY       = 3;
    const MODE_CREATEQUICK         = 4;
    const MODE_MODIFY              = 5;
    const MODE_CREATEREQUEST       = 6;
    const MODE_PARTNERCONTACT      = 7;
    const MODE_CARTRIDGEMANAGEMENT = 9;
    const MODE_HMARFAEXPORT        = 1000;
    const MODE_CLOSE               = 9999;

    const USER_ID_READER = 27;

    const ITIL_CATEGORY_ID_CITIRE_CONTOR         = 17;
    const ITIL_CATEGORY_ID_CITIRE_CONTOR_ESTIMAT = 30;
    const ITIL_CATEGORY_ID_CITIRE_EMAINTENANCE   = 28;

    public static $field_settings          = null;
    public static $field_settings_id       = 0;
    protected static $itil_categories      = null;
    protected static $installed_cartridges = [];

    public static function getFormModeUrl($mode): string
    {
        switch ($mode) {
        case self::MODE_READCOUNTER:
            return "ticket.form.php?mode=$mode&_redirect_on_success=" . urlencode('view.php?view=tickets');
        default:
            return "ticket.form.php?mode=$mode";
        }
    }

    public static function getRedirectOnSuccessLink($mode): string
    {
        switch ($mode) {
        case self::MODE_READCOUNTER:
            return urlencode('view.php?view=tickets');
        default:
            return '';
        }
    }

    public static function getItilCategoryId($itilcategory_name): int
    {
        if (self::$itil_categories == null) {
            self::refreshItilCategories();
        }

        if (isset(self::$itil_categories[strtolower($itilcategory_name)])) {
            return self::$itil_categories[strtolower($itilcategory_name)];
        } else {
            return 0;
        }
    }

    public static function refreshItilCategories(): void
    {
        global $DB;
        self::$itil_categories  = [];
        $itil_categories_result = $DB->query("SELECT id, name FROM glpi_itilcategories") or die($DB->error());
        while (($itil_category_row = $DB->fetchAssoc($itil_categories_result)) !== null) {
            self::$itil_categories[strtolower($itil_category_row['name'])] = $itil_category_row['id'];
        }
    }

    public static function getType(): string
    {
        return Ticket::getType();
    }

    public static function getModeTemplate($mode = 0): int
    {
        switch ($mode) {
        case self::MODE_CLOSE:
            return 5;
        case self::MODE_CREATENORMAL:
        case self::MODE_READCOUNTER:
        case self::MODE_CREATEINQUIRY:
        case self::MODE_CREATEQUICK:
        case self::MODE_CREATEREQUEST:
        case self::MODE_PARTNERCONTACT:
        case self::MODE_CARTRIDGEMANAGEMENT:
            return $mode;
            // Do not include MODE_MODIFY here, it should not have a template (yet!).
            // Neither does MODE_HMARFAEXPORT.
        default:
            return 0;
        }
    }

    public static function getFieldSettings($id): array
    {
        if (self::$field_settings === null || self::$field_settings_id !== $id) {
            self::$field_settings_id = $id;
            self::$field_settings    = [
                '_auto_import' => [
                    'default' => 1,
                    'hidden' => true,
                ],
                'users_id_recipient' => [
                    'default' => $_SESSION["glpiID"],
                    'hidden' => true,
                ],
                '_users_id_requester' => [
                    'default' => $_SESSION["glpiID"],
                    'hidden' => true,
                ],
                '_contact_partner' => [
                    'default' => [
                        self::MODE_PARTNERCONTACT => true,
                    ],
                    'hidden' => true,
                ],
                '_close_on_success' => [
                    'default' => [
                        self::MODE_PARTNERCONTACT => false,
                    ],
                    'hidden' => true,
                ],
                '_movement_id' => [
                    'hidden' => true,
                ],
                '_movement2_id' => [
                    'hidden' => true,
                ],
                '_idemmailfield' => [
                    'hidden' => true,
                ],
                '_users_id_observer' => [
                    'hidden' => [
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_PARTNERCONTACT => true,
                    ],
                    'readonly' => [
                        self::MODE_HMARFAEXPORT => true,
                    ],
                ],
                '_users_id_assign' => [
                    'default' => [
                        self::MODE_READCOUNTER => in_array($_SESSION["glpiactiveprofile"]["name"], ['client', 'superclient', 'subtehnician', 'tehnician']) ? $_SESSION["glpiID"] : self::USER_ID_READER,
                        self::MODE_CREATEINQUIRY => in_array($_SESSION["glpiactiveprofile"]["name"], ['client', 'superclient', 'subtehnician', 'tehnician']) ? $_SESSION["glpiID"] : self::USER_ID_READER,
                        self::MODE_PARTNERCONTACT => $_SESSION["glpiID"],
                    ],
                    'hidden' => [
                        self::MODE_READCOUNTER => !in_array($_SESSION["glpiactiveprofile"]["name"], ['tehnician', 'admin', 'super-admin']),
                        self::MODE_CREATEINQUIRY => in_array($_SESSION["glpiactiveprofile"]["name"], ['client', 'superclient', 'subtehnician', 'tehnician']),
                        self::MODE_CREATEREQUEST => true,
                    ],
                    'readonly' => [
                        self::MODE_HMARFAEXPORT => true,
                    ],
                    'required' => [
                        self::MODE_MODIFY => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                        self::MODE_CLOSE => true,
                    ],
                ],
                'type' => [
                    'default' => parent::INCIDENT_TYPE,
                    'hidden' => true,
                ],
                '_suppliers_id_assign' => [
                    'hidden' => in_array($_SESSION["glpiactiveprofile"]["name"], ['client', 'superclient']),
                    'readonly' => [
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_HMARFAEXPORT => true,
                    ],
                ],
                'items_id[Printer][0]' => [
                    'hidden' => [
                        self::MODE_PARTNERCONTACT => true,
                    ],
                    'readonly' => [
                        self::MODE_HMARFAEXPORT => true,
                    ],
                    'required' => [
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                    ],
                ],
                '_usageaddressfield' => [
                    'hidden' => false
                ],
                'locations_id' => [
                    'hidden' => [
                        self::MODE_CREATENORMAL => true,
                        self::MODE_CREATEQUICK => true,
                        self::MODE_HMARFAEXPORT => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                        self::MODE_CLOSE => true,
                    ]
                ],
                '_email' => [
                    'hidden' => [
                        self::MODE_NONE => true,
                        self::MODE_CREATENORMAL => true,
                        self::MODE_CREATEQUICK => true,
                        self::MODE_MODIFY => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                        self::MODE_HMARFAEXPORT => true,
                        self::MODE_CLOSE => true,
                    ]
                ],
                '_send_email' => [
                    'default' => [
                        self::MODE_READCOUNTER => in_array($_SESSION["glpiactiveprofile"]["name"], ['client', 'superclient']),
                        self::MODE_CREATEINQUIRY => !in_array($_SESSION["glpiactiveprofile"]["name"], ['subtehnician']),
                    ],
                    'hidden' => [
                        self::MODE_NONE => true,
                        self::MODE_CREATENORMAL => true,
                        self::MODE_CREATEQUICK => true,
                        self::MODE_MODIFY => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                        self::MODE_HMARFAEXPORT => true,
                        self::MODE_CLOSE => true,
                    ]
                ],
                '_sum_of_unpaid_invoices' => [
                    'hidden' => [
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_MODIFY => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                        self::MODE_CLOSE => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                    ],
                ],
                '_last_invoice_and_counters' => [
                    'hidden' => [
                        self::MODE_CREATENORMAL => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                    ],
                ],
                'total2_black' => [
                    'hidden' => [
                        self::MODE_NONE => true,
                        self::MODE_CREATENORMAL => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_MODIFY => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                    ],
                    'readonly' => [
                        self::MODE_HMARFAEXPORT => true,
                    ],
                ],
                'total2_color' => [
                    'hidden' => [
                        self::MODE_NONE => true,
                        self::MODE_CREATENORMAL => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_MODIFY => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                    ],
                    'readonly' => [
                        self::MODE_HMARFAEXPORT => true,
                    ],
                ],
                'itilcategories_id' => [
                    'default' => [
                        self::MODE_CREATENORMAL => self::getItilCategoryId('interventie regulata'),
                        self::MODE_READCOUNTER => self::getItilCategoryId('citire contor'),
                        self::MODE_CREATEINQUIRY => in_array($_SESSION["glpiactiveprofile"]["name"], ['client', 'superclient']) ? self::getItilCategoryId('citire contor') : self::getItilCategoryId('sesizare externa'),
                        self::MODE_CREATEREQUEST => self::getItilCategoryId('sesizare externa'),
                        self::MODE_PARTNERCONTACT => self::getItilCategoryId('plati'),
                    ],
                    'hidden' => [
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                        self::MODE_CLOSE => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                    ],
                    'readonly' => [
                        self::MODE_HMARFAEXPORT => true,
                    ],
                    'required' => true,
                ],
                'name' => [
                    'default' => [
                        self::MODE_READCOUNTER => 'citire contor',
                        self::MODE_CREATEQUICK => 'tichet rapid',
                    ],
                    'hidden' => [
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                    ],
                    'readonly' => [
                        self::MODE_HMARFAEXPORT => true,
                    ],
                    'required' => true,
                ],
                'content' => [
                    'default' => [
                        self::MODE_READCOUNTER => 'periodic',
                        self::MODE_CREATEQUICK => 'tichet rapid',
                    ],
                    'hidden' => [
                        self::MODE_READCOUNTER => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                    ],
                    'readonly' => [
                        self::MODE_HMARFAEXPORT => true,
                        self::MODE_CLOSE => true,
                    ]
                ],
                '_followup[content]' => [
                    'default' => [
                        self::MODE_READCOUNTER => 'citire contor',
                        self::MODE_CREATEQUICK => 'tichet rapid',
                    ],
                    'forced' => [
                        self::MODE_READCOUNTER => $id > 0 ? '' : 'citire contor',
                        self::MODE_CREATEQUICK => $id > 0 ? '' : 'tichet rapid',
                        self::MODE_MODIFY => '',
                        self::MODE_CLOSE => '',
                    ],
                    'hidden' => [
                        self::MODE_NONE => true,
                        self::MODE_CREATENORMAL => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                        self::MODE_HMARFAEXPORT => true,
                    ],
                    'required' => [
                        self::MODE_READCOUNTER => !($id > 0),
                    ],
                ],
                '_followup[is_private]' => [
                    'hidden' => [
                        self::MODE_NONE => true,
                        self::MODE_CREATENORMAL => true,
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        // self::MODE_MODIFY => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                        self::MODE_HMARFAEXPORT => true,
                        self::MODE_CLOSE => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                    ],
                ],
                '_all_followup' => [
                    'hidden' => [
                        self::MODE_NONE => true,
                        self::MODE_CREATENORMAL => true,
                        self::MODE_READCOUNTER => !($id > 0),
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEQUICK => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_PARTNERCONTACT => true,
                    ],
                    'readonly' => [
                        self::MODE_MODIFY => true,
                        self::MODE_HMARFAEXPORT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                        self::MODE_CLOSE => true,
                    ],
                ],
                '_available_cartridges' => [
                    'hidden' => [
                        self::MODE_NONE => true,
                        self::MODE_CREATENORMAL => false,
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEQUICK => true,
                        self::MODE_MODIFY => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                        self::MODE_HMARFAEXPORT => true,
                        self::MODE_CLOSE => true,
                    ],
                ],
                '_change_cartridge' => [
                    'hidden' => [
                        self::MODE_NONE => true,
                        self::MODE_CREATENORMAL => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEQUICK => true,
                        self::MODE_MODIFY => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => false,
                        self::MODE_HMARFAEXPORT => true,
                        self::MODE_CLOSE => false,
                    ],
                ],
                '_cartridge_installation' => [
                    'default' => [
                        self::MODE_READCOUNTER => date('Y-m-d'),
                    ],
                    'hidden' => [
                        self::MODE_NONE => true,
                        self::MODE_CREATENORMAL => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEQUICK => true,
                        self::MODE_MODIFY => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => false,
                        self::MODE_HMARFAEXPORT => true,
                        self::MODE_CLOSE => false,
                    ],
                ],
                'status' => [
                    'default' => [
                        self::MODE_CREATENORMAL => parent::INCOMING,
                        self::MODE_CREATEINQUIRY => IserviceToolBox::inProfileArray(['tehnician', 'admin', 'super-admin']) ? parent::INCOMING : parent::SOLVED,
                        self::MODE_CREATEQUICK => parent::CLOSED,
                        self::MODE_CREATEREQUEST => parent::INCOMING,
                        self::MODE_PARTNERCONTACT => parent::CLOSED,
                    ],
                    'forced' => [
                        self::MODE_READCOUNTER => IserviceToolBox::inProfileArray(['tehnician', 'admin', 'super-admin']) ? parent::CLOSED : parent::SOLVED,
                        self::MODE_CREATEQUICK => parent::CLOSED,
                        self::MODE_MODIFY => parent::PLANNED,
                    ],
                    'hidden' => [
                        self::MODE_CREATENORMAL => true,
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEQUICK => true,
                        self::MODE_MODIFY => false,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => false,
                        self::MODE_CLOSE => false,
                    ],
                    'readonly' => [
                        self::MODE_HMARFAEXPORT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                        self::MODE_CLOSE => true,
                    ],
                ],
                '_operator_reading' => [
                    'default' => [
                        self::MODE_NONE => false,
                        self::MODE_CREATENORMAL => false,
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => false,
                        self::MODE_CREATEQUICK => false,
                        self::MODE_MODIFY => false,
                        self::MODE_CREATEREQUEST => false,
                        self::MODE_PARTNERCONTACT => false,
                        self::MODE_CARTRIDGEMANAGEMENT => false,
                        self::MODE_CLOSE => false,
                    ],
                    'hidden' => [
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_HMARFAEXPORT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                        self::MODE_CLOSE => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                    ],
                ],
                '_without_papers' => [
                    'default' => [
                        self::MODE_NONE => false,
                        self::MODE_CREATENORMAL => false,
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEQUICK => false,
                        self::MODE_MODIFY => false,
                        self::MODE_CREATEREQUEST => false,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => false,
                        self::MODE_CLOSE => false,
                    ],
                    'hidden' => [
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_HMARFAEXPORT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                        self::MODE_CLOSE => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                    ],
                ],
                '_without_moving' => [
                    'default' => [
                        self::MODE_PARTNERCONTACT => true,
                    ],
                    'hidden' => [
                        self::MODE_CREATENORMAL => true,
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CARTRIDGEMANAGEMENT => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                        self::MODE_CLOSE => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                    ],
                ],
                '_save_progress' => [
                    'hidden' => true,
                /*
                  array(
                  self::MODE_CREATENORMAL => true,
                  self::MODE_READCOUNTER => true,
                  self::MODE_CREATEINQUIRY => true,
                  self::MODE_CREATEQUICK => true,
                  self::MODE_MODIFY => true,
                  self::MODE_CREATEREQUEST => true,
                  self::MODE_PARTNERCONTACT => true,
                  self::MODE_CARTRIDGEMANAGEMENT => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                  self::MODE_HMARFAEXPORT => true,
                  self::MODE_CLOSE => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                  ),
                  /*
                */
                ],
                '_cartridges' => [
                    'hidden' => [
                        self::MODE_CREATENORMAL => true,
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEQUICK => true,
                        self::MODE_MODIFY => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                        self::MODE_HMARFAEXPORT => true,
                        self::MODE_CLOSE => true,
                    ],
                ],
                '_consumables' => [
                    'hidden' => [
                        self::MODE_CREATENORMAL => false,
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEQUICK => false,
                        self::MODE_MODIFY => false,
                        self::MODE_CREATEREQUEST => false,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                        self::MODE_HMARFAEXPORT => true,
                        self::MODE_CLOSE => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                    ],
                ],
                '_export_type' => [
                    'hidden' => [
                        self::MODE_CREATENORMAL => false,
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEQUICK => false,
                        self::MODE_MODIFY => false,
                        self::MODE_CREATEREQUEST => false,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                        self::MODE_HMARFAEXPORT => true,
                        self::MODE_CLOSE => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                    ],
                    'readonly' => [
                        self::MODE_HMARFAEXPORT => true,
                    ],
                ],
                '_delivered' => [
                    'default' => false,
                    'hidden' => [
                        self::MODE_CREATENORMAL => true,
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEQUICK => true,
                        self::MODE_MODIFY => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                        self::MODE_CLOSE => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                    ],
                    'readonly' => true,
                ],
                '_exported' => [
                    'default' => [
                        self::MODE_NONE => false,
                        self::MODE_CREATENORMAL => false,
                        self::MODE_READCOUNTER => false,
                        self::MODE_CREATEINQUIRY => false,
                        self::MODE_CREATEQUICK => false,
                        self::MODE_MODIFY => false,
                        self::MODE_CREATEREQUEST => false,
                        self::MODE_PARTNERCONTACT => false,
                        self::MODE_CARTRIDGEMANAGEMENT => false,
                        self::MODE_CLOSE => false,
                    ],
                    'hidden' => [
                        self::MODE_CREATENORMAL => true,
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEQUICK => true,
                        self::MODE_MODIFY => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                        self::MODE_CLOSE => $_SESSION["glpiactiveprofile"]["name"] == 'subtehnician',
                    ],
                    'readonly' => true,
                ],
                '_services_invoiced' => [
                    'default' => false,
                    'hidden' => [
                        self::MODE_CREATENORMAL => true,
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEQUICK => true,
                        self::MODE_MODIFY => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                        self::MODE_CLOSE => false,
                    ],
                ],
                'effective_date' => [
                    'default' => [
                        self::MODE_CREATENORMAL => date('Y-m-d H:i:s'),
                        self::MODE_READCOUNTER => date('Y-m-d H:i:s'),
                        self::MODE_CREATEQUICK => date('Y-m-d H:i:s'),
                        self::MODE_PARTNERCONTACT => date('Y-m-d H:i:s'),
                    ],
                    'hidden' => [
                        self::MODE_CREATENORMAL => false,
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEQUICK => false,
                        self::MODE_MODIFY => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => false,
                        self::MODE_HMARFAEXPORT => false,
                        self::MODE_CLOSE => false,
                    ],
                    'readonly' => [
                        self::MODE_HMARFAEXPORT => true,
                    ],
                    'required' => [
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                        self::MODE_CLOSE => true,
                    ],
                ],
                '_notificationmailfield' => [
                    'hidden' => [
                        self::MODE_CREATENORMAL => true,
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEQUICK => true,
                        self::MODE_MODIFY => false,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                        self::MODE_CLOSE => false,
                    ],
                    'readonly' => false,
                ],
                '_printer_min_percentage' => [
                    'hidden' => false,
                ],
                '_last_tickets' => [
                    'hidden' => [
                        self::MODE_CREATENORMAL => false,
                        self::MODE_READCOUNTER => false,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEQUICK => false,
                        self::MODE_MODIFY => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_HMARFAEXPORT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => false,
                        self::MODE_PARTNERCONTACT => true,
                        self::MODE_CLOSE => false,
                    ],
                ],
                '_last_tickets_plati' => [
                    'hidden' => [
                        self::MODE_CREATENORMAL => true,
                        self::MODE_READCOUNTER => true,
                        self::MODE_CREATEINQUIRY => true,
                        self::MODE_CREATEQUICK => true,
                        self::MODE_MODIFY => true,
                        self::MODE_CREATEREQUEST => true,
                        self::MODE_HMARFAEXPORT => true,
                        self::MODE_CARTRIDGEMANAGEMENT => true,
                        self::MODE_PARTNERCONTACT => false,
                        self::MODE_CLOSE => true,
                    ],
                ],
            ];
        }

        return self::$field_settings;
    }

    public static function getTable($classname = null): string
    {
        return Ticket::getTable($classname);
    }

    public static function getDefaultValues($mode = 0, $id = 0): array
    {
        $default_values = parent::getDefaultValues(0);
        foreach (self::getFieldSettings($id) as $field_name => $field_setting) {
            if (isset($field_setting['default'])) {
                if (!is_array($field_setting['default'])) {
                    $default_values[$field_name] = $field_setting['default'];
                } else if (isset($field_setting['default'][$mode])) {
                    $default_values[$field_name] = $field_setting['default'][$mode];
                }
            }
        }

        return $default_values;
    }

    public static function getForcedValues($mode = 0, $id = 0): array
    {
        $forced_values = [];
        foreach (self::getFieldSettings($id) as $field_name => $field_setting) {
            if (isset($field_setting['forced'])) {
                if (!is_array($field_setting['forced'])) {
                    $forced_values[$field_name] = $field_setting['forced'];
                } else if (isset($field_setting['forced'][$mode])) {
                    $forced_values[$field_name] = $field_setting['forced'][$mode];
                }
            }
        }

        return $forced_values;
    }

    public static function getHiddenFields($mode = 0, $id = 0): array
    {
        $hidden_fields = [];
        foreach (self::getFieldSettings($id) as $field_name => $field_setting) {
            if (!isset($field_setting['hidden'])) {
                $hidden_fields[$field_name] = false;
            } else if (!is_array($field_setting['hidden'])) {
                $hidden_fields[$field_name] = $field_setting['hidden'];
            } else if (isset($field_setting['hidden'][$mode])) {
                $hidden_fields[$field_name] = $field_setting['hidden'][$mode];
            } else {
                $hidden_fields[$field_name] = false;
            }
        }

        return $hidden_fields;
    }

    public static function getReadOnlyFields($mode = 0, $id = 0): array
    {
        $readonly_fields = [];
        foreach (self::getFieldSettings($id) as $field_name => $field_setting) {
            if (!isset($field_setting['readonly'])) {
                $readonly_fields[$field_name] = false;
            } else if (!is_array($field_setting['readonly'])) {
                $readonly_fields[$field_name] = $field_setting['readonly'];
            } else if (isset($field_setting['readonly'][$mode])) {
                $readonly_fields[$field_name] = $field_setting['readonly'][$mode];
            } else {
                $readonly_fields[$field_name] = false;
            }
        }

        return $readonly_fields;
    }

    public static function getRequiredFields($mode = 0, $id = 0): array
    {
        $required_fields = [];
        foreach (self::getFieldSettings($id) as $field_name => $field_setting) {
            if (!isset($field_setting['required'])) {
                $required_fields[$field_name] = false;
            } else if (!is_array($field_setting['required'])) {
                $required_fields[$field_name] = $field_setting['required'];
            } else if (isset($field_setting['required'][$mode])) {
                $required_fields[$field_name] = $field_setting['required'][$mode];
            } else {
                $required_fields[$field_name] = false;
            }
        }

        return $required_fields;
    }

    /*
     * @return PluginIservicePrinter
     */
    public function getFirstPrinter(): PluginIservicePrinter
    {
        $item_ticket = new Item_Ticket();
        $data        = $item_ticket->find("`tickets_id` = {$this->getID()} and `itemtype` = 'Printer'");
        $printer     = new PluginIservicePrinter();
        foreach ($data as $val) {
            if ($printer->getFromDB($val["items_id"]) && !$printer->isDeleted()) {
                return $printer;
            }
        }

        return new PluginIservicePrinter();
    }

    /*
     * @return PluginIservicePartner
     */
    public function getFirstAssignedPartner(): PluginIservicePartner
    {
        $this->reloadActors();
        $partner = new PluginIservicePartner();
        foreach ($this->getSuppliers(CommonITILActor::ASSIGN) as $partner_data) {
            if ($partner->getFromDB($partner_data['suppliers_id']) && !$partner->isDeleted()) {
                return $partner;
            }
        }

        return new PluginIservicePartner();
    }

    /*
     * @return PluginIserviceUser
     */
    public function getFirstAssignedUser(): PluginIserviceUser
    {
        $this->reloadActors();
        $user = new PluginIserviceUser();
        foreach ($this->getUsers(CommonITILActor::ASSIGN) as $user_data) {
            if ($user->getFromDB($user_data['users_id']) && !$user->isDeleted()) {
                return $user;
            }
        }

        return new PluginIserviceUser();
    }

    public static function getAllStatusArray($withmetaforsearch = false): array
    {
        // To be overridden by class.
        $tab = [
            self::INCOMING => _x('status', 'New'),
            self::ASSIGNED => _x('status', 'Processing (assigned)'),
            self::PLANNED => _x('status', 'Processing (planned)'),
            self::EVALUATION => __('Order', 'iservice'),
            self::WAITING => __('Pending'),
            self::SOLVED => _x('status', 'Solved'),
            self::CLOSED => _x('status', 'Closed')
        ];

        if ($withmetaforsearch) {
            $tab['notold']    = _x('status', 'Not solved');
            $tab['notclosed'] = _x('status', 'Not closed');
            $tab['process']   = __('Processing');
            $tab['old']       = _x('status', 'Solved + Closed');
            $tab['all']       = __('All');
        }

        return $tab;
    }

    public static function getPreviousIdForItemWithInput($item, $open = null): int
    {
        return self::getPreviousIdForPrinterOrSupplier(
            IserviceToolBox::getValueFromInput('_suppliers_id_assign', $item->input),
            IserviceToolBox::getItemsIdFromInput($item->input, 'Printer'),
            $item->input['effective_date'] ?? $item->customfields->fields['effective_date'] ?? '',
            $item->getID(),
            $open
        );
    }

    public static function getPreviousIdForPrinterOrSupplier($supplier_id = 0, $printer_id = 0, $effective_date = '', $id = 0, $open = null): string
    {
        return self::getLastIdForPrinterOrSupplier($supplier_id, $printer_id, $open, IserviceToolBox::isDateEmpty($effective_date) ? '' : "and (t.effective_date < '$effective_date' or (t.effective_date = '$effective_date' and t.id < $id))");
    }

    public static function getLastForPrinterOrSupplierFromInput($input, $open = null, $additional_condition = '', $additional_join = ''): self
    {
        $result = new self();
        $result->getFromDB(self::getLastIdForPrinterOrSupplierFromInput($input, $open, $additional_condition, $additional_join));
        return $result;
    }

    public static function getLastIdForItemWithInput($item, $open = null, $additional_condition = '', $additional_join = ''): string
    {
        return self::getLastIdForPrinterOrSupplierFromInput($item->input, $open, $additional_condition, $additional_join);
    }

    public static function getLastIdForPrinterOrSupplierFromInput($input, $open = null, $additional_condition = '', $additional_join = ''): string
    {
        return self::getLastIdForPrinterOrSupplier(
            IserviceToolBox::getValueFromInput('_suppliers_id_assign', $input),
            IserviceToolBox::getItemsIdFromInput($input, 'Printer'),
            $open, $additional_condition, $additional_join
        );
    }

    public static function getLastForPrinterOrSupplier($supplier_id = 0, $printer_id = 0, $open = null, $additional_condition = '', $additional_join = ''): self
    {
        $result = new self();
        $result->getFromDB(self::getLastIdForPrinterOrSupplier($supplier_id, $printer_id, $open, $additional_condition, $additional_join));
        return $result;
    }

    public static function getLastIdForPrinterOrSupplier($supplier_id = 0, $printer_id = 0, $open = null, $additional_condition = '', $additional_join = ''): int
    {
        return self::getIdForPrinterOrSupplier($supplier_id, $printer_id, $open, 'desc', $additional_condition, $additional_join);
    }

    public static function getFirstIdForItemWithInput($item, $open = null, $additional_condition = '', $additional_join = ''): int
    {
        return self::getFirstIdForPrinterOrSupplier(
            IserviceToolBox::getValueFromInput('_suppliers_id_assign', $item->input),
            IserviceToolBox::getItemsIdFromInput($item->input, 'Printer'),
            $open, $additional_condition, $additional_join
        );
    }

    public static function getFirstIdForPrinterOrSupplier($supplier_id = 0, $printer_id = 0, $open = null, $additional_condition = '', $additional_join = ''): int
    {
        return self::getIdForPrinterOrSupplier($supplier_id, $printer_id, $open, 'asc', $additional_condition, $additional_join);
    }

    public static function getIdForPrinterOrSupplier($supplier_id = 0, $printer_id = 0, $open = null, $order = 'asc', $additional_condition = '', $additional_join = ''): int
    {
        if (empty($printer_id)) {
            if (empty($supplier_id)) {
                return 0;
            }

            $object             = new Supplier_Ticket();
            $join_and_condition = self::getConditionForSupplier($supplier_id, $open, $order, $additional_condition, $additional_join);
        } else {
            $object             = new Item_Ticket();
            $join_and_condition = self::getConditionForPrinter($printer_id, $open, $order, $additional_condition, $additional_join);
        }

        if (PluginIserviceDB::populateByQuery($object, $join_and_condition, true)) {
            return $object->fields['tickets_id'];
        }

        return 0;
    }

    public static function getNewerClosedTikcetIds($ticket_id, $effective_date, $supplier_id, $printer_id): array
    {
        return self::getAllIdsForPrinterOrSupplier($supplier_id, $printer_id, 'desc', false, "and (t.effective_date > '$effective_date' or (t.effective_date = '$effective_date' and t.id > $ticket_id))");
    }

    public static function getAllIdsForPrinterOrSupplier($supplier_id = 0, $printer_id = 0, $order = 'asc', $open = null, $additional_condition = '', $additional_join = ''): array
    {
        if (empty($printer_id)) {
            if (empty($supplier_id)) {
                return [];
            }

            $table              = 'glpi_suppliers_tickets';
            $join_and_condition = self::getConditionForSupplier($supplier_id, $open, $order, $additional_condition, $additional_join);
        } else {
            $table              = 'glpi_items_tickets';
            $join_and_condition = self::getConditionForPrinter($printer_id, $open, $order, $additional_condition, $additional_join);
        }

        return IserviceToolBox::getQueryResult("select `$table`.tickets_id from `$table` $join_and_condition", "tickets_id");
    }

    protected static function getConditionForPrinter($printer_id = 0, $open = null, $order = 'asc', $additional_condition = '', $additional_join = ''): string
    {
        if (is_array($printer_id) && !empty($printer_id['Printer']) && is_array($printer_id['Printer'])) {
            $printer_id = $printer_id['Printer'][0];
        }

        $join           = "JOIN glpi_plugin_iservice_tickets t ON t.id = `glpi_items_tickets`.tickets_id AND t.is_deleted = 0 ";
        $join          .= $additional_join;
        $open_condition = $open === null ? "" : "t.status " . ($open ? "!=" : "=") . Ticket::CLOSED . " AND";
        return "$join WHERE $open_condition `glpi_items_tickets`.items_id = $printer_id and `glpi_items_tickets`.itemtype = 'Printer' $additional_condition ORDER BY t.effective_date $order, t.id $order";
    }

    protected static function getConditionForSupplier($supplier_id = 0, $open = null, $order = 'asc', $additional_condition = '', $additional_join = ''): string
    {
        $join           = "JOIN glpi_plugin_iservice_tickets t ON t.id = `glpi_suppliers_tickets`.tickets_id AND t.is_deleted = 0
                 LEFT JOIN
                    (SELECT COUNT(git.tickets_id) item_count, git.tickets_id 
                     FROM glpi_items_tickets git
                     WHERE git.itemtype = 'Printer'
                     GROUP BY git.tickets_id 
                    ) ic on ic.tickets_id = t.id
                 $additional_join
                 ";
        $open_condition = $open === null ? "" : "t.status " . ($open ? "!=" : "=") . Ticket::CLOSED . " AND";
        return "$join WHERE $open_condition ic.item_count IS NULL
                AND `glpi_suppliers_tickets`.suppliers_id = $supplier_id
                AND `glpi_suppliers_tickets`.type = " . CommonITILActor::ASSIGN . " $additional_condition
                ORDER BY t.effective_date_field $order, t.id $order";
    }

    public function displayResult($result_type, $result): string
    {
        $result_texts = [
            'global_readcounter' => [
                -1 => __('Error saving ticket(s)', 'iservice') . ', ' . sprintf(__("%d ticket(s) saved.", 'iservice'), $result),
                0 => __('Error saving ticket(s)', 'iservice') . '.',
                1 => sprintf(__("%d ticket(s) saved.", 'iservice'), $result),
            ]
        ];

        if (empty($result_texts[$result_type])) {
            $message = "Internal error (invalid result type: $result_type)";
        } else {
            $message = $result_texts[$result_type][$result < 0 ? -1 : ($result > 0 ? 1 : ($result === false ? 0 : 1))];
        }

        echo "<span class='b'>$message</span>";
    }

    public function showForm($ID, $arg_options = []): bool
    {
        global $DB, $CFG_GLPI, $CFG_PLUGIN_ISERVICE;

        $options = array_merge(filter_input_array(INPUT_GET), $arg_options);
        if ($ID) {
            $options['id'] = $ID;
        }

        if (isset($options['id']) && !$this->isNewID($options['id'])) {
            $id = $options['id'];
            if (!$this->getFromDB($id)) {
                Html::displayNotFoundError();
            } elseif ($this->fields['is_deleted']) {
                die("Deleted");
            }
        } else {
            $id                         = $options['id'] = '';
            $this->customfields         = new PluginFieldsTicketticketcustomfield();
            $this->customfields->fields = array_column($DB->listFields($this->customfields->getTable()), 'Default', 'Field');
        }

        if (!isset($options['mode'])) {
            $options['mode'] = self::MODE_NONE;
        }

        if (!isset($options['form'])) {
            $options['form'] = true;
        }

        Session::checkRight('plugin_iservice_ticket_' . $options['mode'], READ);

        $prepared_data = $this->prepareForShow($options);

        // Default check
        if ($id > 0) {
            $this->check($options['id'], READ);
        } else {
            // Create item
            $this->check(-1, CREATE, $prepared_data['values_for_right_check']);
        }

        $this->fields = $prepared_data['fields'];

        $options['template'] = $prepared_data['template'];
        $closed              = $prepared_data['closed'];

        $processed_data = $this->processFieldsByInput();

        // These initializations are needed only to avoid not defined warnings in the development IDE.
        $movement_id   = '';
        $movement2_id  = '';
        $idemmailfield = '';

        /*
        * @var $printer PluginIservicePrinter
        */

        $printer     = null;
        $printer_id  = '';
        $supplier_id = '';
        foreach ($processed_data['variables'] as $variable_name => $variable_value) {
            $$variable_name = $variable_value;
        }

        // Further preparations.
        if (in_array($options['mode'], [self::MODE_CARTRIDGEMANAGEMENT, self::MODE_CLOSE]) && $id > 0 && ($this->fields['items_id']['Printer'][0] ?? 0) > 0) {
            $prepared_data['field_readonly']['items_id[Printer][0]'] = true;
        }

        $accessible_printer_ids = PluginIservicePrinter::getAccessibleIds();
        if ($accessible_printer_ids !== null && $printer_id > 0 && !in_array($printer_id, $accessible_printer_ids)) {
            Html::displayRightError();
        }

        if (empty($id) && ($movement = PluginIserviceMovement::getOpenFor('Printer', $printer_id)) !== false && empty($movement_id) && empty($movement2_id)) {
            Html::displayErrorAndDie("<a href='movement.form.php?id=$movement' target='_blank'>O mutare nefinalizată există pentru acest aparat, vă rugăm finalizați mutarea $movement întâi!</a>");
        } else {
            $movement = new PluginIserviceMovement();
            $movement->getFromDB($this->customfields->fields['movement_id'] ?: $this->customfields->fields['movement2_id_field'] ?: -1);
        }

        // Begin form.
        if ($options['form']) {
            echo "<form class='iservice-form two-column ticket' method='post' name='form_ticket' enctype='multipart/form-data' action='" . filter_input(INPUT_SERVER, 'REQUEST_URI') . "'>";
        }

        if (isset($options['projecttasks_id'])) {
            echo "<input type='hidden' name='_projecttasks_id' value='" . $options['projecttasks_id'] . "'>";
        }

        echo "<div class='spaced' id='tabsbody'>";
        echo "<table class='tab_cadre_fixe wide80' id='mainformtable'>";

        // Optional line.
        echo "<tr class='headerRow'>";
        echo "<th colspan='2'>";

        if ($id) {
            echo sprintf(__('%1$s - %2$s'), $this->getTypeName(1), sprintf(__('%1$s: %2$s'), __('ID'), $id));
            if (IserviceToolBox::inProfileArray(['super-admin', 'admin'])) {
                $history_values  = [
                    'full' => 'Full',
                    'cartridge' => __('Cartridge'),
                ];
                $history_options = [
                    'value' => empty($this->fields['show_history']) ? '' : $this->fields['show_history'],
                    'display' => false,
                    'display_emptychoice' => true,
                    'on_change' => "ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/getHistoryTable.php?itemtype=Ticket&item_id=$id&filter=\" + $(\"[name=show_history]\").val(), \"\", function(message) { if (message === \"\") { $(\"#history-span\").hide(); } else { $(\"#history-span\").html(message); $(\"#history-span\").show(); }});",
                ];
                echo "<span class='floatright'> ", __('Historical'), " ";
                echo "<div class='dropdown_wrapper floatright'>" , Dropdown::showFromArray('show_history', $history_values, $history_options), "</div>";
                echo "</span>";
                echo "<span class='tab_cadrehov' id='history-span' style='display:none'></span>";
                echo "<script>setTimeout(function() { $('[name=show_history]').change();}, 2000);</script>";
            }
        } else {
            if ($options['template'] > 1) {
                echo $prepared_data['template_object']->fields['name'];
            } else {
                __('New ticket');
            }
        }

        echo "</th></tr>";

        $form = new PluginIserviceHtml();

        // Automatic fields.
        if (!empty($this->fields['_auto_import'])) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_auto_import', $this->fields['_auto_import']);
        }

        $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_mode', $options['mode']);

        if ($prepared_data['field_hidden']['type']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'type', $this->fields['type']);
        } else {
            // Type must be hidden.
        }

        if ($prepared_data['field_hidden']['users_id_recipient']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'users_id_recipient', $this->fields['users_id_recipient']);
        } else {
            // Users_id_recipient must be hidden.
        }

        if ($prepared_data['field_hidden']['_users_id_requester']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_users_id_requester', $this->fields['_users_id_requester']);
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_users_id_requester_original', $prepared_data['initial_values']['_users_id_requester']);
        } else {
            // _users_id_requester must be hidden
        }

        if ($prepared_data['field_hidden']['_contact_partner']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_contact_partner', empty($prepared_data['default_values']['_contact_partner']) ? '' : $prepared_data['default_values']['_contact_partner']);
        } else {
            // _contact_partner must be hidden
        }

        if ($prepared_data['field_hidden']['_close_on_success']) {
            $close_on_success = IserviceToolBox::getInputVariable('_close_on_success', empty($prepared_data['default_values']['_close_on_success']) ? '' : $prepared_data['default_values']['_close_on_success']);
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_close_on_success', $close_on_success);
        } else {
            // _close_on_success must be hidden
        }

        if ($prepared_data['field_hidden']['_movement_id']) {
            $ticket_customfields = new PluginFieldsTicketticketcustomfield();
            if (PluginIserviceDB::populateByQuery($ticket_customfields, "WHERE movement_id_field = " . IserviceToolBox::getInputVariable('movement_id', -2), true)) {
                Html::displayErrorAndDie(sprintf(__("Ticket already exists for movement %d", "iservice"), IserviceToolBox::getInputVariable('movement_id')));
            }

            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_movement_id', $movement_id);
        } else {
            // _movement_id must be hidden
        }

        if ($prepared_data['field_hidden']['_movement2_id']) {
            $ticket_customfields = new PluginFieldsTicketticketcustomfield();
            if (PluginIserviceDB::populateByQuery($ticket_customfields, "WHERE movement2_id_field = " . IserviceToolBox::getInputVariable('movement2_id', -2), true)) {
                Html::displayErrorAndDie(sprintf(__("Ticket already exists for movement %d", "iservice"), IserviceToolBox::getInputVariable('movement2_id')));
            }

            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_movement2_id', $movement2_id);
        } else {
            // _movement2_id must be hidden
        }

        if ($prepared_data['field_hidden']['_idemmailfield']) {
            $ticket_customfields = new PluginFieldsTicketticketcustomfield();
            if (PluginIserviceDB::populateByQuery($ticket_customfields, "WHERE em_mail_id_field = " . IserviceToolBox::getInputVariable('idemmailfield', -2), true)) {
                Html::displayErrorAndDie(sprintf(__("Ticket already exists for [EM] email %d", "iservice"), IserviceToolBox::getInputVariable('idemmailfield')));
            }

            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_idemmailfield', $idemmailfield);
        } else {
            // em_mail_id_field must be hidden
        }

        // Have to get the number of consumables here, to be able to use it:
        ob_start();
        $order_status             = $this->getOrderStatus();
        $ticket_consumables       = PluginIserviceConsumable_Ticket::showForTicket($this, $prepared_data['field_required'], false, (empty($id) || $closed || ($id > 0 && $this->customfields->fields['delivered_field']) || $order_status != 0));
        $ticket_consumables_table = ob_get_contents();
        ob_end_clean();
        ob_start();
        $ticket_cartridges       = PluginIserviceCartridge_Ticket::showForTicket($this, $prepared_data['field_required'], false, (empty($id) || $closed || ($id > 0 && $this->customfields->fields['exported_field']) || $order_status != 0));
        $ticket_cartridges_table = ob_get_clean();
        if ($ticket_consumables || count($ticket_cartridges)) {
            $prepared_data['field_readonly']['_suppliers_id_assign'] = true;
            $prepared_data['field_readonly']['items_id[Printer][0]'] = true;
        }

        // Supplier - the id is received earlier!
        if (in_array($options['mode'], [self::MODE_CARTRIDGEMANAGEMENT, self::MODE_CLOSE]) && $id > 0 && $this->fields['_suppliers_id_assign'] > 0) {
            $prepared_data['field_readonly']['_suppliers_id_assign'] = true;
        }

        if ($prepared_data['field_hidden']['_suppliers_id_assign']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_suppliers_id_assign', $supplier_id);
        } else {
            $supplier_dropdown_options['type']                 = 'Supplier';
            $supplier_dropdown_options['class'][]              = 'full';
            $supplier_dropdown_options['options']['on_change'] = '$("[name=\'items_id[Printer][0]\']").val(-1);$(this).closest("form").submit();';
            if ($accessible_printer_ids !== null) {
                $query = "
                        SELECT s.id
                        FROM glpi_suppliers s
                        LEFT JOIN glpi_infocoms i ON i.suppliers_id = s.id AND itemtype = 'Printer'
                        LEFT JOIN glpi_plugin_iservice_printers p ON p.id = i.items_id
                        WHERE p.id IN (" . join(',', $accessible_printer_ids) . ")
                        GROUP BY s.id
                        ";
                if (($result = $DB->query($query)) === false) {
                    echo $DB->error();
                    die();
                }

                while (($row = $DB->fetchAssoc($result)) !== null) {
                    $supplier_ids[] = $row['id'];
                }

                if ($supplier_id > 0 && !in_array($supplier_id, $supplier_ids)) {
                    Html::displayRightError();
                }

                $supplier_dropdown_options['options']['condition'] .= ["id in (" . join(',', $supplier_ids) . ")"];
            }

            $form->displayFieldTableRow(__('Supplier'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, '_suppliers_id_assign', $supplier_id, $prepared_data['field_readonly']['_suppliers_id_assign'], $supplier_dropdown_options));
        }

        $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_suppliers_id_assign_original', $prepared_data['initial_values']['_suppliers_id_assign']);
        $supplier_customfields = new PluginFieldsSuppliersuppliercustomfield();
        PluginIserviceDB::populateByItemsId($supplier_customfields, $supplier_id);

        $color_printer   = $printer->isColor();
        $plotter_printer = $printer->isPlotter();

        if ($prepared_data['field_hidden']['items_id[Printer][0]']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'items_id[Printer][0]', $printer_id);
        } else {
            $printer_dropdown_options['type']                 = 'PluginIservicePrinter';
            $printer_dropdown_options['class'][]              = 'full';
            $printer_dropdown_options['options']['on_change'] = "$(this).closest('form').submit();";
            if ($supplier_id > 0) {
                $printer_dropdown_options['options']['condition'] = ["supplier_id = $supplier_id"];
            }

            if ($accessible_printer_ids !== null) {
                $printer_dropdown_options['options']['condition'][] = "id IN (" . join(',', $accessible_printer_ids) . ')';
            }

            $printer_label = __('Printer', 'iservice') . ($printer->isNewItem() ? '' : ($color_printer ? ' color' : ' alb-negru'));
            $form->displayFieldTableRow($printer_label, $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'items_id[Printer][0]', $printer_id, $prepared_data['field_readonly']['items_id[Printer][0]'], $printer_dropdown_options));
        }

        // Usage address
        if ($prepared_data['field_hidden']['_usageaddressfield']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_usageaddressfield', $printer->customfields->fields['usage_address_field']);
        } else {
            $form->displayFieldTableRow('Adresa de exploatare', $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, '_usageaddressfield', $printer->customfields->fields['usage_address_field'] ?? ''));
        }

        // Location - The location of the printer at the first save should be saved,
        // because the printer may move ad we do not want to save that movement.
        // Especially when the ticket is reopened then reclosed after the printer has been moved.
        $location = new Location();
        if (!empty($this->fields['locations_id'])) {
            $location->getFromDB($this->fields['locations_id']);
        } else if (empty($id) && $printer_id > 0) {
            $location->getFromDB($printer->fields['locations_id']);
        } else {
            $location = false;
        }

        if (!$prepared_data['field_hidden']['locations_id'] && $location) {
            $form->displayFieldTableRow(__('Location'), $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, '', empty($location->fields['completename']) ? '' : $location->fields['completename']));
        }

        $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'locations_id', $location ? $location->getID() : '');

        // Email
        $default_email = empty($printer->customfields->fields['email']) ? (empty($printer->fields['memory_size']) ? '' : $printer->fields['memory_size']) : $printer->customfields->fields['email'];
        $email         = IserviceToolBox::getInputVariable('_email', '');
        if (empty($email)) {
            $email = $default_email;
        }

        $send_email = IserviceToolBox::getInputVariable('_send_email', empty($this->fields['_send_email']) ? '' : $this->fields['_send_email']);
        if ($prepared_data['field_hidden']['_send_email']) {
            $email_field_options['postfix'] = [];
        } else {
            $email_field_options['postfix'] = $form->generateField(PluginIserviceHtml::FIELDTYPE_CHECKBOX, '_send_email', $send_email, $prepared_data['field_readonly']['_send_email'], ['style' => 'height:1.2em;margin-left:1%']);
        }

        if (!$prepared_data['field_hidden']['_email']) {
            $email_field = $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_email', $email, $prepared_data['field_readonly']['_email'], $email_field_options);
            $info_icon   = " <img src='$CFG_GLPI[root_doc]/pics/info-small.png' class='pointer' title='Puteţi introduce mai multe adrese de e-mail, separate prin virgulă'>";
            $form->displayFieldTableRow(__('Send confirmation email', 'iservice') . $info_icon, $email_field);
        }

        // Watcher
        if ($prepared_data['field_hidden']['_users_id_observer']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_users_id_observer', $this->fields['_users_id_observer']);
        } else {
            $observer_dropdown_options = [
                'type' => 'User',
                'class' => 'full',
                'options' => ['right' => 'own_ticket']
            ];
            $form->displayFieldTableRow(__('Watcher'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, '_users_id_observer', $this->fields['_users_id_observer'], $prepared_data['field_readonly']['_users_id_observer'], $observer_dropdown_options));
        }

        $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_users_id_observer_original', $prepared_data['initial_values']['_users_id_observer']);

        // Assign
        if ($prepared_data['field_hidden']['_users_id_assign']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_users_id_assign', $this->fields['_users_id_assign']);
        } else {
            if (empty($this->fields['_users_id_assign']) || $this->fields['_users_id_assign'] < 1) {
                $this->fields['_users_id_assign'] = empty($printer->fields['users_id_tech']) ? '' : $printer->fields['users_id_tech'];
            }

            $assign_dropdown_options = [
                'type' => 'User',
                'class' => 'full',
                'options' => ['right' => 'own_ticket']
            ];
            $form->displayFieldTableRow(__('Assigned to'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, '_users_id_assign', $this->fields['_users_id_assign'], $prepared_data['field_readonly']['_users_id_assign'], $assign_dropdown_options));
        }

        $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_users_id_assign_original', $prepared_data['initial_values']['_users_id_assign']);

        // Sum of unpaid invoices
        if (!$prepared_data['field_hidden']['_sum_of_unpaid_invoices'] && $supplier_id > 0) {
            $sum_display = IserviceToolBox::getSumOfUnpaidInvoicesLink($supplier_id, $supplier_customfields->fields['hmarfa_code_field']);
            $form->displayFieldTableRow(__('Sum of unpaid invoices', 'iservice'), $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, '_sum_of_unpaid_invoices', $sum_display));
        }

        // Last invoice and counters
        if (!$prepared_data['field_hidden']['_last_invoice_and_counters']) {
            $info_table_header = new PluginIserviceHtml_table_row();
            $info_table_header->populateCells(
                [
                    __('Last invoice date', 'iservice'),
                    __('Invoice expiry date', 'iservice'),
                    ($plotter_printer ? __('Printed surface', 'iservice') : __('Color counter', 'iservice')) . ' ' . __('last invoice', 'iservice'),
                    ($plotter_printer ? __('Printed surface', 'iservice') : __('Color counter', 'iservice')) . ' ' . __('last closed ticket', 'iservice'),
                    __('Black counter', 'iservice') . ' ' . __('last invoice', 'iservice'),
                    __('Black counter', 'iservice') . ' ' . __('last closed ticket', 'iservice'),
                ], '', '', 'th'
            );
            $info_table = new PluginIserviceHtml_table(
                'tab_cadrehov wide80', $info_table_header, new PluginIserviceHtml_table_row(
                    '', [
                        new PluginIserviceHtml_table_cell($printer->customfields->fields['invoice_date_field'] ?? '', 'nowrap'),
                        new PluginIserviceHtml_table_cell($printer->customfields->fields['invoice_expiry_date_field'] ?? '', 'nowrap'),
                        $printer->customfields->fields['invoiced_total_color_field'] ?? '',
                        $printer->lastClosedTicket()->fields['total2_color'] ?? '',
                        $printer->customfields->fields['invoiced_total_black_field'] ?? '',
                        $printer->lastClosedTicket()->fields['total2_black'] ?? '',
                    ]
                ), 'display: inline-block;text-align: center;'
            );
            $form->displayFieldTableRow(__('Last invoice and counter information', 'iservice'), $info_table);
        }

        $last_ticket        = self::getLastForPrinterOrSupplier($supplier_id, $printer_id);
        $last_closed_ticket = self::getLastForPrinterOrSupplier(0, $printer_id, false);
        $last_opened_ticket = self::getLastForPrinterOrSupplier(0, $printer_id, true, "and t.effective_date < '{$this->customfields->fields['effective_date']}'");

        // Counter required minumums.
        // We check if there are newer tickets.
        // We check only for closed tickets, as the counters are calculated on ticket close.
        $total2_black_required_minimum = $last_closed_ticket->fields['total2_black'] ?? 0;
        $total2_color_required_minimum = $last_closed_ticket->fields['total2_color'] ?? 0;

        // If there are newer closed tickets, we do not allow counter change, as counters on the cartridges will be messed up.
        if ($id > 0 && ($last_closed_ticket->customfields->fields['effective_date'] ?? '') > $this->customfields->fields['effective_date']) {
            $prepared_data['field_readonly']['total2_black'] = true;
            $prepared_data['field_readonly']['total2_color'] = true;
        }

        // If the ticket is closed or there are newer tickets, the required minimum is the already saved counter or the last intervention counter.
        if ($id > 0 && (($last_ticket->customfields->fields['effective_date'] ?? '') > $this->customfields->fields['effective_date'] || $closed)) {
            $total2_black_required_minimum = min($total2_black_required_minimum, $this->fields['total2_black']);
            $total2_color_required_minimum = min($total2_color_required_minimum, $this->fields['total2_color']);
        }

        // effective_date required minimum.
        $effective_date_requiered_minimum = $closed ? '2000-01-01' : date('Y-m-d H:i', strtotime($last_closed_ticket->customfields->fields['effective_date'] ?? '2000-01-01'));

        // Counter CSV data.
        $csv_data = PluginIserviceEmaintenance::getDataFromCsvsForSpacelessSerial($printer->getSpacelessSerial());
        if (!empty($csv_data) && !$prepared_data['field_readonly']['total2_black']) {
            $style   = '';
            $onclick = '';
            $title   = "Click pentru valoarea din CSV\nData contor: {$csv_data['effective_date']}\nContor black: ";

            $button_template = " <input class='submit' onclick='' title=' %2\$d' type='button' value='din CSV'/>";

            if (!empty($csv_data['total2_black']['error'])) {
                $style  = "style='color: red;'";
                $title .= $csv_data['total2_black']['error'];
            } else {
                $title .= $csv_data['total2_black'];
                if ($csv_data['total2_black'] < $total2_black_required_minimum) {
                    $style  = "style='color: red;'";
                    $title .= " < $total2_black_required_minimum (minim valid)!";
                } else {
                    $onclick .= sprintf("$(\"[name=total2_black]\").val(%d);", $csv_data['total2_black']);
                }
            }

            if ($color_printer || $plotter_printer) {
                $title .= $plotter_printer ? "\nSuprafață printată:" : "\nContor color: ";
                if (!empty($csv_data['total2_color']['error'])) {
                    $style  = "style='color: red;'";
                    $title .= $csv_data['total2_color']['error'];
                } else {
                    $title .= $csv_data['total2_color'];
                    if ($csv_data['total2_color'] < $total2_color_required_minimum) {
                        $style  = "style='color: red;'";
                        $title .= " < $total2_color_required_minimum (minim valid)!";
                    } else {
                        $onclick .= sprintf("$(\"[name=total2_color]\").val(%d);", $csv_data['total2_color']);
                    }
                }
            }

            if (!isset($csv_data['effective_date']['error']) && $csv_data['effective_date'] >= $effective_date_requiered_minimum) {
                $onclick .= sprintf("setGlpiDateField($(\"[name=_effective_date]\").closest(\".dropdown_wrapper\"), \"%s\");", $csv_data['effective_date']);
            }

            $counter_black_suffix = " <input class='submit' onclick='$onclick' title='$title' type='button' $style value='din CSV' />";
        } else {
            $counter_black_suffix = '';
        }

        // Estimation.
        if (!$closed && $printer_id > 0 && !$printer->isRouter() && (empty($id) || ($last_ticket->customfields->fields['effective_date'] ?? '') <= $this->customfields->fields['effective_date']) && $last_closed_ticket->getID() > 0 && !$printer->customfields->fields['no_invoice_field']) {
            $last_effective_date     = new DateTime($last_closed_ticket->customfields->fields['effective_date'] ?? '');
            $days_since_last_counter = $last_effective_date->diff(new DateTime(empty($this->customfields->fields['effective_date']) ? null : $this->customfields->fields['effective_date']))->format("%a");
            $estimated_black         = $last_closed_ticket->fields['total2_black'] + $printer->customfields->fields['daily_bk_average_field'] * $days_since_last_counter;
            $estimated_color         = $last_closed_ticket->fields['total2_color'] + $printer->customfields->fields['daily_color_average_field'] * $days_since_last_counter;
            $title                   = "";
            $onclick                 = '';
            if ($estimated_black > 0) {
                $title   .= "black: $estimated_black ({$last_closed_ticket->fields['total2_black']} + {$printer->customfields->fields['daily_bk_average_field']}*$days_since_last_counter)";
                $onclick .= "$(\"[name=total2_black]\").val($estimated_black);";
            }

            if (($color_printer || $plotter_printer) && $estimated_color > 0) {
                $title   .= ", " . ($plotter_printer ? "suprafață hârtie" : "color") . ": $estimated_color ({$last_closed_ticket->fields['total2_color']} + {$printer->customfields->fields['daily_color_average_field']}*$days_since_last_counter)";
                $onclick .= "$(\"[name=total2_color]\").val($estimated_color);";
            }

            // Uncomment this line to see date explanation.
            // $title .= sprintf(" [%s - %s]", date('Y-m-d', strtotime($this->customfields->fields['effective_date'])), date('Y-m-d', strtotime($last_closed_ticket->customfields->fields['effective_date'])));
            $counter_black_suffix .= " <input class='submit' onclick='$onclick' title='$title' type='button' value='Estimare' />";
        } else {
            $estimated_black = '';
            $estimated_color = '';
        }

        // Counter calculation.
        $total2_black = empty($id) ? $total2_black_required_minimum : ($this->fields['total2_black'] > $total2_black_required_minimum || $closed ? $this->fields['total2_black'] : $total2_black_required_minimum);
        $total2_color = empty($id) ? ($total2_color_required_minimum) : ($this->fields['total2_color'] > $total2_color_required_minimum || $closed ? $this->fields['total2_color'] : $total2_color_required_minimum);

        // Black counter data.
        if ($prepared_data['field_hidden']['total2_black']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'total2_black', $total2_black);
        } else {
            $label = $plotter_printer ? __('Consumed ink', 'iservice') : __('Black counter reading', 'iservice');
            if ($plotter_printer) {
                $info_icon = "";
                $label     = __('Consumed ink', 'iservice');
            } else {
                $info_icon = " <img src='$CFG_GLPI[root_doc]/pics/info-small.png' class='pointer' title='contor " . (($color_printer || $plotter_printer) ? '109' : '102') . "'>";
                $label     = __('Black counter reading', 'iservice');
            }

            $form->displayFieldTableRow($label . $info_icon, $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'total2_black', $total2_black, $prepared_data['field_readonly']['total2_black'] || ($this->originalFields['status'] ?? '') == self::CLOSED, ['data-required-minimum' => $total2_black_required_minimum, 'data-estimated' => $estimated_black]) . $counter_black_suffix);
        }

        // Color counter data.
        if ($prepared_data['field_hidden']['total2_color'] || (!$color_printer && !$plotter_printer)) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'total2_color', $total2_color);
        } else {
            if ($plotter_printer) {
                $info_icon = "";
                $label     = __('Printed surface', 'iservice');
            } else {
                $info_icon = " <img src='$CFG_GLPI[root_doc]/pics/info-small.png' class='pointer' title='contor 106'>";
                $label     = __('Color counter reading', 'iservice');
            }

            $form->displayFieldTableRow($label . $info_icon, $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'total2_color', $total2_color, $prepared_data['field_readonly']['total2_color'] || ($this->originalFields['status'] ?? '') == self::CLOSED, ['data-required-minimum' => $total2_color_required_minimum, 'data-estimated' => $estimated_color]));
        }

        // Category.
        if ($prepared_data['field_hidden']['itilcategories_id']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'itilcategories_id', $this->fields['itilcategories_id']);
        } else {
            $category_dropdown_options['type']    = 'ITILCategory';
            $category_dropdown_options['class'][] = 'full';
            $form->displayFieldTableRow(__('Category'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, 'itilcategories_id', $this->fields['itilcategories_id'], $prepared_data['field_readonly']['itilcategories_id'], $category_dropdown_options));
        }

        // Name (title).
        if ($prepared_data['field_hidden']['name']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'name', $this->fields['name']);
        } else {
            $form->displayFieldTableRow(__('Title') . ' (' . __('mandatory', 'iservice') . ')', $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'name', $this->fields['name'], $prepared_data['field_readonly']['name']));
        }

        // Content (Description).
        if ($supplier_id < 1) {
            $prepared_data['field_readonly']['content'] = false;
        }

        if ($prepared_data['field_hidden']['content']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'content', $this->fields['content']);
        } else {
            $form->displayFieldTableRow(__('Ticket description', 'iservice') . ' (' . __('optional', 'iservice') . ')', $form->generateField($options['form'] ? PluginIserviceHtml::FIELDTYPE_RICHMEMO : PluginIserviceHtml::FIELDTYPE_MEMO, 'content', $this->fields['content'], $prepared_data['field_readonly']['content']));
        }

        // Followup content (Followup description).
        $followup             = new PluginIserviceTicketFollowup();
        $get_followup_content = IserviceToolBox::getInputVariable('followup_content');
        if (!empty($get_followup_content) || isset($prepared_data['forced_values']['_followup[content]'])) {
            $followup_content = empty($get_followup_content) ? $prepared_data['forced_values']['_followup[content]'] : $get_followup_content;
        } else if ($id > 0 && PluginIserviceDB::populateByQuery($followup, "WHERE id = (SELECT MAX(id) FROM " . ITILFollowup::getTable() . " WHERE items_id = $id AND itemtype = 'Ticket')")) {
            $followup_content = $followup->fields['content'];
        } else {
            $followup_content = $this->fields['_followup[content]'] ?? '';
        }

        if (!$prepared_data['field_hidden']['_followup[content]']) {
            $form->displayFieldTableRow(
                __('Followup description', 'iservice'), $form->generateField(
                    $options['form'] ? PluginIserviceHtml::FIELDTYPE_RICHMEMO : PluginIserviceHtml::FIELDTYPE_MEMO, '_followup[content]', $followup_content, $prepared_data['field_readonly']['_followup[content]'], [
                        'class' => 'change-cartridge-description'
                    ]
                )
            );
        }

        // Private followup.
        if (!$prepared_data['field_hidden']['_followup[is_private]']) {
            $form->displayFieldTableRow(__('Private followup', 'iservice'), $form->generateField(PluginIserviceHtml::FIELDTYPE_CHECKBOX, '_followup[is_private]', empty($followup->fields['is_private']) ? '' : $followup->fields['is_private'], $prepared_data['field_readonly']['_followup[is_private]']));
        }

        // All followups.
        $followups = $followup->showShortForTicket($id);
        if (!$prepared_data['field_hidden']['_all_followup']) {
            $all_followups_options = [
                'field_class' => 'ticket-followups',
                'postfix' => '<script></script>',
            ];
            $form->displayFieldTableRow(_n('Followup', 'Followups', 2), "<div style='width:80%'>$followups</div>", $all_followups_options);
        }

        $available_cartridges = PluginIserviceCartridgeItem::getChangeablesForTicket($this);
        if (!$prepared_data['field_hidden']['_available_cartridges']) {
            if (count($available_cartridges) > 0) {
                $available_cartridges_table  = "<table class='wide80'>";
                $available_cartridges_table .= "<thead><tr><th>" . _n('Cartridge', 'Cartridges', 1) . "</th><th>" . __('Amount', 'iservice') . "</th></tr></thead><tbody>";
                foreach ($available_cartridges as $cartridge) {
                    $cartridge_name = $cartridge["name"];
                    if (!empty($cartridge['location_name'])) {
                        $cartridge_name .= " din locația $cartridge[location_name]";
                    }

                    $available_cartridges_table .= sprintf("<tr><td>%s</td><td>%s</td></tr>", $cartridge_name, $cartridge['cpt']);
                }

                $available_cartridges_table .= "</tbody></table>";
            } else {
                $available_cartridges_table = "";
            }

            $form->displayFieldTableRow('Cartuse disponibile', $available_cartridges_table);
        }

        // Change cartridge.
        $changeables = 0;
        if (empty($printer_id) || $printer_id < 1 || $supplier_id < 1 || !$supplier_customfields->fields['cm_field']) {
            $prepared_data['field_hidden']['_change_cartridge'] = true;
            $changeables                                        = -1;
        } elseif ($this->fields['itilcategories_id'] == self::getItilCategoryId('citire contor')) {
            $prepared_data['field_hidden']['_change_cartridge'] = false;
        }

        if (!$prepared_data['field_hidden']['_change_cartridge']) {
            $cartridge_link             = "view.php?view=cartridges&pmi={$printer->fields['printermodels_id']}&cartridges0[filter_description]=compatibile {$printer->fields['name']}";
            $last_ticket_with_cartridge = self::getLastForPrinterOrSupplier($supplier_id, $printer_id, null, '', 'JOIN glpi_plugin_iservice_cartridges_tickets ct on ct.tickets_id = t.id');
            echo "<tr><td><a target='_blank' href='$cartridge_link'>", __('Change cartridge', 'iservice'), "</a>";
            if ($id > 0 && ($last_ticket_with_cartridge->customfields->fields['effective_date'] ?? '') > $this->customfields->fields['effective_date']) {
                echo "<br><span style='color:grey;font-size:90%'>Atenție. Există un tichet mai nou ({$last_ticket_with_cartridge->getID()}) cu cartușe instalate. Ștergeți întâi cartușele de pe acel tichet.<span>";
            }

            echo "</td><td>";
            $changeables = PluginIserviceCartridge_Ticket::showChangeableForTicket($this, $prepared_data['field_required'], false, (/*$id < 1 || */$closed || (($last_ticket_with_cartridge->customfields->fields['effective_date'] ?? '') > $this->customfields->fields['effective_date'] && $id > 0)));
            echo "</td></tr>";
        }

        // Cartridge change date.
        $prepared_data['field_hidden']['_cartridge_installation'] = $prepared_data['field_hidden']['_change_cartridge'];
        if ($prepared_data['field_hidden']['_cartridge_installation']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_cartridge_installation', $id > 0 ? $this->customfields->fields['cartridge_install'] : (empty($this->fields['_cartridge_installation']) ? '' : $this->fields['_cartridge_installation']));
        } else {
            $cartridge_installation_buttons = [
                'Azi' => date("Y-m-d"),
                'Ieri' => date("Y-m-d", strtotime("-1 day")),
                    // 'Alaltăieri' => date("Y-m-d", strtotime("-2 days")),
            ];
            $form->displayFieldTableRow(
                __('Change date', 'iservice'), $form->generateField(
                    PluginIserviceHtml::FIELDTYPE_DATE,
                    '_cartridge_installation',
                    $id > 0 ? $this->customfields->fields['cartridge_install'] : $this->fields['_cartridge_installation'],
                    $prepared_data['field_readonly']['_cartridge_installation'],
                    ['buttons' => $cartridge_installation_buttons]
                )
            );
        }

        // Status.
        if ($prepared_data['field_hidden']['status']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'status', $this->fields['status']);
        } else if ($prepared_data['field_readonly']['status']) {
            $statuses = self::getAllStatusArray();
            $form->displayFieldTableRow(__('Status'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_status_value', $statuses[$this->fields["status"]], true) . $form->generateField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'status', $this->fields["status"]));
        } else {
            $status_dropdown = self::dropdownStatus(
                [
                    'value' => $this->fields["status"],
                    'display' => false
                ]
            );
            $form->displayFieldTableRow(__('Status'), $status_dropdown);
        }

        // Operator reading.
        if (!$prepared_data['field_hidden']['_operator_reading']) {
            $form->displayFieldTableRow(__('Operator reading', 'iservice'), $form->generateField(PluginIserviceHtml::FIELDTYPE_CHECKBOX, '_operator_reading', 0, $prepared_data['field_readonly']['_operator_reading']));
        }

        // Without papers.
        if ($prepared_data['field_hidden']['_without_papers']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_without_papers', $id > 0 ? $this->customfields->fields['without_paper_field'] : $prepared_data['default_values']['_without_papers']);
        } else {
            $form->displayFieldTableRow(__('Without papers', 'iservice'), $form->generateField(PluginIserviceHtml::FIELDTYPE_CHECKBOX, '_without_papers', $id > 0 ? $this->customfields->fields['without_paper_field'] : $prepared_data['default_values']['_without_papers'], $prepared_data['field_readonly']['_without_papers']));
        }

        // Without moving.
        if ($prepared_data['field_hidden']['_without_moving']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_without_moving', $id > 0 ? $this->customfields->fields['no_travel_field'] : (empty($prepared_data['default_values']['_without_moving']) ? '' : $prepared_data['default_values']['_without_moving']));
        } else {
            $form->displayFieldTableRow('Fără deplasare', $form->generateField(PluginIserviceHtml::FIELDTYPE_CHECKBOX, '_without_moving', $id > 0 ? $this->customfields->fields['no_travel_field'] : (empty($prepared_data['default_values']['_without_moving']) ? '' : $prepared_data['default_values']['_without_moving']), $prepared_data['field_readonly']['_without_moving']));
        }

        // Save work progress.
        if (!$prepared_data['field_hidden']['_save_progress'] && !$closed && $id > 0) {
            $form->displayFieldTableRow('', $form->generateSubmit('wait', 'Salvează tichet în starea "' . __('Working', 'iservice') . '"'), ['row_class' => 'tall3 center']);
        }

        // Cartridges.
        if (!$prepared_data['field_hidden']['_cartridges']) {
            echo "<tr><td>", _n('Cartridge', 'Cartridges', 2), "</td><td>";
            echo $ticket_cartridges_table;
            echo "</td></tr>";
        }

        // Export type.
        if (empty($supplier_id) || $supplier_id < 0) {
            $prepared_data['field_hidden']['_consumables'] = true;
        }

        if ($this->customfields->fields['exported_field']) {
            $prepared_data['field_readonly']['_export_type'] = true;
        }

        $prepared_data['field_hidden']['_export_type'] = $prepared_data['field_hidden']['_consumables'];
        $export_type                                   = $id > 0 ? $this->customfields->fields['plugin_fields_ticketexporttypedropdowns_id'] : (empty($prepared_data['default_values']['_export_type']) ? '' : $prepared_data['default_values']['_export_type']);

        if ($prepared_data['field_hidden']['_export_type']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_export_type', $export_type);
        } else {
            $export_type_options = [
                'method' => 'showFromArray',
                'class' => ['full'],
                'values' => [
                    '' => '---',
                    'factura' => 'Factură',
                    'aviz' => 'Aviz',
                ],
                'options' => ['on_change' => 'if ($(this).val()) {$(".add-consumable-div").show();} else {$(".add-consumable-div").hide();}'],
            ];
            $form->displayFieldTableRow(__('Export type', 'iservice'), $form->generateField(PluginIserviceHtml::FIELDTYPE_DROPDOWN, '_export_type', $export_type, $prepared_data['field_readonly']['_export_type'], $export_type_options));
        }

        // Consumables.
        if (!$prepared_data['field_hidden']['_consumables']) {
            echo "<tr><td>", _n('Consumable', 'Consumables', 2, 'iservice'), "</td><td>";
            echo $ticket_consumables_table;
            echo "</td></tr>";
            if (!$export_type) {
                echo '<script>$(".add-consumable-div").hide();</script>';
            }
        }

        // Delivered.
        if ($prepared_data['field_hidden']['_delivered']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_delivered', $id > 0 ? $this->customfields->fields['delivered_field'] : $prepared_data['default_values']['_delivered']);
        } else {
            $errors = [];
            if (empty($ticket_consumables)) {
                $errors[] = 'Nu sunt consumabile de livirat';
            } if ($this->customfields->fields['exported_field']) {
                $errors[] = 'Starea livrării nu poate fi modificată dacă tichetul este exportat';
            } else {
                if (!empty($this->consumable_data['installed_cartridges'])) {
                    $error = '';
                    foreach ($this->consumable_data['installed_cartridges'] as $cartridge) {
                        $error .= "- cartușul $cartridge[id] instalat cu tichetul $cartridge[ticket_use]\n";
                    }

                    $errors[] = "Următoarele cartușe livrate cu acest tichet sunt deja instalate:\n$error";
                }
            }

            if (!empty($errors)) {
                $deliver_checkbox_title = count($errors) < 2 ? $errors[0] : ("Starea livrări nu poate fi modificată pentru următoarele motive:\n" . implode("\n", $errors));
            } else {
                $prepared_data['field_readonly']['_delivered'] = false;
                $deliver_checkbox_title                        = $this->customfields->fields['delivered_field'] ? 'Revocă livrarea' : 'Finalizează livrarea';
            }

            $deliver_checkbox_options = [
                'title' => $deliver_checkbox_title,
                'onclick' => 'if ($(this).is(":checked")) { $("#btn_export").removeClass("disabled").attr("title", ""); $(this).attr("title", "Revocă livrarea"); } else { $("#btn_export").addClass("disabled").attr("title", $("#btn_export").data("title")); $(this).attr("title", "Finalizează livrarea"); }',
            ];
            $delivered_date           = $this->customfields->fields['delivered_field'] ? ($this->consumable_data['delivery_date'] ?? '?') : $this->customfields->fields['effective_date'];
            $effective_date_span      = " la data de <span id='dataluc-span' style='font-weight: bold;'>$delivered_date</span> ";
            $effective_date_span     .= $this->customfields->fields['delivered_field'] ? "(revocați livrarea și schimbați data efectivă pentru a modifica)" : "(schimbați data efectivă pentru a modifica)";
            $form->displayFieldTableRow(__('Delivered', 'iservice'), $form->generateField(PluginIserviceHtml::FIELDTYPE_CHECKBOX, '_delivered', $id > 0 ? $this->customfields->fields['delivered_field'] : $prepared_data['default_values']['_delivered'], $prepared_data['field_readonly']['_delivered'], $deliver_checkbox_options) . $effective_date_span);
            echo "<script>setTimeout(function() { $('[name=\"_effective_date\"]').change(function() { $('#dataluc-span').text($(this).val()); }); }, 1000 );</script>";
        }

        // Exported.
        if ($prepared_data['field_hidden']['_exported']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_exported', $id > 0 ? $this->customfields->fields['exported_field'] : $prepared_data['default_values']['_exported']);
        } else {
            if ($this->customfields->fields['exported_field']) {
                $months   = [
                    1 => 'ianuarie',
                    2 => 'februarie',
                    3 => 'martie',
                    4 => 'aprilie',
                    5 => 'mai',
                    6 => 'iunie',
                    7 => 'iulie',
                    8 => 'august',
                    9 => 'septembrie',
                    10 => 'octombrie',
                    11 => 'noiembrie',
                    12 => 'decembrie',
                ];
                $supplier = new PluginIservicePartner();
                $supplier->getFromDB($supplier_id);
                $mail_subject      = "Factura ExpertLine - {$supplier->fields['name']} - " . $months[date("n")] . ", " . date("Y");
                $mail_body         = $supplier->getMailBody();
                $send_email_button = " <a class='vsubmit' href='mailto:{$supplier_customfields->fields['email_for_invoices_field']}?subject=$mail_subject&body=$mail_body'>Trimite email către client</a>";
            } else {
                $send_email_button = '';
            }

            $form->displayFieldTableRow(__('Exported in csv and imported in hMarfa', 'iservice'), $form->generateField(PluginIserviceHtml::FIELDTYPE_CHECKBOX, '_exported', $id > 0 ? $this->customfields->fields['exported_field'] : $prepared_data['default_values']['_exported'], $prepared_data['field_readonly']['_exported']) . $send_email_button);
        }

        // Services invoiced.
        if (empty($this->customfields->fields['movement_id'])) {
            $prepared_data['field_hidden']['_services_invoiced'] = true;
        } else {
            $prepared_data['field_readonly']['_services_invoiced'] = $movement->fields['invoice'];
        }

        if (!$prepared_data['field_hidden']['_services_invoiced']) {
            $warning = $prepared_data['field_readonly']['_services_invoiced'] ? '' : " <span style='color:red'>(Nu bifați înainte ca factura de servicii să fie emisă, operația nu poate fi revocată!)</span> Pentru a crea factura, apăsați linkul <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/hmarfaexport.form.php?id={$this->fields['items_id']['Printer'][0]}' target='_blank'>" . __("hMarfa export", "iservice") . "</a>";
            $form->displayFieldTableRow(__('Services invoice', 'iservice'), $form->generateField(PluginIserviceHtml::FIELDTYPE_CHECKBOX, '_services_invoiced', $id > 0 ? $movement->fields['invoice'] : $prepared_data['default_values']['_services_invoiced'], $prepared_data['field_readonly']['_services_invoiced']) . $warning);
        }

        // Data efectiva.
        if (in_array($options['mode'], [self::MODE_CARTRIDGEMANAGEMENT, self::MODE_CLOSE]) && empty($followups)) {
            if (IserviceToolBox::isDateEmpty($this->customfields->fields['effective_date'])) {
                $this->customfields->fields['effective_date'] = date('Y-m-d H:i:s');
            }
        }

        if ($prepared_data['field_hidden']['effective_date']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'effective_date', empty($this->customfields->fields['effective_date']) ? '' : $this->customfields->fields['effective_date']);
        } else {
            $effective_date_buttons = [
                'Azi' => date("Y-m-d H:i:s"),
                'Ieri' => date("Y-m-d H:i:s", strtotime("-1 day")),
                    // 'Alaltăieri' => date("Y-m-d H:i:s", strtotime("-2 days")),
            ];
            $warning = ($this->customfields->fields['effective_date'] && $this->customfields->fields['effective_date'] < ($last_closed_ticket->customfields->fields['effective_date'] ?? $this->customfields->fields['effective_date'])) ? "ATENȚIE! Ultimul tichet închis are data efectivă: " . date('Y-m-d H:i:s', strtotime($last_closed_ticket->customfields->fields['effective_date'])) : '';
            $form->displayFieldTableRow(
                __('Effective date', 'iservice'), $form->generateField(
                    PluginIserviceHtml::FIELDTYPE_DATETIME, 'effective_date', $this->customfields->fields['effective_date'], $prepared_data['field_readonly']['effective_date'], [
                        'data-required-minimum' => $effective_date_requiered_minimum,
                        'data-label' => __('Effective date', 'iservice'),
                        'mindate' => date('Y-m-d', strtotime($last_closed_ticket->customfields->fields['effective_date'] ?? '0000-00-00')),
                        'class' => 'full agressive',
                        'buttons' => $effective_date_buttons,
                        'warning' => $warning
                    ]
                )
            );
        }

        $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_effective_date_default', $this->customfields->fields['effective_date'] ?? '');

        // Email de notificare.
        if ($prepared_data['field_hidden']['_notificationmailfield']) {
            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, '_notificationmailfield', $this->customfields->fields['notificationmailfield']);
        } else {
            $form->displayFieldTableRow(__('Notification e-mail', 'iservice'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_notificationmailfield', $this->customfields->fields['notificationmailfield'], $prepared_data['field_readonly']['_notificationmailfield']));
        }

        // Consumabile instalate.
        if ($printer_id > 0 && !$prepared_data['field_hidden']['_printer_min_percentage']) {
            $pc2_last_cache_data    = Views::getView('printercounters2', false)->getCachedData();
            $printer_min_percentage = PluginIserviceDB::getQueryResult(
                "SELECT consumable_code, min_estimate_percentage, cfci.mercury_code_field  
                                                           FROM glpi_plugin_iservice_cachetable_printercounters2  cp
                                                           INNER JOIN
                                                               (
                                                                   SELECT MIN(min_estimate_percentage) min_percentage, printer_id
                                                                   FROM glpi_plugin_iservice_cachetable_printercounters2
                                                                   WHERE printer_id=$printer_id
                                                               ) cp2 on cp2.printer_id = cp.printer_id
                                                           LEFT JOIN glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cfci on cfci.items_id = cp.ciid and cfci.itemtype = 'CartridgeItem'
                                                           WHERE cp.min_estimate_percentage = cp2.min_percentage 
                                                             AND cp.cm_field = 1
                                                             AND cp.consumable_type = 'cartridge'
                                                             AND cp.printer_types_id in (3, 4)
                                                             AND cp.printer_states_id in (SELECT id FROM glpi_states WHERE name like 'CO%' OR name like 'Gar%' OR name like 'Pro%')"
            );

            if (isset($printer_min_percentage[0]['min_estimate_percentage']) && $printer_min_percentage[0]['min_estimate_percentage'] < 0) {
                $formated_percentage = number_format($printer_min_percentage[0]['min_estimate_percentage'] * 100, 2, '.', '');
                $form->displayFieldTableRow('', $form->generateField(PluginIserviceHtml::FIELDTYPE_LABEL, '_printer_min_percentage', "<span style='color:red' title='La ultima verificare din {$pc2_last_cache_data['data_cached']}:\n{$printer_min_percentage[0]['consumable_code']} {$formated_percentage}%'>Verificați când ați instalat tonere pe aparat!</span>"));
            }
        }

        // Prepare data for buttons.
        $close_confirm_message = '';
        foreach (array_column($available_cartridges, 'ref') as $available_cartridge_id) {
            if (substr($available_cartridge_id, 0, 4) !== 'CTON' && substr($available_cartridge_id, 0, 2) !== 'CC') {
                $close_confirm_message .= "- $available_cartridge_id\n";
            }
        }

        if (!empty($close_confirm_message)) {
            $close_confirm_message = "Există consumabile instalabile dar neinstalate la client:\n$close_confirm_message\nSigur vreți să închideți tichetul?";
        }

        // Buttons.
        $buttons = [];
        switch ($options['mode']) {
        case self::MODE_CREATENORMAL:
            if ($closed || ($id > 0 && empty($options['_allow_buttons']))) {
                break;
            }

            $buttons['solve']  = $form->generateSubmit(
                'add', '&nbsp;', [
                    'data-required' => implode(',', array_keys(array_filter($prepared_data['field_required']))),
                    'class' => 'submit img-button status' . Ticket::SOLVED,
                    'title' => __('Solve', 'iservice'),
                    'onclick' => '$("[name=status]").val(' . Ticket::SOLVED . ');'
                ]
            );
            $buttons['wait']   = $form->generateSubmit(
                'add', '&nbsp;', [
                    'data-required' => implode(',', array_keys(array_filter($prepared_data['field_required']))),
                    'class' => 'submit img-button status' . Ticket::WAITING,
                    'title' => Ticket::getStatus(Ticket::WAITING),
                    'onclick' => '$("[name=status]").val(' . Ticket::WAITING . ');'
                ]
            );
            $buttons['plan']   = $form->generateSubmit(
                'add', '&nbsp;', [
                    'data-required' => implode(',', array_keys(array_filter($prepared_data['field_required']))),
                    'class' => 'submit img-button status' . Ticket::PLANNED,
                    'title' => Ticket::getStatus(Ticket::PLANNED),
                    'onclick' => '$("[name=status]").val(' . Ticket::PLANNED . ');'
                ]
            );
            $buttons['assign'] = $form->generateSubmit(
                'add', '&nbsp;', [
                    'data-required' => implode(',', array_keys(array_filter($prepared_data['field_required']))),
                    'class' => 'submit img-button status' . Ticket::ASSIGNED,
                    'title' => Ticket::getStatus(Ticket::ASSIGNED),
                    'onclick' => '$("[name=status]").val(' . Ticket::ASSIGNED . ');'
                ]
            );
            $buttons['new']    = $form->generateSubmit(
                'add', '&nbsp;', [
                    'class' => 'submit img-button status' . Ticket::INCOMING,
                    'title' => Ticket::getStatus(Ticket::INCOMING),
                    'onclick' => '$("[name=status]").val(' . Ticket::INCOMING . ');'
                ]
            );
            break;
        case self::MODE_READCOUNTER:
            if ($closed || ($id < 1 && $changeables > 0)) {
                break;
            }

            $buttons['add'] = $form->generateSubmit(
                $id > 0 ? 'update' : 'add', __('Send data', 'iservice'), [
                    'id' => 'btn_add',
                    'data-required' => implode(',', array_keys(array_filter($prepared_data['field_required']))),
                    'onclick' => !empty($last_opened_ticket->fields['id']) ? "return confirm(\"Există deja un tichet deschis. Doriți să continuați?\")" : '',
                // 'style' => ($changeables < 0) ? '' : 'display:none',
                ]
            );
            break;
        case self::MODE_CREATEINQUIRY:
        case self::MODE_CREATEREQUEST:
        case self::MODE_PARTNERCONTACT:
            if ($closed || $id > 0) {
                break;
            }

            $buttons['add'] = $form->generateSubmit(
                'add', __('Save'), [
                    'data-required' => implode(',', array_keys(array_filter($prepared_data['field_required']))),
                    'onclick' => !empty(PluginIserviceTicket::getLastIdForPrinterOrSupplier(0, $printer_id, true)) ? "return confirm(\"Există deja un tichet deschis. Doriți să continuați?\")" : '',
                ]
            );
            break;
        case self::MODE_CREATEQUICK:
            if ($closed) {
                break;
            }

            $action           = $id > 0 ? 'update' : 'add';
            $buttons[$action] = $form->generateSubmit($action, __('Save'), ['data-required' => implode(',', array_keys(array_filter($prepared_data['field_required']))), 'onclick' => "getElementsByName(\"status\")[0].value=" . Ticket::SOLVED . ";"]);
            if (!$ticket_consumables || $this->customfields->fields['exported_field']) {
                $buttons[$action . '_close'] = $form->generateSubmit(
                    $action, __('Save') . ' & ' . __('Close', 'iservice'), [
                        'data-required' => implode(',', array_keys(array_filter($prepared_data['field_required']))),
                        'data-confirm-message' => $close_confirm_message
                    ]
                );
            }

            $button_statuses = [Ticket::SOLVED, Ticket::WAITING, Ticket::PLANNED, Ticket::ASSIGNED];
            if (in_array($_SESSION["glpiactiveprofile"]["name"], ['super-admin'])) {
                $button_statuses[] = Ticket::INCOMING;
            }

            foreach ($button_statuses as $status) {
                $buttons[$status] = $form->generateSubmit(
                    $action, '&nbsp;', [
                        'data-required' => implode(',', array_keys(array_filter($prepared_data['field_required']))),
                        'class' => "submit img-button status$status",
                        'title' => Ticket::getStatus($status),
                        'onclick' => "$(\"[name=status]\").val($status);"
                    ]
                );
            }
            break;
        case self::MODE_MODIFY:
            if ($closed || empty($id) || $id < 1) {
                break;
            }

            $buttons['update'] = $form->generateSubmit('update', __('Save'), ['data-required' => implode(',', array_keys(array_filter($prepared_data['field_required'])))]);
            $buttons['wait']   = $form->generateSubmit('wait', __('Working', 'iservice'), ['data-required' => implode(',', array_keys(array_filter($prepared_data['field_required'])))]);
            break;
        case self::MODE_CARTRIDGEMANAGEMENT:
        case self::MODE_CLOSE:
            if ($closed || empty($id) || $id < 1) {
                if (IserviceToolBox::inProfileArray(['super-admin'])) {
                    $newer_closed_ticket_ids = self::getNewerClosedTikcetIds($this->getID(), $this->customfields->fields['effective_date'], $supplier_id, $printer_id);
                    if (count($newer_closed_ticket_ids)) {
                        $confirm = ['data-confirm-first' => count($newer_closed_ticket_ids) . " tichete închise mai noi vor fi redeschise. Sigur vreți să continuați?"];
                    } else {
                        $confirm = [];
                    }

                    $buttons['reopen'] = $form->generateSubmit('solve', __('Reopen', 'iservice'), $confirm);
                }

                break;
            }

            $buttons['close']           = $form->generateSubmit(
                'update', __('Close', 'iservice'), [
                    'data-required' => implode(',', array_keys(array_filter($prepared_data['field_required']))),
                    'data-confirm-message' => $close_confirm_message,
                    'onclick' => '$("[name=status]").val(' . Ticket::CLOSED . ');'
                ]
            );
            $buttons['services_export'] = $form->generateSubmit(
                'services_export', __('Save') . ' + ' . __('Generate services invoice', 'iservice'), [
                    'data-required' => implode(',', array_keys(array_filter($prepared_data['field_required']))),
                    'data-confirm-message' => $close_confirm_message
                ]
            );

            $button_statuses = [Ticket::SOLVED, Ticket::WAITING, Ticket::PLANNED, Ticket::ASSIGNED];
            if (in_array($_SESSION["glpiactiveprofile"]["name"], ['super-admin'])) {
                $button_statuses[] = Ticket::INCOMING;
            }

            foreach ($button_statuses as $status) {
                $confirm_alert    = ($status === Ticket::SOLVED || $this->fields['status'] != Ticket::SOLVED) ? '' : "if (!nativeConfirm(\"ATENȚIE! Schimbând starea tichetului, data efectivă va deveni data curentă în loc de {$this->customfields->fields['effective_date']}!\")) return false;";
                $buttons[$status] = $form->generateSubmit(
                    'update', '&nbsp;', [
                        'data-required' => implode(',', array_keys(array_filter($prepared_data['field_required']))),
                        'class' => "submit img-button status$status",
                        'title' => Ticket::getStatus($status),
                        'onclick' => "$confirm_alert$(\"[name=status]\").val($status);"
                    ]
                );
            }

            $export_button_options = [
                'onclick' => 'if ($(this).hasClass("disabled")) { return false; }',
                'data-required' => implode(',', array_keys(array_filter($prepared_data['field_required']))),
                'data-title' => 'Ticketul nu poate fi exportat până livrarea nu este finalizată',
            ];
            if (!$this->customfields->fields['delivered_field']) {
                $export_button_options['class'] = 'submit disabled';
                $export_button_options['title'] = 'Ticketul nu poate fi exportat până livrarea nu este finalizată';
            }

            $buttons['export'] = $form->generateSubmit('export', __('Save') . ' + ' . __('hMarfa export', 'iservice'), $export_button_options);
            $buttons['order']  = $form->generateSubmit('order', __('Save') . ' + ' . __('Order', 'iservice'), ['data-required' => implode(',', array_keys(array_filter($prepared_data['field_required'])))]);

            // If minimum percentage estimate is less then -150% and effective_date is not older than 4 days and the cartridge with minimum percentage estimate is not changed, the ticket cannot be closed
            $ticket_cartridges_mercurycodes = [];
            foreach ($ticket_cartridges as $tc) {
                foreach (explode(',', str_replace("'", "", $tc['compatible_mercury_codes_field'])) as $mercurycode) {
                    array_push($ticket_cartridges_mercurycodes, $mercurycode);
                }
            }

            // Do not allow close if there is a cartridge with very low estimate percentage
            // if (isset($printer_min_percentage[0]['min_estimate_percentage']) && $printer_min_percentage[0]['min_estimate_percentage'] < -1.50 && ceil((time() - strtotime($this->customfields->fields['effective_date'] ?? '2000-01-01')) / 60 / 60 / 24) < 4 && !in_array($printer_min_percentage[0]['mercury_code_field'], $ticket_cartridges_mercurycodes)) {
            // unset($buttons['close']);
            // }
            // If it is a movement ticket, it cannot be closed until the services invoice is not created.
            if ($this->customfields->fields['movement_id'] || $this->customfields->fields['movement2_id_field']) {
                unset($buttons['services_export']);
                if (($this->customfields->fields['movement_id']) && !$movement->fields['invoice']) {
                    unset($buttons['close']);
                }
            }

            // If ticket does not have consumables, it cannot be exported or ordered.
            if (!$ticket_consumables) {
                unset($buttons['order']);
                unset($buttons['export']);
            } else {
                // If ticket has consumables cannot generate services invoice.
                unset($buttons['services_export']);
                // If ticket has consumables and is exported, it cannot be ordered.
                if ($this->customfields->fields['exported_field']) {
                    unset($buttons['order']);
                } else {
                    // And if it is not exported yet, it cannot be closed.
                    unset($buttons['close']);
                }

                // Order cannot be placed 2 times.
                if ($order_status != 0) {
                    unset($buttons['order']);
                }

                // If order is placed but not completed, then cannot export.
                if ($order_status < 0) {
                    unset($buttons['export']);
                }
            }

            // If user is subtechnician, in self::MODE_CARTRIDGEMANAGEMENT and self::MODE_CLOSE modes he can only solve or put the ticket in wait.
            if ($_SESSION["glpiactiveprofile"]["name"] == 'subtehnician') {
                foreach (array_keys($buttons) as $button_name) {
                    if (!in_array($button_name, ['solve', 'wait'])) {
                        unset($buttons[$button_name]);
                    }
                }
            }
            break;
        case self::MODE_HMARFAEXPORT:
        default:
            break;
        }

        $buttons[] = "<input type='checkbox' name='_send_notification' /> " . __('Send notification', 'iservice');

        $show_warning       = IserviceToolBox::inProfileArray(['super-admin', 'admin', 'tehnician']) && ($last_opened_ticket->customfields->fields['effective_date'] ?? '2100-01-01' < $this->customfields->fields['effective_date']);
        $filter_description = urlencode(($printer->fields['name'] ?? '') . " (" . ($printer->fields['serial'] ?? '') . ") - " . ($printer->customfields->fields['usage_address_field'] ?? ''));
        $form->displayButtonsTableRow(
            $buttons, [
                'label' => $show_warning ? "<span style='color: red'>ATENȚIE! Există tickete deschise mai vechi, vezi <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/view.php?view=operations&operations0[printer_id]={$printer->fields['id']}&operations0[filter_description]=$filter_description' target='_blank'>lista lucrari</a></span>" : '',
            ]
        );

        /**/
        // Last 5 tickets.
        if (!$prepared_data['field_hidden']['_last_tickets'] && $printer_id > 0) {
            echo "<tr><td colspan = 2>";
            echo "<h3>", sprintf(__('Last %d tickets', 'iservice'), 5), "</h3>";
            $view = PluginIserviceViews::getView('last_n_tickets', false);
            $view->customize(['type' => PluginIserviceView_Last_n_Tickets::TYPE_FOR_PRINTER, 'n' => 5, 'printer_id' => $printer_id]);
            $view->display(true, false, 0, false);
            echo "</td></tr>";
        }

        // Last 5 tickets of category "Plati".
        if (!$prepared_data['field_hidden']['_last_tickets_plati'] && $supplier_id > 0) {
            echo "<tr><td colspan = 2>";
            echo "<h3>", sprintf(__('Last %d tickets with "%s" category', 'iservice'), 5, 'Plati'), "</h3>";
            $view = PluginIserviceViews::getView('last_n_tickets', false);
            $view->customize(['type' => PluginIserviceView_Last_n_Tickets::TYPE_PLATI, 'n' => 5, 'supplier_id' => $supplier_id]);
            $view->display(true, false, 0, false);
            echo "</td></tr>";
        }

        /**/
        echo "</table>";
        echo "<input type='hidden' name='id' value='$id'>";

        echo "</div>";

        if ($options['form']) {
            Html::closeForm();
        }

        return true;
    }

    public function prepareForShow($options): array
    {
        if (!isset($options['id'])) {
            $options['id'] = '';
        }

        $prepared_data['template']       = self::getModeTemplate($options['mode']);
        $prepared_data['default_values'] = self::getDefaultValues($options['mode'], $options['id']);
        $prepared_data['forced_values']  = self::getForcedValues($options['mode'], $options['id']);
        $prepared_data['field_hidden']   = self::getHiddenFields($options['mode'], $options['id']);
        $prepared_data['field_readonly'] = self::getReadOnlyFields($options['mode'], $options['id']);
        $prepared_data['field_required'] = self::getRequiredFields($options['mode'], $options['id']);

        $this->originalFields = $this->fields;

        $values = Html::cleanPostForTextArea(empty($options['get']) ? filter_input_array(INPUT_GET) : $options['get']);
        if (empty($values)) {
            $values = [];
        }

        // Restore saved value or override with page parameter.
        $saved = $this->restoreInput();

        foreach ($prepared_data['default_values'] as $name => $value) {
            if (!isset($values[$name])) {
                $values[$name] = isset($saved[$name]) ? $saved[$name] : $value;
            }
        }

        if (empty($options['id'])) {
            // Override defaut values from projecttask if needed.
            if (isset($options['projecttasks_id'])) {
                $pt = new ProjectTask();
                if ($pt->getFromDB($options['projecttasks_id'])) {
                    $values['name']    = $pt->getField('name');
                    $values['content'] = $pt->getField('name');
                }
            }
        }

        // Check category / type validity.
        if (!empty($values['itilcategories_id'])) {
            $cat = new ITILCategory();
            if ($cat->getFromDB($values['itilcategories_id'])) {
                switch ($values['type']) {
                case self::INCIDENT_TYPE :
                    if (!$cat->getField('is_incident')) {
                        $values['itilcategories_id'] = 0;
                    }
                    break;

                case self::DEMAND_TYPE :
                    if (!$cat->getField('is_request')) {
                        $values['itilcategories_id'] = 0;
                    }
                    break;

                default :
                    break;
                }
            }
        }

        $prepared_data['values_for_right_check'] = $values;

        $ticket_user                         = new Ticket_User();
        $ticket_users                        = $ticket_user->getActors($options['id']);
        $this->fields['_users_id_assign']    = $ticket_users[CommonITILActor::ASSIGN][0]['users_id'] ?? '';
        $this->fields['_users_id_observer']  = $ticket_users[CommonITILActor::OBSERVER][0]['users_id'] ?? '';
        $this->fields['_users_id_requester'] = $ticket_users[CommonITILActor::REQUESTER][0]['users_id'] ?? '';

        $supplier_ticket                      = new Supplier_Ticket();
        $ticket_suppliers                     = $supplier_ticket->getActors($options['id']);
        $this->fields['_suppliers_id_assign'] = $ticket_suppliers[CommonITILActor::ASSIGN][0]['suppliers_id'] ?? '';

        if (empty($options['id'])) {
            $this->userentities = [];
            if (!empty($values["_users_id_requester"])) {
                // Get all the user's entities.
                $all_entities = Profile_User::getUserEntities($values["_users_id_requester"], true, true);
                // For each user's entity, check if the technician which creates the ticket have access to it.
                foreach ($all_entities as $ID_entity) {
                    if (Session::haveAccessToEntity($ID_entity)) {
                        $this->userentities[] = $ID_entity;
                    }
                }
            }

            $this->countentitiesforuser = count($this->userentities);

            if (($this->countentitiesforuser > 0) && (!isset($this->fields["entities_id"]) || !in_array($this->fields["entities_id"], $this->userentities))) {
                // If entity is not in the list of user's entities,
                // then use as default value the first value of the user's entites list.
                $this->fields["entities_id"] = $this->userentities[0];
                // Pass to values.
                $values['entities_id'] = $this->userentities[0];
            }
        }

        if ($values['type'] < 1) {
            $values['type'] = Entity::getUsedConfig('tickettype', $values['entities_id'], '', Ticket::INCIDENT_TYPE);
        }

        if (!isset($options['template'])) {
            $options['template'] = 0;
        }

        // save original data.
        $prepared_data['initial_values'] = $this->fields;

        // Load ticket template if available.
        if (!empty($options['id'])) {
            $tt = $this->getITILTemplateToUse($options['template'], $this->fields['type'], $this->fields['itilcategories_id'], $this->fields['entities_id']);
        } else {
            $tt = $this->getITILTemplateToUse($options['template'], $values['type'], $values['itilcategories_id'], $values['entities_id']);
        }

        // Predefined fields from template : reset them.
        if (isset($values['_predefined_fields'])) {
            $values['_predefined_fields'] = Toolbox::decodeArrayFromInput($values['_predefined_fields']);
        } else {
            $values['_predefined_fields'] = [];
        }

        // Store predefined fields to be able not to take into account on change template.
        // Only manage predefined values on ticket creation.
        $predefined_fields = [];
        if (empty($options['id'])) {
            if (isset($tt->predefined) && count($tt->predefined)) {
                foreach ($tt->predefined as $predeffield => $predefvalue) {
                    if (isset($prepared_data['default_values'][$predeffield])) {
                        // Is always default value : not set.
                        // Set if already predefined field.
                        // Set if ticket template change.
                        if (((count($values['_predefined_fields']) == 0) && ($values[$predeffield] == $prepared_data['default_values'][$predeffield])) || (isset($values['_predefined_fields'][$predeffield]) && ($values[$predeffield] == $values['_predefined_fields'][$predeffield])) || (isset($values['_tickettemplates_id']) && ($values['_tickettemplates_id'] != $tt->getID()))) {
                            // Load template data.
                            $values[$predeffield]            = $predefvalue;
                            $this->fields[$predeffield]      = $predefvalue;
                            $predefined_fields[$predeffield] = $predefvalue;
                        }
                    }
                }

                // All predefined override : add option to say predifined exists.
                if (count($predefined_fields) == 0) {
                    $predefined_fields['_all_predefined_override'] = 1;
                }
            } else { // No template load : reset predefined values.
                if (count($values['_predefined_fields'])) {
                    foreach ($values['_predefined_fields'] as $predeffield => $predefvalue) {
                        if ($values[$predeffield] == $predefvalue) {
                            $values[$predeffield] = $prepared_data['default_values'][$predeffield];
                        }
                    }
                }
            }
        }

        // Put ticket template on $values for actors.
        $values['_tickettemplate'] = $prepared_data['template_object'] = $tt;

        $prepared_data['closed'] = isset($this->fields['status']) && in_array($this->fields['status'], $this->getClosedStatusArray());

        if ($options['id'] && $prepared_data['closed']) {
            $values['_noupdate'] = true;
        }

        foreach ($values as $key => $val) {
            if (!isset($this->fields[$key])) {
                $this->fields[$key] = $val;
            }
        }

        foreach ($prepared_data['forced_values'] as $key => $val) {
            $this->fields[$key] = $val;
        }

        $prepared_data['fields'] = $this->fields;

        return $prepared_data;
    }

    public function processFieldsByInput(): array
    {
        $result = [];

        $result['variables']['movement_id']        = IserviceToolBox::getInputVariable('movement_id', isset($this->customfields) ? $this->customfields->fields['movement_id'] : '');
        $result['variables']['movement2_id_field'] = IserviceToolBox::getInputVariable('movement2_id_field', isset($this->customfields) ? $this->customfields->fields['movement2_id_field'] : '');
        $result['variables']['em_mail_id_field']   = IserviceToolBox::getInputVariable('em_mail_id_field', isset($this->customfields) ? $this->customfields->fields['em_mail_id_field'] : '');

        $this->fields['items_id'] = IserviceToolBox::getArrayInputVariable('items_id', (is_array($this->fields['items_id']) ? $this->fields['items_id'] : ['Printer' => [$this->fields['items_id']]]) ?? ['Printer' => [0]]);
        $printer_id               = $this->fields['items_id']['Printer'][0] ?? 0;
        $printer                  = new PluginIservicePrinter();
        if (empty($printer_id) && $this->getID() > 0) {
            $printer = $this->getFirstPrinter();
            if (!$printer->isNewItem()) {
                $this->fields['items_id']['Printer'][0] = $printer_id = $printer->getID();
            }
        } else {
            $printer->getFromDB($printer_id);
        }

        $result['variables']['printer']    = $printer;
        $result['variables']['printer_id'] = $printer_id;

        $this->fields['_suppliers_id_assign'] = $supplier_id = IserviceToolBox::getInputVariable('_suppliers_id_assign', $this->fields['_suppliers_id_assign'] ?? '');
        if (empty($supplier_id) && !empty($printer_id)) {
            $infocom = new Infocom();
            if ($infocom->getFromDBforDevice('Printer', $printer_id)) {
                $this->fields['_suppliers_id_assign'] = $supplier_id = $infocom->fields['suppliers_id'];
            }
        }

        $result['variables']['supplier_id'] = $supplier_id;

        return $result;
    }

    public function getFromDB($ID): bool
    {
        $this->customfields = new PluginFieldsTicketticketcustomfield();
        if (parent::getFromDB($ID)) {
            if (!$this->customfields->getFromDBByItemsId($ID) && !$this->customfields->add(['add' => 'add', 'items_id' => $ID, '_no_message' => true])) {
                return false;
            }

            $this->fields['items_id']['Printer'] = array_column(IserviceToolBox::getQueryResult("select it.items_id from glpi_items_tickets it where tickets_id = $ID and itemtype = 'Printer'"), 'items_id');

            // Further code poosibility.
            self::$item_cache[$ID] = $this;
            return true;
        }

        return false;
    }

    /*
     * @return int Returns 0 if no order was placed, a positive number if all ordered consumables were received indicating their number, or a negative number indicating the not yet received consumables.
     */
    public function getOrderStatus(): int
    {
        if ($this->isNewItem()) {
            return 0;
        }

        global $DB;
        $query = "
            SELECT oc.ordered_consumables, rc.received_consumables
            FROM glpi_tickets t
            LEFT JOIN ( SELECT COUNT(id) ordered_consumables, tickets_id
                                    FROM glpi_plugin_iservice_intorders io
                                    WHERE NOT tickets_id IS NULL
                                    GROUP BY tickets_id
                                ) oc ON oc.tickets_id = t.id
            LEFT JOIN ( SELECT COUNT(io.id) received_consumables, tickets_id
                                    FROM glpi_plugin_iservice_intorders io
                                    JOIN glpi_plugin_iservice_orderstatuses os ON os.id = io.plugin_iservice_orderstatuses_id
                                    WHERE NOT tickets_id IS NULL AND os.weight >= " . PluginIserviceOrderStatus::WEIGHT_RECEIVED . "
                                    GROUP BY tickets_id
                                ) rc ON rc.tickets_id = t.id
            WHERE t.id = {$this->getID()}
            ";
        if (($result = $DB->query($query)) !== false) {
            $query_result = $DB->fetchAssoc($result);
            return intval($query_result['ordered_consumables']) == intval($query_result['received_consumables']) ? intval($query_result['ordered_consumables']) : intval($query_result['received_consumables']) - intval($query_result['ordered_consumables']);
        }

        return 0;
    }

    public function explodeArrayFields(): void
    {
        foreach ($this->fields as $field_name => $field_value) {
            if (strpos($field_name, '[')) {
                $parts                                             = explode('[', $field_name, 2);
                $this->fields[$parts[0]][substr($parts[1], 0, -1)] = $field_value;
            }
        }
    }

    public function addCartridge(&$error_message, $cartridgeitems_id, $supplier_id, $printer_id, $install_date = null, $imposed_cartridge_id = null): bool
    {
        $cartridge_item_data = explode('l', $cartridgeitems_id, 2);
        $cartridge_item_id   = $cartridge_item_data[0];
        $base_condition      = "AND EXISTS (SELECT * FROM glpi_plugin_iservice_consumables_tickets WHERE amount > 0 AND new_cartridge_ids LIKE CONCAT('%|', glpi_cartridges.id, '|%'))";
        $location_condition  = 'AND (locations_id_field IS null OR locations_id_field < 1)';
        $printer_condition   = 'AND printers_id = 0 AND date_use IS null AND date_out IS null';
        $date_condition      = empty($install_date) ? '' : "AND date_in <= '$install_date'";
        if (count($cartridge_item_data) > 1) {
            $cartridge_item_data = explode('p', $cartridge_item_data[1], 2);
            $location_condition  = "AND locations_id_field = $cartridge_item_data[0]";
            if (count($cartridge_item_data) > 1) {
                $printer_condition = "AND printers_id = $cartridge_item_data[1] AND date_out IS null";
            }
        }

        $cartridge              = new Cartridge();
        $cartridge_customfields = new PluginFieldsCartridgeitemcartridgeitemcustomfield();
        $cartridges             = $cartridge->find("suppliers_id_field = $supplier_id AND cartridgeitems_id = $cartridge_item_id $base_condition $location_condition $printer_condition $date_condition", ["id ASC"]);

        // First check the cartridges at the given partner. If there are none, check the partners in the same group.
        if (count($cartridges) === 0) {
            $cartridges = $cartridge->find("FIND_IN_SET (suppliers_id_field, (SELECT group_field FROM glpi_plugin_fields_suppliersuppliercustomfields WHERE items_id = $supplier_id)) AND cartridgeitems_id = $cartridge_item_id $location_condition $printer_condition $date_condition", ["id ASC"]);
        }

        if (count($cartridges) === 0) {
            $error_message = "Stoc insuficient!";
            return false;
        }

        if (!empty($imposed_cartridge_id)) {
            if (in_array($imposed_cartridge_id, array_column($cartridges, 'id'))) {
                $cartridge_id_to_install = $imposed_cartridge_id;
            } else {
                $error_message = "Cartușul impus pentru instalare nu este instalabil pe acest aparat!";
                return false;
            }
        } else {
            $cartridge_id_to_install = array_shift($cartridges)['id'];
        }

        $cartridge->getFromDB($cartridge_id_to_install);
        PluginIserviceDB::populateByItemsId($cartridge_customfields, $cartridge->fields['cartridgeitems_id']);
        $cartridge->fields['printers_id']        = $printer_id;
        $cartridge->fields['mercury_code_field'] = $cartridge_customfields->fields['mercury_code_field'];

        $plugin_iservice_cartridges_tickets = new PluginIserviceCartridge_Ticket();

        $used_types = PluginIserviceDB::getQueryResult(
            "
            select ct.plugin_fields_typefielddropdowns_id selected_type
            from glpi_plugin_iservice_cartridges_tickets ct
            join glpi_cartridges c on c.id = ct.cartridges_id
            where ct.tickets_id = {$this->getID()}
              and c.cartridgeitems_id = {$cartridge->fields['cartridgeitems_id']}
            "
        );

        foreach (explode(',', $cartridge_customfields->fields['supported_types_field']) as $supported_type) {
            if (!in_array($supported_type, array_column($used_types, 'selected_type'))) {
                $cartridge->fields['plugin_fields_typefielddropdowns_id'] = $supported_type;
                break;
            }
        }

        $first_emptiable_cartridge = PluginIserviceCartridge::getFirstEmptiableByCartridge($cartridge);

        if (!$plugin_iservice_cartridges_tickets->add(
            [
                'add' => 'add',
                'tickets_id' => $this->getID(),
                'cartridges_id' => $cartridge_id_to_install,
                'plugin_fields_typefielddropdowns_id' => $cartridge->fields['plugin_fields_typefielddropdowns_id'],
                'cartridges_id_emptied' => empty($first_emptiable_cartridge[$cartridge->getIndexName()]) ? 'NULL' : $first_emptiable_cartridge[$cartridge->getIndexName()],
                '_no_message' => true
            ]
        )
        ) {
            return false;
        }

        if (!$cartridge->update(['id' => $cartridge_id_to_install, 'printers_id' => $printer_id, '_no_message' => true])) {
            return  false;
        }

        return true;
    }

    public function addMessageOnAddAction(): void
    {
        $this->internalAddMessageOnChange('add');
    }

    public function addMessageOnUpdateAction(): void
    {
        $this->internalAddMessageOnChange('update');

    }

    protected function internalAddMessageOnChange($changeType): void
    {
        $addMessAfterRedirect = false;
        if (isset($this->input["_$changeType"])) {
            $addMessAfterRedirect = true;
        }

        if (isset($this->input['_no_message']) || !$this->auto_message_on_action) {
            $addMessAfterRedirect = false;
        }

        if (!$addMessAfterRedirect) {
            return;
        }

        global $CFG_PLUGIN_ISERVICE;

        $ticket_name = (isset($this->input['_no_message_link']) ? $this->getID() : $this->getLink(['mode' => $this->input['_mode']]));

        switch ($this->input['_mode']) {
        case PluginIserviceTicket::MODE_CREATENORMAL:
            $ticketreport_href = "$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.report.php?id={$this->getID()}";
            $suffix            = sprintf("%s <a href='$ticketreport_href' target='_blank'>%s</a>", ucfirst(__('see', 'iservice')), lcfirst(__('intervention report', 'iservice')));
            break;
        case PluginIserviceTicket::MODE_CREATEQUICK:
        case PluginIserviceTicket::MODE_CLOSE:
            $supplier = PluginIservicePartner::getFromTicketInput($this->input);
            $s2       = PluginIservicePartner::get(1);
            if ($supplier->isNewItem()) {
                $suffix = "";
            } else {
                $printers_href = "$CFG_PLUGIN_ISERVICE[root_doc]/front/view.php?view=printers&printers0[supplier_name]=" . $supplier->fields['name'];
                $suffix        = sprintf("%s <a href='$printers_href'>%s</a>", ucfirst(__('see', 'iservice')), lcfirst(_n('Printer', 'Printers', 2, 'iservice')));
            }
            break;
        case PluginIserviceTicket::MODE_PARTNERCONTACT:
            $suffix = "<a href='#' onclick='window.close();'>" . __('Close', 'iservice') . "</a>";
            break;
        default:
            $suffix = '';
            break;
        }

        if (!empty($suffix)) {
            $suffix = "<br>$suffix";
        }

        IserviceToolBox::clearAfterRedirectMessages(INFO);

        Session::addMessageAfterRedirect(sprintf(__('Ticket %s saved successfully.', 'iservice'), stripslashes($ticket_name)) . stripslashes($suffix));
    }

    public function getLink($options = []): string
    {
        if (empty($this->fields['id'])) {
            return '';
        }

        $label = $this->getID();

        if ($this->no_form_page || !$this->can($this->fields['id'], READ)) {
            return $label;
        }

        return "<a href='{$this->getLinkURL($options)}'>$label</a>";
    }

    public function getLinkURL($additionalParams = []): string
    {
        $link = parent::getLinkURL();
        foreach ($additionalParams as $paramName => $paramValue) {
            $link .= "&$paramName=$paramValue";
        }

        return $link;
    }

    public function getInstalledCartridgeIds(): array
    {
        return self::getTicketInstalledCartridgeIds($this);
    }

    public static function getTicketInstalledCartridgeIds(Ticket $ticket): array
    {
        if (empty(self::$installed_cartridges[$ticket->getID()])) {
            self::$installed_cartridges[$ticket->getID()] = array_column(PluginIserviceCartridge_Ticket::getForTicketId($ticket->getID()), 'cartridges_id');
        }

        return self::$installed_cartridges[$ticket->getID()];
    }

    public function isClosed(): bool
    {
        return self::isTicketClosed($this);
    }

    public static function isTicketClosed(Ticket $ticket): bool
    {
        return ($ticket->fields['status'] ?? '') == TICKET::CLOSED;
    }

    public static function wasTicketClosed(Ticket $ticket): bool
    {
        return ($ticket->oldvalues['status'] ?? '') == TICKET::CLOSED;
    }

    public function isClosing(): bool
    {
        return self::isTicketClosing($this);
    }

    public static function isTicketClosing(Ticket $ticket): bool
    {
        return self::isTicketChangingStatus($ticket) && ($ticket->input['status'] ?? '') == Ticket::CLOSED;
    }

    public static function wasTicketClosing(Ticket $ticket): bool
    {
        return self::wasTicketChangingStatus($ticket) && ($ticket->input['status'] ?? '') == Ticket::CLOSED;
    }

    public function isOpening(): bool
    {
        return self::isTicketOpening($this);
    }

    public static function isTicketOpening(Ticket $ticket): bool
    {
        return self::isTicketClosedStatusChanging($ticket) && ($ticket->input['status'] ?? Ticket::CLOSED) != Ticket::CLOSED;
    }

    public static function wasTicketOpening(Ticket $ticket): bool
    {
        return self::wasTicketClosedStatusChanging($ticket) && ($ticket->input['status'] ?? Ticket::CLOSED) != Ticket::CLOSED;
    }

    public function isStatusChanging(): bool
    {
        return self::isTicketChangingStatus($this);
    }

    public static function isTicketChangingStatus(Ticket $ticket): bool
    {
        if (empty($ticket->input['status'])) {
            return false;
        }

        return $ticket->input['status'] != $ticket->fields['status'] ?? '';
    }

    public static function wasTicketChangingStatus(Ticket $ticket): bool
    {
        if (empty($ticket->input['status'])) {
            return false;
        }

        return $ticket->input['status'] != ($ticket->oldvalues['status'] ?? '');
    }

    public function isClosedStatusChanging(): bool
    {
        return self::isTicketClosedStatusChanging($this);
    }

    public static function isTicketClosedStatusChanging(Ticket $ticket): bool
    {
        return self::isTicketClosing($ticket) xor self::isTicketClosed($ticket);
    }

    public static function wasTicketClosedStatusChanging(Ticket $ticket): bool
    {
        return self::wasTicketClosing($ticket) xor self::wasTicketClosed($ticket);
    }

    public function post_updateItem($history = 1): void
    {
        parent::post_updateItem($history);

        // This functionality is missing from Glpi (the item addition is solved with a different approach)
        // This function is copied from CommonITILObject::handleItemsIdInput
        // But it is not good any more, because $this->input['items_id'] was flattened in prepareInputForUpdate,
        // so it had to be refactored
        /**
        if (!empty($this->input['items_id'])) {
            $item_ticket = new Item_Ticket();
            foreach ($this->input['items_id'] as $itemtype => $items) {
                foreach ($items as $items_id) {
                    if (empty($items_id)) {
                        continue;
                    }
                    $item_ticket->add([
                        'items_id'      => $items_id,
                        'itemtype'      => $itemtype,
                        'tickets_id'    => $this->getID(),
                        '_disablenotif' => true
                    ]);
                }
            }
        }
        /**/
        if (!empty($this->input['items_id'])) {
            $item_ticket = new Item_Ticket();
            $item_ticket->add(
                [
                    'items_id'      => $this->input['items_id'],
                    'itemtype'      => 'Printer',
                    'tickets_id'    => $this->getID(),
                    '_disablenotif' => true
                ]
            );
        }

        if (!empty($this->input["_suppliers_id_assign"])) {
            if (is_array($this->input["_suppliers_id_assign"])) {
                $tab_assign = $this->input["_suppliers_id_assign"];
            } else {
                $tab_assign   = [];
                $tab_assign[] = $this->input["_suppliers_id_assign"];
            }

            $supplierToAdd   = [];
            $supplier_ticket = new Supplier_Ticket();
            foreach ($tab_assign as $key_assign => $assign) {
                if (in_array($assign, $supplierToAdd) || empty($assign)) {
                    // This assigned supplier ID is already added.
                    continue;
                }

                if ($supplier_ticket->add(
                    [
                        'tickets_id' => $this->getID(),
                        'suppliers_id' => $assign,
                        'type'         => CommonITILActor::ASSIGN,
                        'use_notification' => 0,
                    ]
                )
                ) {
                    $supplierToAdd[] = $assign;
                }
            }
        }

    }

    public function prepareInputForAdd($input): array|bool
    {
        foreach (array_keys($input['items_id']) as $itemtype) {
            foreach ($input['items_id'][$itemtype] as $key => $value) {
                if (empty($value)) {
                    unset($input['items_id'][$itemtype][$key]);
                }
            }

            if (empty($input['items_id'][$itemtype])) {
                unset($input['items_id'][$itemtype]);
            }
        }

        return parent::prepareInputForAdd($input);
    }

    public function prepareInputForUpdate($input): array
    {
        foreach ([CommonITILActor::ASSIGN => 'assign', CommonITILActor::REQUESTER => 'requester', CommonITILActor::OBSERVER => 'observer'] as $user_type_key => $user_type) {
            foreach (['glpi_tickets_users' => 'users_id', 'glpi_suppliers_tikcets' => 'suppliers_id'] as $table => $item_type) {
                if ((($input['_' . $item_type . '_' . $user_type] ?? 0) ?: 0) != (($input['_' . $item_type . '_' . $user_type . '_original'] ?? 0) ?: 0)) {
                    global $DB;
                    $DB->query("delete from $table where type = $user_type_key and tickets_id = $input[id]");
                }

                if (!is_array($input['_' . $item_type . '_' . $user_type] ?? [])) {
                    if (empty($input['_' . $item_type . '_' . $user_type])) {
                        unset($input['_' . $item_type . '_' . $user_type]);
                    } else {
                        $input['_' . $item_type . '_' . $user_type] = [$input['_' . $item_type . '_' . $user_type]];
                    }
                }
            }
        }

        $input = parent::prepareInputForUpdate($input);
        if (isset($this->fields['items_id']['Printer'][0])) {
            $this->fields['items_id'] = $this->fields['items_id']['Printer'][0];
        }

        if (isset($input['items_id']['Printer'][0])) {
            $input['items_id'] = $input['items_id']['Printer'][0];
        }

        return $input;
    }

    public static function sendNotificationForTicket($ticket, $config_key = '', $params = []): ?bool
    {
        global $CFG_GLPI;

        /*
        * @var $ticket PluginIserviceTicket
        */
        if (!($ticket instanceof PluginIserviceTicket)) {
            $ticket_id = ($ticket instanceof Ticket) ? $ticket->getID() : intval($ticket);
            $ticket    = new PluginIserviceTicket();
            if (!$ticket->getFromDB($ticket_id)) {
                return false;
            }
        }

        $ticket->customfields->getFromDB($ticket->customfields->getID());
        $supplier     = $ticket->getFirstAssignedPartner();
        $printer      = $ticket->getFirstPrinter();
        $followup     = new PluginIserviceTicketFollowup();
        $itilcategory = new ITILCategory();
        $itilcategory->getFromDB($ticket->fields['itilcategories_id']);

        $config_array = [
            'notification' => [
                'to_addresses' => $ticket->customfields->fields['notificationmailfield'],
                'subject' => $ticket->fields['name'],
                'body' =>
                    "Pentru partenerul {$supplier->fields['name']}\n\n" .
                    "Tichetul {$ticket->getID()} a fost [ticket_verb] la data de " . date('d.m.Y H:i:s') . "\n\n" .
                    "Titlu tichet: {$ticket->fields['name']}\n" .
                    "Nume aparat: {$printer->fields['name']}\n" .
                    "Serie aparat: {$printer->fields['serial']}\n\n" .
                    "Descriere tichet:\n" . strip_tags(IserviceToolBox::br2nl($ticket->fields['content'])) . "\n\n" .
                    "Adnotări:\n" . $followup->getShortForMail($ticket->getID()) . "\n"
            ],
            'readcounter' => [
                'subject' => "{$itilcategory->fields['name']} nr. {$ticket->getID()} - {$supplier->fields['name']}",
                'body' => $printer->isNewItem() ? "{$ticket->fields['name']}\nAcest tichet nu are aparat asociat." :
                    "Vă mulțumim pentru raportare. Următoarele informații au fost salvate:\n\n" .
                    "Număr tichet: {$ticket->getID()}\n" .
                    "Dată tichet: {$ticket->fields['date']}\n" .
                    "Titlu tichet: {$ticket->fields['name']}\n" .
                    "Partener: {$supplier->fields['name']}\n" .
                    "Nume aparat: {$printer->fields['name']}\n" .
                    "Serie aparat: {$printer->fields['serial']}\n" .
                    (empty($ticket->fields['total2_black']) ? "" : "Contor alb-negru: {$ticket->fields['total2_black']}\n") .
                    (empty($ticket->fields['total2_color']) ? "" : "Contor color: {$ticket->fields['total2_color']}\n") .
                    "Descriere tichet:\n" . strip_tags(IserviceToolBox::br2nl($ticket->fields['content'])) . "\n\n" .
                    "Adnotări:\n" . $followup->getShortForMail($ticket->getID()) . "\n"
            ],
        ];

        $config = array_merge(
            $config_array[$config_key] ?? [
                'force_new' => false,
            ], $params
        );

        if ($config['force_new'] || $ticket->fields['status'] === Ticket::INCOMING) {
            $ticket_verb = 'creat';
        } elseif (in_array($ticket->fields['status'], Ticket::getClosedStatusArray())) {
            $ticket_verb = 'închis';
        } elseif ($ticket->fields['status'] === Ticket::SOLVED) {
            $ticket_verb = 'soluționat';
        } else {
            $ticket_verb = 'modificat';
        }

        $config['body'] = str_replace('[ticket_verb]', $ticket_verb, $config['body']);

        $mmail = new GLPIMailer();

        $mmail->AddCustomHeader("Auto-Submitted: auto-generated");
        $mmail->AddCustomHeader("X-Auto-Response-Suppress: OOF, DR, NDR, RN, NRN");

        $mmail->SetFrom($CFG_GLPI["admin_email"], $CFG_GLPI["admin_email_name"], false);

        foreach (preg_split("/(,|;)/", $config['to_addresses']) as $to_address) {
            $mmail->AddAddress(trim($to_address));
        }

        $mmail->Subject = "[iService] " . $config['subject'];

        $mmail->Body = $config['body'] . "\n\n--\n$CFG_GLPI[mailing_signature]";

        if (!$mmail->Send()) {
            Session::addMessageAfterRedirect(__('Could not send ticketreport to', 'iservice') . " {$config[to_addresses]}: $mmail->ErrorInfo", false, ERROR);
        } else {
            Session::addMessageAfterRedirect(__('Confirmation mail sent to', 'iservice') . " {$config[to_addresses]}");
        }

        return true;

    }

}

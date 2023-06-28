<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceProfile extends Profile
{

    static $rightname = "config";

    static function checkRight($module, $right = READ)
    {
        if (Session::getLoginUserID()) {
            Session::checkRight($module, $right);
        } else {
            switch ($module) {
            case 'plugin_iservice_view_facturi_client':
                return;
            default:
                Session::redirectIfNotLoggedIn();
                ;
            }
        }
    }

    static function changeprofile()
    {

        function right_array($right, $profiles)
        {
            $result = [];
            foreach ($profiles as $profile) {
                $result[$profile] = $right;
            }

            return $result;
        }

        $level10_profiles  = ['client'];
        $level15_profiles  = ['superclient', 'subtehnician'];
        $level20_profiles  = ['tehnician'];
        $level30_profiles  = ['admin', 'super-admin'];
        $low_profiles      = array_merge($level10_profiles, $level15_profiles);
        $elevated_profiles = array_merge($level20_profiles, $level30_profiles);
        $all_profiles      = array_merge($low_profiles, $elevated_profiles);

        $profile_rights = [
            'plugin_iservice' => right_array(READ, $all_profiles),
            'plugin_iservice_config' => right_array(ALLSTANDARDRIGHT, $level30_profiles),
            'plugin_iservice_central' => right_array(ALLSTANDARDRIGHT, $all_profiles),
            'plugin_iservice_user_preferences' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_interface_original' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_apache_restart' => right_array(ALLSTANDARDRIGHT, $level30_profiles),
            'plugin_iservice_planlunar' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
        // '-------------------------------' => right_array(ALLSTANDARDRIGHT, array()),
            'plugin_iservice_hmarfa' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_ticket_hmarfa_export_close' => right_array(ALLSTANDARDRIGHT, $level30_profiles),
        // '-------------------------------' => right_array(ALLSTANDARDRIGHT, array()),
            'plugin_iservice_views' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_tickets' => right_array(ALLSTANDARDRIGHT, $all_profiles),
            'plugin_iservice_view_operations' => right_array(ALLSTANDARDRIGHT, array_merge(['subtehnician'], $elevated_profiles)),
            'plugin_iservice_view_printers' => right_array(ALLSTANDARDRIGHT, $all_profiles),
            'plugin_iservice_view_printercounters' => right_array(ALLSTANDARDRIGHT, array_merge($level15_profiles, $elevated_profiles)),
            'plugin_iservice_view_printercounters2' => right_array(ALLSTANDARDRIGHT, array_merge($level15_profiles, $elevated_profiles)),
            'plugin_iservice_view_movements' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_partners' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_contracts' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_facturi_client' => array_merge(right_array(READ, $low_profiles), right_array(ALLSTANDARDRIGHT, $elevated_profiles)),
            'plugin_iservice_view_evaluation' => array_merge(right_array(READ, $level20_profiles), right_array(ALLSTANDARDRIGHT, $level30_profiles)),
            'plugin_iservice_view_intorders' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_extorders' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_reminders' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_cartridges' => right_array(ALLSTANDARDRIGHT, $all_profiles),
            'plugin_iservice_view_emaintenance' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_global_readcounter' => right_array(ALLSTANDARDRIGHT, array_merge($level15_profiles, $elevated_profiles)),
        // '-------------------------------' => right_array(ALLSTANDARDRIGHT, array()),
            'plugin_iservice_view_unpaid_invoices' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_skipped_payment' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_loturi_iesire' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_loturi_stoc' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_stoc' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_loturi_intrare' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_foaie_de_parcurs' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_price_list' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_pending_emails' => right_array(ALLSTANDARDRIGHT, $level30_profiles),
        // '-------------------------------' => right_array(ALLSTANDARDRIGHT, array()),
            'plugin_iservice_contract' => array_merge(right_array(READ, $level20_profiles), right_array(ALLSTANDARDRIGHT, $level30_profiles)),
        // '-------------------------------' => right_array(ALLSTANDARDRIGHT, array()),
            'plugin_iservice_printer' => right_array(ALLSTANDARDRIGHT, array_merge(['subtehnician'], $elevated_profiles)),
            'plugin_iservice_printer_full' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
        // '-------------------------------' => right_array(ALLSTANDARDRIGHT, array()),
            'plugin_iservice_movement' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_intorder' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_extorder' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_orderstatus' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_pendingemails' => right_array(ALLSTANDARDRIGHT, $level30_profiles),
        // '-------------------------------' => right_array(ALLSTANDARDRIGHT, array()),
            'plugin_iservice_ticket_' . PluginIserviceTicket::MODE_NONE => [],
            'plugin_iservice_ticket_' . PluginIserviceTicket::MODE_CREATENORMAL => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_ticket_' . PluginIserviceTicket::MODE_READCOUNTER => right_array(ALLSTANDARDRIGHT, $all_profiles),
            'plugin_iservice_ticket_' . PluginIserviceTicket::MODE_CREATEINQUIRY => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_ticket_' . PluginIserviceTicket::MODE_CREATEQUICK => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_ticket_' . PluginIserviceTicket::MODE_MODIFY => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_ticket_' . PluginIserviceTicket::MODE_CREATEREQUEST => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_ticket_' . PluginIserviceTicket::MODE_PARTNERCONTACT => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_ticket_' . PluginIserviceTicket::MODE_CARTRIDGEMANAGEMENT => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_ticket_' . PluginIserviceTicket::MODE_HMARFAEXPORT => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_ticket_' . PluginIserviceTicket::MODE_CLOSE => right_array(ALLSTANDARDRIGHT, array_merge(['subtehnician'], $elevated_profiles)),
        // '-------------------------------' => right_array(ALLSTANDARDRIGHT, array()),
            'plugin_iservice_ticket_own_printers' => right_array(ALLSTANDARDRIGHT, $all_profiles),
            'plugin_iservice_ticket_group_printers' => right_array(ALLSTANDARDRIGHT, array_merge(['superclient'], $elevated_profiles)),
            'plugin_iservice_ticket_assigned_printers' => right_array(ALLSTANDARDRIGHT, array_merge(['subtehnician'], $elevated_profiles)),
            'plugin_iservice_ticket_all_printers' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
        // '-------------------------------' => right_array(ALLSTANDARDRIGHT, array()),
            'plugin_iservice_invoice_confirm' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_docgenerator' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_emaintenance' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
        // '-------------------------------' => right_array(ALLSTANDARDRIGHT, array()),
            'plugin_iservice_admintask_RedistributeCartridges' => right_array(ALLSTANDARDRIGHT, $level30_profiles),
            'plugin_iservice_admintask_DataIntegrityTest' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
        ];

        // Only user 8 has access to the backup menu
        if ($_SESSION['glpiID'] == 8) {
            $_SESSION['glpiactiveprofile']['plugin_iservice_admintask_Backup'] = ALLSTANDARDRIGHT;
        }

        $current_profile = $_SESSION['glpiactiveprofile']['name'];
        foreach ($profile_rights as $right => $profiles) {
            if (isset($profiles[$current_profile])) {
                $_SESSION['glpiactiveprofile'][$right] = $profiles[$current_profile];
            }
        }

        $iservice_by_default = [
            'super-admin' => 'central',
            'admin' => 'central',
            'tehnician' => 'central',
            'subtehnician' => 'central',
            'superclient' => 'central',
            'client' => 'central'
        ];

        if (isset($iservice_by_default[$current_profile])) {
            $_SESSION['glpiactiveprofile']['iservice_interface'] = $iservice_by_default[$current_profile];
        } else if (isset($_SESSION['glpiactiveprofile']['iservice_interface'])) {
            unset($_SESSION['glpiactiveprofile']['iservice_interface']);
        }
    }

}

<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceProfile extends Profile
{

    public static $rightname = "config";

    public static function checkRight($module, $right = READ)
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

    public static function changeprofile()
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

            'plugin_iservice_monthly_plan' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_hmarfa' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_ticket_hmarfa_export_close' => right_array(ALLSTANDARDRIGHT, $level30_profiles),
            'plugin_iservice_views' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_stock' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_unpaid_invoices' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_stock_lots' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_skipped_payment' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_route_manifest' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_price_list' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_outbound_lots' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
            'plugin_iservice_view_inbound_lots' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),

            'plugin_iservice_admintask_DataIntegrityTest' => right_array(ALLSTANDARDRIGHT, $elevated_profiles),
        ];

        $current_profile = strtolower($_SESSION['glpiactiveprofile']['name']);
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

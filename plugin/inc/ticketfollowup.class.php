<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceTicketFollowup extends ItilFollowup
{

    /**
     * @param $ID  integer  ID of the ticket
     **/
    static function showShortForTicket($ID)
    {
        global $DB, $CFG_GLPI;

        // Print Followups for a job
        $showprivate = Session::haveRight(self::$rightname, self::SEEPRIVATE);

        $RESTRICT = "";
        if (!$showprivate) {
            $RESTRICT = " AND (`is_private` = '0'
                            OR `users_id` ='" . Session::getLoginUserID() . "') ";
        }

        // Get Number of Followups
        $query  = "SELECT *
                FROM `glpi_itilfollowups`
                WHERE `items_id` = '$ID' and `itemtype` = 'Ticket'
                      $RESTRICT
                ORDER BY `date` DESC";
        $result = $DB->query($query);

        $out = "";
        if ($DB->numrows($result) > 0) {
            $out .= "<div class='center'><table class='tab_cadre' width='100%'>\n
                  <tr><th>" . __('Date') . "</th><th>" . __('Requester') . "</th>
                  <th>" . __('Description') . "</th></tr>\n";

            $showuserlink = 0;
            if (Session::haveRight('user', READ)) {
                $showuserlink = 1;
            }

            while ($data = $DB->fetchAssoc($result)) {
                $out .= "<tr class='tab_bg_3'>
                     <td class='center'>" . Html::convDateTime($data["date"]) . "</td>
                     <td class='center'>" . getUserName($data["users_id"], $showuserlink) . "</td>
                     <td width='70%' class='b followup" . ($data['is_private'] ? '_private' : '') . "'>"
                . Html::resume_text($data["content"], $CFG_GLPI["cut"]) . "
                     </td></tr>";
            }

            $out .= "</table></div>";
        }

        return $out;
    }

    static function getShortForMail($id, $showprivate = false)
    {
        global $DB;
        $restrict = $showprivate ? '' : "AND `is_private` = '0'";
        $result   = $DB->query("SELECT * FROM `glpi_itilfollowups` WHERE `items_id` = '$id' and `itemtype` = 'Ticket' $restrict ORDER BY `date` DESC");
        $out      = "";
        while ($data = $DB->fetchAssoc($result)) {
            $out .= date('[d.m.Y H:i:s] ', strtotime($data["date"])) . strip_tags(PluginIserviceCommon::br2nl($data["content"])) . "\n";
        }

        return $out;
    }

    public static function getTable($classname = null)
    {
        return ITILFollowup::getTable();
    }

}

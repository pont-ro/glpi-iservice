<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceTicketFollowup extends ITILFollowup
{

    /**
     * @param $ID  integer  ID of the ticket
     **/
    static function getTicketFollowupsData($ID): array
    {
        global $DB, $CFG_GLPI;

        // Print Followups for a job
        $showprivate = Session::haveRight(self::$rightname, self::SEEPRIVATE);

        $RESTRICT = "";
        if (!$showprivate) {
            $RESTRICT = " AND (`is_private` = '0'
                            OR `users_id` ='" . Session::getLoginUserID() . "') ";
        }

        // Get Number of Followups.
        $query  = "SELECT *
                FROM `glpi_itilfollowups`
                WHERE `items_id` = '$ID' and `itemtype` = 'Ticket'
                      $RESTRICT
                ORDER BY `date` DESC";
        $result = $DB->query($query);

        $followupsData = [];

        if ($DB->numrows($result) > 0) {
            $followupsData['header'] = [
                'date' => [
                    'value' => __('Date'),
                    'class' => 'center',
                ],
                'requester' => [
                    'value' => __('Requester'),
                    'class' => 'center',
                ],
                'description' => [
                    'value' => __('Description'),
                    'class' => 'center',
                ],
            ];

            $showuserlink = 0;
            if (Session::haveRight('user', READ)) {
                $showuserlink = 1;
            }

            while ($data = $DB->fetchAssoc($result)) {
                $followupsData['rows'][] = [
                    'class' => ($data['is_private'] ? 'bg-danger' : ''),
                    'cols' => [
                        'date' => [
                            'value' => Html::convDateTime($data["date"]),
                            'class' => 'center',
                        ],
                        'requester' => [
                            'value' => getUserName($data["users_id"], $showuserlink),
                            'class' => 'center',
                        ],
                        'description' => [
                            'value' => Html::resume_text($data["content"], $CFG_GLPI["cut"]),
                            'class' => 'center',
                        ],
                    ],
                ];
            }
        }

        return $followupsData;
    }

    static function getShortForMail($id, $showprivate = false)
    {
        global $DB;
        $restrict = $showprivate ? '' : "AND `is_private` = '0'";
        $result   = $DB->query("SELECT * FROM `glpi_itilfollowups` WHERE `items_id` = '$id' and `itemtype` = 'Ticket' $restrict ORDER BY `date` DESC");
        $out      = "";
        while ($data = $DB->fetchAssoc($result)) {
            $out .= date('[d.m.Y H:i:s] ', strtotime($data["date"])) . strip_tags(IserviceToolBox::br2nl($data["content"])) . "\n";
        }

        return $out;
    }

    public static function getTable($classname = null)
    {
        return ITILFollowup::getTable();
    }

}

<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

class PluginIserviceTicketFollowup extends ITILFollowup
{

    /**
     * @param $ID  integer  ID of the ticket
     **/
    public static function getTicketFollowupsData($ID): array
    {
        global $CFG_GLPI;

        $showprivate = Session::haveRight(self::$rightname, self::SEEPRIVATE) || IserviceToolBox::inProfileArray(['admin', 'super-admin', 'tehnician', 'subtehnician']);

        $criteria = [
            'items_id' => $ID,
            'itemtype' => 'Ticket',
        ];
        if (!$showprivate) {
            $criteria['AND']['OR'] = [
                'is_private' => 0,
                'users_id' => Session::getLoginUserID(),
            ];
        }

        $result = (new self)->find(
            $criteria,
            ['order' => 'date DESC']
        );

        $followupsData = [];

        if (count($result) > 0) {
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

            foreach ($result as $data) {
                $followupsData['rows'][] = [
                    'class' => '',
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
                            'class' => 'center' . ($data['is_private'] ? ' text-danger' : ''),
                        ],
                    ],
                ];
            }
        }

        return $followupsData;
    }

    public static function getShortForMail($id, $showprivate = false)
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

<?php

// Imported from iService2, needs refactoring. Original file: "Operations.php".
class PluginIserviceView_Operations extends PluginIserviceView
{

    static function getTicketStatusDisplay($row_data)
    {
        global $CFG_GLPI;
        $actions = [
            'add' => [
                'link' => 'ticket.form.php?mode=' . PluginIserviceTicket::MODE_CREATENORMAL . "&items_id[Printer][0]=$row_data[printer_id]&_suppliers_id_assign=$row_data[supplier_id]",
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/app_add.png',
                'title' => __('New ticket'),
                'visible' => Session::haveRight('plugin_iservice_ticket_' . PluginIserviceTicket::MODE_CREATENORMAL, UPDATE),
            ],
            'add_quick' => [
                'link' => 'ticket.form.php?mode=' . PluginIserviceTicket::MODE_CREATEQUICK . "&items_id[Printer][0]=$row_data[printer_id]&_suppliers_id_assign=$row_data[supplier_id]",
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/app_lightning.png',
                'title' => __('New quick ticket', 'iservice'),
                'visible' => Session::haveRight('plugin_iservice_ticket_' . PluginIserviceTicket::MODE_CREATEQUICK, UPDATE),
            ],
            'close' => [
                'link' => 'ticket.form.php?mode=' . PluginIserviceTicket::MODE_CLOSE . "&id=$row_data[ticket_id]",
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/app_check.png',
                'title' => __('Close ticket', 'iservice'),
                'visible' => Session::haveRight('plugin_iservice_ticket_' . PluginIserviceTicket::MODE_CLOSE, UPDATE),
            ],
            'ticketreport' => [
                'link' => $CFG_GLPI['root_doc'] . "/plugins/iservice/front/ticket.report.php?id=$row_data[ticket_id]",
                'icon' => $CFG_GLPI['root_doc'] . '/plugins/iservice/pics/app_exp.png',
                'title' => __('Generate', 'iservice') . ' ' . __('intervention report', 'iservice'),
                'visible' => Session::haveRight('plugin_iservice_docgenerator', READ),
            ],
        ];
        $out     = "<div class='actions'>";
        foreach ($actions as $action) {
            if (!isset($action['visible']) || $action['visible']) {
                $out .= "<a href='$action[link]' target='_blank'><img class='noprint view_action_button' src='$action[icon]' alt='$action[title]' title='$action[title]'></a>";
            }
        }

        $out .= "&nbsp;";
        $out .= Ticket::getStatusIcon($row_data['status']);
        $out .= "</div>";
        return $out;
    }

    static function getTicketConsumablesDisplay($row_data)
    {
        $export_type = empty($row_data['export_type']) ? '' : ucfirst("$row_data[export_type]<br>");
        if ($row_data['ticket_exported']) {
            return "<b>$export_type</b>$row_data[ticket_consumables]";
        } else {
            return "<span style='color:red;' title='Exportare nefinalizată sau revocată'><b>$export_type</b>$row_data[ticket_consumables]</span>";
        }
    }

    protected function getSettings()
    {
        global $CFG_GLPI;
        $ticket_status_options = [
            '1,2,3,4,5,6' => '---',
            '1,2,3,4,5' => 'Neinchise',
        ];
        foreach (Ticket::getAllStatusArray() as $status_id => $status_name) {
            $ticket_status_options[$status_id] = $status_name;
        }

        $settings = [
            'name' => __('Operations', 'iservice'),
            'query' => "
                SELECT * FROM (
                    SELECT
                          t.status
                        , t.solvedate date_solve
                        , t.data_luc
                        , t.id ticket_id
                        , t.name ticket_name
                        , t.content ticket_content
                        , t.total2_black + coalesce(t.total2_color, 0) ticket_counter_total
                        , t.total2_black ticket_counter_black
                        , t.total2_color ticket_counter_color
                        , tcf.contact ticket_contact
                        , tcf.numar_contact ticket_contact_num
                        , tcf.observatii ticket_comment
                        , tcf.status_aparat ticket_status
                        , tcf.fara_hartii ticket_without_papers
                        , tcf.exported ticket_exported
                        , tcf.export_type
                        , l.completename ticket_location
                        , p.id printer_id
                        , s.id supplier_id
                        , s.name supplier_name
                        , GROUP_CONCAT(CONCAT('<span class=\"followup', CASE tf.is_private WHEN 1 THEN '_private' ELSE '' END, '\">', DATE_FORMAT(tf.date, '%Y-%m-%d %T'), ' - ', tf.content, '</span>') SEPARATOR '<br>') ticket_followups
                        , (SELECT GROUP_CONCAT(CONCAT(ct.plugin_iservice_consumables_id, '<br>(', TRIM(ct.amount) + 0, COALESCE(CONCAT(': ', REPLACE(ct.new_cartridge_ids, '|', '')), ''), ')') SEPARATOR '<br>') ticket_consumables
                           FROM glpi_plugin_iservice_consumables_tickets ct
                           WHERE ct.tickets_id = t.id) ticket_consumables
                        , ct.cartridges ticket_cartridges
                    FROM glpi_tickets t
                    LEFT JOIN glpi_plugin_fields_ticketcustomfields tcf ON tcf.items_id = t.id and tcf.itemtype = 'Ticket'
                    LEFT JOIN glpi_itilfollowups tf ON tf.items_id = t.id and tf.itemtype = 'Ticket'
                    LEFT JOIN glpi_items_tickets it ON it.tickets_id = t.id AND it.itemtype = 'Printer'
                    LEFT JOIN glpi_printers p ON p.id = it.items_id
                    LEFT JOIN glpi_suppliers_tickets st ON st.tickets_id = t.id AND st.type = " . CommonITILObject::ASSIGNED . "
                    LEFT JOIN glpi_suppliers s ON s.id = st.suppliers_id
                    LEFT JOIN glpi_locations l ON l.id = t.locations_id
                    -- LEFT JOIN ( SELECT cat.tickets_id, GROUP_CONCAT(CONCAT(ci.ref, '<br>(', c.id,')') SEPARATOR '<br>') cartridges
                    LEFT JOIN ( SELECT cat.tickets_id, GROUP_CONCAT(CONCAT(ci.ref, '&nbsp;<span title=\"', IF(cfc.plugin_fields_typefielddropdowns_id IN (2,3,4), t.total2_color, t.total2_black + coalesce(t.total2_color, 0)), ' + (', cfc.atcfield, ' * ', cfc.lcfield, ')', '\">(pana&nbsp;', IF(cfc.plugin_fields_typefielddropdowns_id IN (2,3,4), t.total2_color, t.total2_black + coalesce(t.total2_color, 0)) + ROUND(cfc.atcfield * cfc.lcfield), ')</span><br>[', c.id,COALESCE(CONCAT(' -> ',cat.cartridges_id_emptied),' -> <span style=\"color:red;\" title=\"nu golește nimic\">!!!</span>'),']') SEPARATOR '<br>') cartridges
                                FROM glpi_plugin_iservice_cartridges_tickets cat
                                LEFT JOIN glpi_tickets t ON t.id = cat.tickets_id
                                LEFT JOIN glpi_cartridges c ON c.id = cat.cartridges_id
                                LEFT JOIN glpi_cartridgeitems ci ON ci.id = c.cartridgeitems_id
                                LEFT JOIN glpi_plugin_fields_cartridgeitemcartridgecustomfields cfc on cfc.items_id = ci.id and cfc.itemtype = 'CartridgeItem'
                                GROUP BY cat.tickets_id
                              ) ct ON ct.tickets_id = t.id
                    WHERE t.is_deleted = 0
                        AND t.status in ([ticket_status])
                        AND CAST(t.id AS CHAR) LIKE '[ticket_id]'
                        AND t.name LIKE '[ticket_name]'
                        AND t.content LIKE '[ticket_content]'
                        AND (t.data_luc IS NULL OR t.data_luc <= '[data_luc]')
                        [printer_id]
                    GROUP BY t.id
                ) t
                WHERE 1=1
                  AND ((t.ticket_followups IS null AND '[ticket_followups]' = '%%') OR t.ticket_followups LIKE '[ticket_followups]')
                  AND ((t.ticket_consumables IS null AND '[ticket_consumables]' = '%%') OR t.ticket_consumables LIKE '[ticket_consumables]')
                  AND ((t.ticket_cartridges IS null AND '[ticket_cartridges]' = '%%') OR t.ticket_cartridges LIKE '[ticket_cartridges]')
                ",
            'default_limit' => 30,
            'filters' => [
                'filter_buttons_prefix' => " <input type='submit' class='submit noprint' name='filter' value='Toate' onclick=\"$('form').attr('action', 'http://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]?view=operations');\"/>",
                'printer_id' => [
                    'type' => self::FILTERTYPE_HIDDEN,
                    'format' => "AND p.id = %d",
                ],
                'data_luc' => [
                    'type' => self::FILTERTYPE_DATE,
                    'header' => 'data_luc',
                    'header_caption' => '< ',
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => date('Y-m-d'),
                ],
                'ticket_status' => [
                    'type' => self::FILTERTYPE_SELECT,
                    'caption' => 'Stare tichet',
                    'options' => $ticket_status_options,
                    'empty_value' => '1,2,3,4,5,6',
                    'header' => 'status',
                ],
                'ticket_id' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'Număr',
                    'format' => '%%%s%%',
                    'header' => 'ticket_id',
                ],
                'ticket_name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'Titlu',
                    'format' => '%%%s%%',
                    'header' => 'ticket_name',
                ],
                'ticket_content' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'Descriere',
                    'format' => '%%%s%%',
                    'header' => 'ticket_content',
                ],
                'ticket_followups' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'ticket_followups',
                ],
                'ticket_consumables' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'ticket_consumables',
                ],
                'ticket_cartridges' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'ticket_cartridges',
                ],
            ],
            'columns' => [
                'status' => [
                    'title' => 'Stare tichet',
                    'format' => 'function:PluginIserviceView_Operations::getTicketStatusDisplay($row);',
                    'align' => 'center',
                ],
                'data_luc' => [
                    'title' => 'Data efectivă',
                    'style' => 'white-space: nowrap;',
                    'default_sort' => 'DESC',
                ],
                'ticket_id' => [
                    'title' => 'Număr',
                ],
                'ticket_name' => [
                    'title' => 'Titlu',
                    'link' => [
                        'href' => $CFG_GLPI['root_doc'] . '/front/ticket.form.php?id=[ticket_id]',
                        'title' => "Partener: [supplier_name]\nLocație: [ticket_location]\nStatus: [ticket_status]\n\nContact: [ticket_contact]\nNumăr contact: [ticket_contact_num]\n\nFără hârtii: [ticket_without_papers]\n\nObservatii: [ticket_comment]",
                        'visible' => Session::haveRight('plugin_iservice_interface_original', READ),
                        'target' => '_blank',
                    ],
                ],
                'ticket_content' => [
                    'title' => 'Descriere',
                ],
                'ticket_followups' => [
                    'title' => 'Followupuri',
                ],
                'ticket_consumables' => [
                    'title' => 'Cartușe livrate',
                    'class' => 'no-wrap',
                    'align' => 'center',
                    'format' => 'function:default',  // this will call PluginIserviceView_Operations::getTicketConsumablesDisplay($row);
                ],
                'ticket_cartridges' => [
                    'title' => 'Cartușe instalate',
                    'align' => 'center'
                ],
                'ticket_counter_black' => [
                    'title' => 'Contor bk',
                ],
                'ticket_counter_color' => [
                    'title' => 'Contor color',
                ],
                'ticket_counter_total' => [
                    'title' => 'Contor total',
                ],
            ],
        ];

        $params  = PluginIserviceCommon::getArrayInputVariable('operations0');
        $printer = new PluginIservicePrinter();
        if ($printer->getFromDB($params['printer_id']) && $printer->isPlotter()) {
            $settings['columns']['ticket_counter_black']['title'] = __('Consumed ink', 'iservice');
            $settings['columns']['ticket_counter_color']['title'] = __('Printed surface', 'iservice');
            unset($settings['columns']['ticket_counter_total']);
        }

        return $settings;
    }

}

<?php

// Imported from iService2, needs refactoring. Original file: "Emaintenance.php".
namespace GlpiPlugin\Iservice\Views;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use GlpiPlugin\Iservice\Views\View;
use PluginIserviceEmaintenance;
use PluginIserviceHtml;
use PluginIserviceTicket;
use Session;

class Emaintenance extends View
{

    public static $rightname = 'plugin_iservice_view_emaintenance';

    public static $icon = 'ti ti-tools';

    public static function getName(): string
    {
        return _t('E-Maintenance');
    }

    public static function getIDDisplay($row_data)
    {
        global $CFG_PLUGIN_ISERVICE;

        $operations_filter_description = urlencode("$row_data[printer_name] ($row_data[printer_serial]) - $row_data[printer_usageaddress] - $row_data[supplier_name]");
        $buttons_config                = [
            '_wrapper' => [
                'id' => "actions_$row_data[id]",
            ],
            'ticket' => self::getTicketButton($row_data),
            'list_ticket' => [
                'link' => "views.php?view=Operations&operations0[printer_id]=$row_data[printers_id]&operations0[filter_description]=$operations_filter_description",
                'icon' => "$CFG_PLUGIN_ISERVICE[root_doc]/pics/app_detail.png",
                'title' => _t('Operations list'),
                'visible' => Session::haveRight('plugin_iservice_view_operations', READ),
            ],
            'invalidate' => [
                'link' => "$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageEMMail.php?id=$row_data[id]&operation=invalidate",
                'onclick' => 'ajaxCall',
                'confirm' => "",
                'success' => 'function(message) {if(message !== "' . IserviceToolBox::RESPONSE_OK . '") {alert(message);} else {$("#actions_' . $row_data['id'] . '").closest("tr").css("background", "red");}}',
                'html' => "<i class='fa fa-file fa-2x pointer' style='color:red; vertical-align: middle;' title='Invalidează'></i>",
            ],
            'body' => self::getBodyDisplay($row_data),
            'cartridge' => [
                'link' => "$CFG_PLUGIN_ISERVICE[root_doc]/ajax/getPrinterCartridgesPopup.php?supplier_id=$row_data[suppliers_id]&supplier_name=" . urlencode($row_data['supplier_name']) . "&printer_id=$row_data[printers_id]&uid=$row_data[id]",
                'icon' => "$CFG_PLUGIN_ISERVICE[root_doc]/pics/toolbox.png",
                'title' => _t('Installable cartridges'),
                'visible' => Session::haveRight('plugin_iservice_view_cartridges', READ),
                'onclick' => "ajaxCall",
                'success' => "function(message) {\$(\"#popup_$row_data[printers_id]_$row_data[id]\").html(message);}",
                'suffix' => "<div class='iservice-view-popup' id='popup_$row_data[printers_id]_$row_data[id]'></div>",
            ],
        ];

        return self::getIDDisplayLink($row_data['id'], true, $row_data['read']) . self::getIDDisplayLink($row_data['id'], false, !$row_data['read']) . ' ' . self::getActionButtons($buttons_config, true);
    }

    protected static function getIDDisplayLink($id, $read, $visible)
    {
        global $CFG_PLUGIN_ISERVICE;
        if ($read) {
            $dom_id    = "eme_read_$id";
            $title     = 'Citit, marchează necitit';
            $color     = 'lightgrey';
            $operation = 'mark_unread';
        } else {
            $dom_id    = "eme_unread_$id";
            $title     = 'Necitit, marchează citit';
            $color     = 'black';
            $operation = 'mark_read';
        }

        if ($visible) {
            $display = 'inline';
        } else {
            $display = 'none';
        }

        $success_function = 'function(message) {if(message !== "' . IserviceToolBox::RESPONSE_OK . '") {alert(message);} else {$("#eme_read_' . $id . '").toggle();$("#eme_unread_' . $id . '").toggle();}}';
        $displayed_id     = str_pad($id, 6, 0, STR_PAD_LEFT);
        return "<a id='$dom_id' href='#' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageEMMail.php?id=$id&operation=$operation\", \"\", $success_function); return false;'  style='display: $display;' title='$title'>$displayed_id <i class='fa fa-file fa-2x' style='color: $color; vertical-align: middle;'></i></a>";
    }

    static function getSubjectDisplay($row_data)
    {
        return "<span title='$row_data[subject]'>" . self::getSubjectForDisplay($row_data) . "</span>";
    }

    static function getBodyDisplay($row_data)
    {
        return "<i class='fa fa-file-text-o fa-2x' style='vertical-align: middle;' title='$row_data[body]'></i> " . self::getEMLink($row_data);
    }

    static function getSuggestedDisplay($row_data)
    {
        $display = '';

        if (!empty($row_data['suggested'])) {
            $em      = new \PluginIserviceEMEmail();
            $printer = PluginIserviceEmaintenance::getPrinterFromEmailData($row_data);
            $infocom = new \Infocom();
            if (!empty($printer)) {
                $infocom->getFromDBforDevice('Printer', $printer->getID());
            }

            $em->update(
                [
                    'id' => $row_data['id'],
                    'printers_id' => empty($printer) ? 'null' : $printer->getID(),
                    'suppliers_id' => $infocom->isNewItem() ? 'null' : $infocom->fields['suppliers_id'],
                    'users_id_tech' => empty($printer) ? 'null' : $printer->fields['users_id_tech'],
                    'suggested' => '',
                ]
            );
            return "<input class='submit fa fa-exclamation-triagle' name='filter' type='submit' value='Invalid data detected, prease refresh' />";
        }

        switch (self::getSubjectForDisplay($row_data)) {
        case 'Communication Test':
            return "<i class='fa fa-exclamation-triangle' style='color:orange'></i> Communication Test detected, don't know what to do";
        case 'Jam Notification':
        case 'Alarm Notification':
        case 'Error Notification':
        case 'Toner-Related Notification':
        case 'Full Toner Waste Box Notification':
        case 'Device Installation Incompleted Notification':
        case 'Toner Replacement Notification':
        default:
            $display .= "<div class='emaintenance-suggestion' title='$row_data[body]'>" . self::getSuggestionText($row_data) . "</div>";
        }

        if (!empty($row_data['process_result'])) {
            $process_restults       = explode('###', $row_data['process_result']);
            $process_result         = $process_restults[0];
            $process_result_details = count($process_restults) > 1 ? "<i class='fa fa-caret-down pointer' onclick='$(this).next().toggle();'></i><div onclick='$(this).hide();' style='display:none'>$process_restults[1]</div>" : '';
            $display               .= "<div class='emaintenance-suggestion-error'><i class='fa fa-exclamation-circle' style='color:red'></i> $process_result $process_result_details</div>";
        }

        return $display;
    }

    protected static function getTicketButton($row_data)
    {
        global $CFG_PLUGIN_ISERVICE;

        if (!empty($row_data['ticket_id'])) {
            $ticket_button = "<a href='ticket.form.php?id=$row_data[ticket_id]' style='vertical-align: middle;' target='_blank' title='" . _t('Close ticket') . "' /><img src='$CFG_PLUGIN_ISERVICE[root_doc]/pics/app_check.png' style='vertical-align: middle;'/></a>";
        } elseif (empty($row_data['printers_id'])) {
            $ticket_button = "<i class='fa fa-exclamation-circle fa-2x' style='color:red; vertical-align: middle;' title='" . _t('Cannot identify printer from email') . "'></i>";
        } else {
            $name          = self::getSubjectForDisplay($row_data);
            $content       = urlencode(self::getContentForTicket($row_data));
            $ticket_button = "<a href='ticket.form.php?items_id[Printer][0]=$row_data[printers_id]&name=$name&content=$content&em_mail_id_field=$row_data[id]&effective_date_field=$row_data[date]' style='vertical-align: middle;' target='_blank' title='" . _t('New quick ticket') . "' /><img src='$CFG_PLUGIN_ISERVICE[root_doc]/pics/app_lightning.png' style='vertical-align: middle;'/></a>";
        }

        return $ticket_button;
    }

    protected static function getSubjectForDisplay($row_data)
    {
        return trim(explode('/', $row_data['subject'])[0]);
    }

    protected static function getContentForTicket($row_data)
    {
        return self::getSuggestionText($row_data, false);
    }

    protected static function getEMLink($row_data)
    {
        $extended_data = PluginIserviceEmaintenance::getExtendedMailData($row_data);
        if (empty($extended_data['body_lines']['link'])) {
            return "";
        }

        return "<a href='{$extended_data['body_lines']['link']}' target='_blank' title ='" . _t('Goto link from email') . "'><i class='fa fa-link fa-2x' style='vertical-align: middle;'></i></a>";
    }

    protected static function getSuggestionText($row_data, $html_format = true)
    {
        return PluginIserviceEmaintenance::getContentForTicket($row_data, $html_format);
    }

    public static function getSerialDisplay($row_data): ?string
    {
        if (!Session::haveRight('plugin_iservice_interface_original', READ)) {
            return $row_data['printer_serial'];
        }

        $link = "<a href='printer.form.php?id=$row_data[printers_id]' title='" . _t('Manage printer') . "'>$row_data[printer_serial]</a>";
        if (isset($row_data['printer_gps']) && !empty($row_data['printer_gps'])) {
            $link = "<span style='color:blue;'><i>$link</i></span>";
        }

        $copyLink = IserviceToolBox::getSerialCopyButton($row_data['printer_serial']);
        return $link . ' ' . $copyLink;
    }

    protected function getSettings(): array
    {
        global $CFG_GLPI;
        global $CFG_PLUGIN_ISERVICE;

        $max_emails       = IserviceToolBox::getInputVariable('max_emails', 50);
        $mailcollector_id = IserviceToolBox::getInputVariable('id', PluginIserviceEmaintenance::getMailCollector()['id'] ?? null);

        ob_start();
        $html = new PluginIserviceHtml();
        $html->openForm(
            [
                'action' => '/plugins/iservice/front/emaintenance.form.php',
                'method' => 'post',
                'class' => 'iservice-form',
            ]
        );
        $html->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'id', $mailcollector_id);
        echo __('Number of emails to retrieve'), " ";
        $html->displayField(PluginIserviceHtml::FIELDTYPE_TEXT, 'max_emails', $max_emails, false, ['style' => 'width:40px;']);
        echo ' ';
        $html->displaySubmit('get_mails', _t('Get E-maintenance emails'));
        echo " <a href='javascript:none' onclick='$(\"#auto-process-rules\").toggle();'>Reguli de procesare ▼</a>";
        echo "<pre id='auto-process-rules' style='display: none;'>" . print_r(PluginIserviceEmaintenance::getAutoProcessRules(), true) . "</pre>";
        echo "</table>";
        $html->closeForm();
        $prefix = ob_get_clean();

        return [
            'name' => _t('Processed mail list'),
            'prefix' => $prefix,
            'query' => "
                        SELECT 
                            eme.*
                          , tc.items_id ticket_id
                          , p.name printer_name
                          , p.serial printer_serial
                          , p.usage_address_field printer_usageaddress
                          , p.cost_center_field costcenter
                          , s.name supplier_name
                          , CONCAT(IFNULL(CONCAT(u.realname, ' '),''), IFNULL(u.firstname, '')) tech_name
                        FROM glpi_plugin_iservice_ememails eme
                        LEFT JOIN glpi_plugin_fields_ticketticketcustomfields tc on tc.em_mail_id_field = eme.id and tc.itemtype = 'Ticket'
                        LEFT JOIN glpi_plugin_iservice_printers p on p.id = eme.printers_id
                        LEFT JOIN glpi_suppliers s ON s.id = eme.suppliers_id
                        LEFT JOIN glpi_users u ON u.id = eme.users_id_tech
                        WHERE eme.read in ([read])
                          AND eme.id LIKE '[id]'
                          AND eme.date <= '[date]'
                          AND eme.subject LIKE '[subject]'
                          AND eme.body LIKE '[body]'
                          AND ((p.name is null AND '[printer_name]' = '%%') OR p.name LIKE '[printer_name]')
                          AND ((s.name is null AND '[supplier_name]' = '%%') OR s.name LIKE '[supplier_name]')
                          AND ((p.serial is null AND '[printer_serial]' = '%%') OR p.serial LIKE '[printer_serial]')
                          AND ((p.cost_center_field is null AND '[costcenter]' = '%%') OR p.cost_center_field like '[costcenter]')
                          [tech_id]
                        ",
            'show_filter_buttons' => 'false',
            'default_limit' => 30,
            'filters' => [
                'id' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'empty_format' => '%%%s%%',
                    'header' => 'id',
                    'style' => 'width: 3em;'
                ],
                'read' => [
                    'type' => self::FILTERTYPE_SELECT,
                    'caption' => 'Stare',
                    'options' => ['0,1' => '---', '0' => 'necitite', '1' => 'citite'],
                    'empty_value' => '0,1',
                    'zero_is_empty' => false,
                    'header' => 'id',
                    'no_break_before' => true,
                    'post_widget' => '<script>$("select[name=\'emaintenance0[read]\']").closest(".dropdown_wrapper").removeClass("full");</script>',
                ],
                'date' => [
                    'type' => self::FILTERTYPE_DATE,
                    'caption' => 'Data email <',
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => date('Y-m-d'),
                    'header' => 'date',
                    'header_caption' => '< ',
                ],
                'printer_name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'empty_format' => '%%%s%%',
                    'header' => 'printer_name',
                ],
                'supplier_name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'empty_format' => '%%%s%%',
                    'header' => 'supplier_name',
                ],
                'printer_serial' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'empty_format' => '%%%s%%',
                    'header' => 'printer_serial',
                ],
                'costcenter' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'header' => 'costcenter'
                ],
                'tech_id' => [
                    'type' => self::FILTERTYPE_SELECT,
                    'caption' => 'Responsabil',
                    'format' => 'AND u.id = %d',
                    'header' => 'tech_name',
                    'options' => IserviceToolBox::getUsersByProfiles(['tehnician']),
                ],
                'subject' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'empty_format' => '%%%s%%',
                    'header' => 'subject',
                ],
                'body' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'format' => '%%%s%%',
                    'empty_format' => '%%%s%%',
                    'header' => 'suggested',
                ],
            ],
            'columns' => [
                'id' => [
                    'title' => 'ID',
                    'format' => 'function:default',
                    'style' => 'white-space: nowrap; width: 8em;',
                    'default_sort' => 'desc',
                    'align' => 'center',
                ],
                'date' => [
                    'title' => 'Data',
                ],
                'printer_name' => [
                    'title' => 'Nume aparat',
                    'format' => '%s',
                    'link' => [
                        'href' => "$CFG_GLPI[root_doc]/front/printer.form.php?id=[printers_id]",
                        'target' => '_blank',
                        'visible' => Session::haveRight('plugin_iservice_interface_original', READ),
                    ]
                ],
                'supplier_name' => [
                    'title' => 'Partener',
                    'format' => '%s',
                    'link' => [
                        'href' => "$CFG_GLPI[root_doc]/front/supplier.form.php?id=[suppliers_id]",
                        'target' => '_blank',
                        'visible' => Session::haveRight('plugin_iservice_interface_original', READ),
                    ]
                ],
                'printer_serial' => [
                    'title' => 'Serie',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\Emaintenance::getSerialDisplay($row);',
                ],
                'costcenter' => [
                    'title' => 'Centru de cost'
                ],
                'tech_name' => [
                    'title' => 'Responsabil',
                ],
                /**/
                'subject' => [
                    'title' => 'Titlu e-mail',
                    'format' => 'function:default',
                ],
                /**
                  'body' => [
                  'title' => 'Conținut e-mail',
                  'format' => 'function:default',
                  'align' => 'center',
                  ],
                  /*
*/
                'suggested' => [
                    'title' => 'Interpretare e-mail',
                    'format' => 'function:default',
                ]
            ],
        ];
    }

}

<?php
class PluginIserviceView_Pending_Emails extends PluginIserviceView
{

    protected function getInvoiceDisplay($row_data)
    {
        global $CFG_PLUGIN_ISERVICE;
        $title          = $row_data['attachment'];
        $invoice        = $row_data['invoice'] ?: '---';
        $ajax_call      = "ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/managePrinter.php?operation=get_last_invoices_dropdown&id=$row_data[pid]&selected=$row_data[invoice]-$row_data[attachment]&limit=3\", \"\", function(message) {\$(\"#invoice-select-$row_data[id]\").html(message);\$(\"#invoice-link-$row_data[id], #invoice-span-$row_data[id]\").toggle();});";
        $ajax_call2     = "ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/managePendingEmail.php?operation=update_invoice&id=$row_data[id]&invoice_data=\" + \$(\"#last-invoices-$row_data[pid]\").children(\"option:selected\").val(), \"\", function(message) {if(message !== \"" . PluginIserviceCommon::RESPONSE_OK . "\") {alert(message);} else {\$(\"#invoice-link-link-$row_data[id]\").html(\$(\"#last-invoices-$row_data[pid]\").children(\"option:selected\").val().split(\"-\", 1)[0]);\$(\"#invoice-link-link-$row_data[id]\").prop(\"title\", \$(\"#last-invoices-$row_data[pid]\").children(\"option:selected\").val().split(\"-\", 2)[1]);\$(\"#invoice-link-$row_data[id], #invoice-span-$row_data[id]\").toggle();\$(\"#invoice-link-delete-$row_data[id]\").show();}});";
        $ajax_call3     = "ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/managePendingEmail.php?operation=remove_invoice&id=$row_data[id]\", \"Sigur vreti sa stergeti factura asociata acestui mail?\", function(message) {if(message !== \"" . PluginIserviceCommon::RESPONSE_OK . "\") {alert(message);} else {\$(\"#invoice-link-link-$row_data[id]\").html(\"---\");\$(\"#invoice-link-delete-$row_data[id]\").hide();}});";
        $onclick_cancel = "\$(\"#invoice-select-$row_data[id]\").html(\"\");\$(\"#invoice-link-$row_data[id], #invoice-span-$row_data[id]\").toggle();";
        $delete_display = $row_data['invoice'] ? '' : 'display: none;';
        $link           = "<span id='invoice-link-$row_data[id]'><a id='invoice-link-link-$row_data[id]' href='javascript:void(0);' onclick='$ajax_call' title='$title'>$invoice</a> <a id='invoice-link-delete-$row_data[id]' class='fa fa-times-circle' href='javascript:void(0);' onclick='$ajax_call3' style='color: red;$delete_display'></a></span>";
        return "$link<span id='invoice-span-$row_data[id]' style='display: none;'><span id='invoice-select-$row_data[id]'></span> <a class='fa fa-check-circle' href='javascript:void(0);' onclick='$ajax_call2' style='color: green'></a> <a class='fa fa-times-circle' href='javascript:void(0);' onclick='$onclick_cancel' style='color: red'></a></span>";
    }

    static function getBodyDisplay($row_data)
    {
        global $CFG_PLUGIN_ISERVICE;
        $ajax_call = "ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/managePendingEmail.php?operation=update_body&id=$row_data[id]&body=\" + encodeURIComponent($(\"#body-edit-$row_data[id]\").val()), \"\", function(message) {if(message !== \"" . PluginIserviceCommon::RESPONSE_OK . "\") {alert(message);} else {\$(\"#body-edit-span-$row_data[id]\").toggle(); \$(\"#body-image-$row_data[id]\").attr(\"title\", $(\"#body-edit-$row_data[id]\").val());}});";
        $onclick   = "\$(\"#body-edit-span-$row_data[id]\").toggle();";
        $image     = "<img id='body-image-$row_data[id]' src='$CFG_PLUGIN_ISERVICE[root_doc]/pics/mail.png' onclick='$onclick' title='" . htmlentities(urldecode($row_data['body']), ENT_QUOTES) . "' />";
        return "<span style='position: relative;'>$image<span id='body-edit-span-$row_data[id]' style='display: none; position: absolute; right: 50%; top: 0; z-index: 1;'><textarea id='body-edit-$row_data[id]' cols='80' rows='10'> " . urldecode($row_data['body']) . "</textarea> <span style='position: absolute; right: 25px; top: 5px;'><a class='fa fa-check-circle' href='javascript:void(0);' onclick='$ajax_call' style='color: green'></a> <a class='fa fa-times-circle' href='javascript:void(0);' onclick='$onclick' style='color: red'></a></span></span></span>";
    }

    protected function getSettings()
    {
        global $CFG_PLUGIN_ISERVICE;
        if (PluginIserviceCommon::getInputVariable('mass_action_send_email')) {
            $result = [
                'ignored' => 0,
                'error'   => 0,
                'sent'    => 0,
            ];
            foreach (array_keys(PluginIserviceCommon::getArrayInputVariable('item', [])['pending_emails'] ?? []) as $item) {
                global $CFG_GLPI;

                $pending_email = new PluginIservicePendingEmail();
                if (!$pending_email->getFromDB($item) || empty($pending_email->fields['attachment']) || !file_exists(PluginIservicePendingEmailUpdater::getInvoiceSearchFolder() . DIRECTORY_SEPARATOR . $pending_email->fields['attachment'])) {
                    $result['ignored']++;
                    continue;
                }

                $mmail = new GLPIMailer();

                $mmail->AddCustomHeader("Auto-Submitted: auto-generated");
                $mmail->AddCustomHeader("X-Auto-Response-Suppress: OOF, DR, NDR, RN, NRN");

                $mmail->SetFrom($CFG_GLPI["admin_email"], $CFG_GLPI["admin_email_name"], false);

                foreach (preg_split("/(,|;)/", $pending_email->fields['mail_to']) as $to_address) {
                    $mmail->AddAddress(trim($to_address));
                }

                $mmail->addBCC('financiar@expertline.ro');

                $mmail->Subject = "[iService] " . $pending_email->fields['subject'];

                $mmail->Body = urldecode($pending_email->fields['body']) . "\n\n--\n$CFG_GLPI[mailing_signature]";

                $mmail->addAttachment(PluginIservicePendingEmailUpdater::getInvoiceSearchFolder() . DIRECTORY_SEPARATOR . $pending_email->fields['attachment']);

                if (!$mmail->Send()) {
                    $result['error']++;
                } else {
                    $result['sent']++;
                    $pending_email->delete(['id' => $pending_email->fields['id']]);
                }
            };

            Session::addMessageAfterRedirect(sprintf(__('%d emails were sent', 'iservice'), $result['sent']), true);
            if ($result['error']) {
                Session::addMessageAfterRedirect(sprintf(__('%d emails were not sent', 'iservice'), $result['error']), true, ERROR);
            }
        } elseif (PluginIserviceCommon::getInputVariable('mass_action_delete')) {
            global $DB;
            $ids_to_delete = implode(',', array_keys(PluginIserviceCommon::getArrayInputVariable('item', [])['pending_emails'] ?? []));
            $DB->query("delete from glpi_plugin_iservice_pendingemails where id in ({$ids_to_delete})");
        }

        return [
            'name' => __('Pending emails', 'iservice') . " - " . PluginIserviceHmarfa::getNextImportText(),
            'query' => "
                select
                      pem.id 
                    , pem.printers_id pid
                    , pem.refresh_time 
                    , pem.invoice
                    , pem.mail_to
                    , pem.subject
                    , pem.body 
                    , pem.attachment 
                    , p.serial
                    , s.name partner_name
                    , cfs.cod_hmarfa
                from glpi_plugin_iservice_pendingemails pem
                left join glpi_plugin_iservice_printers p on p.id = pem.printers_id
                left join glpi_infocoms ic on ic.items_id = p.id and ic.itemtype ='Printer'
                left join glpi_suppliers s on s.id = ic.suppliers_id 
                left join glpi_plugin_fields_suppliercustomfields cfs on cfs.items_id = s.id and cfs.itemtype = 'Supplier'
                ",
            'mass_actions' => [
                'send_email' => [
                    'caption' => 'Trimite email(uri)',
                    'action' => 'view.php?view=pending_emails',
                ],
                'delete' => [
                    'caption' => 'Șterge definitiv',
                    'action' => 'view.php?view=pending_emails',
                ],
            ],

            'columns' => [
                'refresh_time' => [
                    'title' => 'Reîmprospătare'
                ],
                'partner_name' => [
                    'title' => 'Partener',
                    'link' => [
                        'href' => $CFG_PLUGIN_ISERVICE['root_doc'] . '/front/views.php?view=unpaid_invoices&unpaid_invoices0[cod]=[cod_hmarfa]',
                        'target' => '_blank',
                    ],
                ],
                'mail_to' => [
                    'title' => 'Email destinație',
                    'editable' => true,
                    'edit_settings' => [
                        'callback' => 'managePendingEmail',
                        'operation' => 'update_mail_to'
                    ]
                ],
                'serial' => [
                    'title' => 'Serie aparat'
                ],
                'invoice' => [
                    'title' => 'Factura atașată',
                    'align' => 'center',
                    'format' => 'function:default', // this will call PluginIserviceView_Pending_Emails::getInvoiceDisplay($row);
                ],
                'subject' => [
                    'title' => 'Subiect e-mail'
                ],
                'body' => [
                    'title' => 'Conținut e-mail',
                    'align' => 'center',
                    'format' => 'function:default', // this will call PluginIserviceView_Pending_Emails::getBodyDisplay($row);
                ],
            ]
        ];
    }

}

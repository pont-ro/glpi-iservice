<?php
class PluginIserviceMonthlyPlan extends CommonGLPI
{

    /**
     * Right name used to check rights to do actions on item
     *
     * @var string
     */
    public static $rightname = 'plugin_iservice_monthly_plan';

    public static function getMenuName(): string
    {
        return __('Monthly plan', 'iservice');
    }

    public static function getAdditionalMenuOptions()
    {
        return [
            'sortOrder' => 110,
        ];
    }

    public static function getMenuContent(): array
    {
        if (!Session::haveRight(self::$rightname, READ)) {
            return [];
        }

        global $CFG_PLUGIN_ISERVICE;

        return [
            'title' => self::getMenuName(),
            'page'  => "$CFG_PLUGIN_ISERVICE[root_doc]/front/monthlyplan.php",
            'icon'  => 'ti ti-calendar-event',
            'options' => static::getAdditionalMenuOptions(),
        ];
    }

    // Imported from iService2, needs refactoring. Code moved from: "planlunar.php".
    public function display($options = []): void
    {
        $year = filter_input(INPUT_GET, 'year');
        if ($year === null) {
            $year = filter_input(INPUT_POST, 'year');
            if ($year === null) {
                $year = date('Y');
            }
        }

        $month = filter_input(INPUT_GET, 'month');
        if ($month === null) {
            $month = filter_input(INPUT_POST, 'month');
            if ($month === null) {
                $month = date('m');
            }
        }

        $tech_id = filter_input(INPUT_GET, 'tech_id');
        if ($tech_id === null) {
            $tech_id = filter_input(INPUT_POST, 'tech_id');
        }

        if (empty($tech_id)) {
            $tech_filter = '';
        } else {
            $tech_filter = "AND p.users_id_tech = $tech_id";
        }

        $DB    = new DB;
        $query = "
            SELECT
                e.id enterprise_id,
                e.name enterprise_name,
                e.phonenumber enterprise_tel,
                e.fax enterprise_fax,
                e.comment enterprise_comment,
                e.email_for_invoices_field enterprise_email_facturi,
                e.hmarfa_code_field,
                p.id printer_id,
                p.otherserial,
                p.name printer_name,
                p.contact_num printer_contact_num,
                p.contact printer_contact,
                p.comment printer_comment,
                CONCAT(COALESCE(CONCAT(u.realname, ' '), ''), COALESCE(u.firstname,'')) tech_name,
                s.name state,
                p.week_nr_field,
                p.plan_observations_field,
                p.invoice_date_field,
                p.em_field,
                htf.numar_facturi,
                htf.total_facturi,
                putc.ticket_count as ticket_count_by_item,
                t.effective_date_field last_closed_ticket_close_date,
                count(m.id) as movements_count
            FROM glpi_plugin_iservice_printers p
            JOIN glpi_infocoms i on i.items_id = p.id and i.itemtype = 'Printer'
            JOIN glpi_plugin_iservice_suppliers e on e.id = i.suppliers_id
            LEFT JOIN hmarfa_total_facturi htf on htf.codbenef = e.hmarfa_code_field
            LEFT JOIN glpi_users u on u.ID = p.users_id_tech
            LEFT JOIN glpi_states s on s.ID = p.states_id
            LEFT JOIN glpi_plugin_iservice_printer_unclosed_ticket_counts putc ON putc.printers_id = p.id
            LEFT JOIN glpi_plugin_iservice_printers_last_closed_tickets plt ON plt.printers_id = p.id
            LEFT JOIN glpi_plugin_iservice_tickets t on t.id = plt.tickets_id
            LEFT JOIN glpi_plugin_iservice_movements m ON (
                (m.suppliers_id = e.id OR m.suppliers_id_old = e.id)
                AND init_date >= DATE_SUB(CURDATE(), INTERVAL 40 DAY)
            )
            WHERE p.week_nr_field > 0 and p.is_deleted = 0 $tech_filter
            GROUP BY p.id
            ORDER BY e.name
            ";

        $result = $DB->query($query);
        if (!$result) {
            echo $DB->error();
            die();
        }

        $data = [];

        while (($row = $DB->fetchAssoc($result)) !== null) {
            if ($row['week_nr_field'] > 0) {
                for ($i = 1; $i < 5; $i++) {
                    if (isset($data[$i][$row['enterprise_id']])) {
                        if ($i < $row['week_nr_field']) {
                            $row['week_nr_field']                                        = $i;
                            $data[$row['week_nr_field']][$row['enterprise_id']]['moved'] = true;
                        } elseif ($i > $row['week_nr_field']) {
                            $data[$row['week_nr_field']][$row['enterprise_id']] = $data[$i][$row['enterprise_id']];
                            unset($data[$i][$row['enterprise_id']]);
                            $data[$row['week_nr_field']][$row['enterprise_id']]['moved'] = true;
                        }

                        break;
                    }
                }
            }

            $data[$row['week_nr_field']][$row['enterprise_id']]['enterprise_name']          = $row['enterprise_name'];
            $data[$row['week_nr_field']][$row['enterprise_id']]['numar_facturi']            = $row['numar_facturi'];
            $data[$row['week_nr_field']][$row['enterprise_id']]['total_facturi']            = $row['total_facturi'];
            $data[$row['week_nr_field']][$row['enterprise_id']]['hmarfa_code_field']        = $row['hmarfa_code_field'];
            $data[$row['week_nr_field']][$row['enterprise_id']]['enterprise_tel']           = $row['enterprise_tel'];
            $data[$row['week_nr_field']][$row['enterprise_id']]['enterprise_fax']           = $row['enterprise_fax'];
            $data[$row['week_nr_field']][$row['enterprise_id']]['enterprise_comment']       = $row['enterprise_comment'];
            $data[$row['week_nr_field']][$row['enterprise_id']]['enterprise_email_facturi'] = $row['enterprise_email_facturi'];
            $data[$row['week_nr_field']][$row['enterprise_id']]['movements_count']          = $row['movements_count'];
            if (!isset($data[$row['week_nr_field']][$row['enterprise_id']]['open_tickets_count'])) {
                $data[$row['week_nr_field']][$row['enterprise_id']]['open_tickets_count'] = 0;
            }

            $data[$row['week_nr_field']][$row['enterprise_id']]['open_tickets_count'] += $row['ticket_count_by_item'];

            if (stripos($row['state'], 'Proiect') !== 0) {
                $row['state'] = 'Altele';
            }

            $data[$row['week_nr_field']][$row['enterprise_id']]['techs'][$row['tech_name']][$row['state']]['printers'][$row['printer_id']]['otherserial']                   = $row['otherserial'];
            $data[$row['week_nr_field']][$row['enterprise_id']]['techs'][$row['tech_name']][$row['state']]['printers'][$row['printer_id']]['plan_observations_field']       = $row['plan_observations_field'];
            $data[$row['week_nr_field']][$row['enterprise_id']]['techs'][$row['tech_name']][$row['state']]['printers'][$row['printer_id']]['invoice_date_field']            = $row['invoice_date_field'];
            $data[$row['week_nr_field']][$row['enterprise_id']]['techs'][$row['tech_name']][$row['state']]['printers'][$row['printer_id']]['em_field']                      = $row['em_field'];
            $data[$row['week_nr_field']][$row['enterprise_id']]['techs'][$row['tech_name']][$row['state']]['printers'][$row['printer_id']]['open_tickets_count']            = $row['ticket_count_by_item'];
            $data[$row['week_nr_field']][$row['enterprise_id']]['techs'][$row['tech_name']][$row['state']]['printers'][$row['printer_id']]['printer_name']                  = $row['printer_name'];
            $data[$row['week_nr_field']][$row['enterprise_id']]['techs'][$row['tech_name']][$row['state']]['printers'][$row['printer_id']]['printer_contact']               = $row['printer_contact'];
            $data[$row['week_nr_field']][$row['enterprise_id']]['techs'][$row['tech_name']][$row['state']]['printers'][$row['printer_id']]['printer_contact_num']           = $row['printer_contact_num'];
            $data[$row['week_nr_field']][$row['enterprise_id']]['techs'][$row['tech_name']][$row['state']]['printers'][$row['printer_id']]['printer_comment']               = $row['printer_comment'];
            $data[$row['week_nr_field']][$row['enterprise_id']]['techs'][$row['tech_name']][$row['state']]['printers'][$row['printer_id']]['last_closed_ticket_close_date'] = $row['last_closed_ticket_close_date'];
        }

        $form = new PluginIserviceHtml();

        $form->openForm(['method' => 'post', 'class' => 'iservice-form']);
        echo __('Technician') . ': <div class="dropdown_wrapper fixed-15">';
        User::dropdown(['name' => 'tech_id', 'value' => $tech_id, 'right' => 'interface']);
        echo '</div>&nbsp;&nbsp;&nbsp;&nbsp';
        echo __('Year', 'iservice') . ": <input type='text' id='year' name='year' value='$year' style='width: 4em; text-align: center;'>&nbsp;&nbsp;&nbsp;&nbsp;";
        echo __('Month', 'iservice') . ": <input type='text' id='month' name='month' value='$month' style='width: 2em; text-align: center;'>&nbsp;&nbsp;&nbsp;&nbsp;";
        echo "<input type='submit' class='submit' value='" . __('Search') . "'>&nbsp;&nbsp;&nbsp;&nbsp;";
        echo "<input type='submit' class='submit' value='" . __('This month', 'iservice') . "' onclick='return setPreviousMonth(0);'>&nbsp;&nbsp;&nbsp;&nbsp;";
        echo "<input type='submit' class='submit' value='" . __('Last month', 'iservice') . "' onclick='return setPreviousMonth(1);'>&nbsp;&nbsp;&nbsp;&nbsp;";
        echo "<input type='submit' class='submit' value='" . __('2 month ago', 'iservice') . "' onclick='return setPreviousMonth(2);'>";
        $form->closeForm();
        ?>
<script>
    function setPreviousMonth(month) {
        var currentMonth = new Date().getMonth() + 1;
        if (currentMonth > month) {
            document.getElementById('month').value = currentMonth - month;
            document.getElementById('year').value = new Date().getFullYear();
            return true;
        } else {
            document.getElementById('month').value = 12 - (month - currentMonth);
            document.getElementById('year').value = new Date().getFullYear() - 1;
            return true;
        }
    }
</script>

<table id="monthlyplan" class="tab_cadre wide">
    <tbody>
    <tr>
        <?php for ($column = 1; $column < 5; $column++) { ?>
            <td style="padding:0; vertical-align:top; width:25%;">
                <?php $form->openForm(['method' => 'post', 'action' => 'views.php?view=GlobalReadcounter', 'class' => 'iservice-form']); ?>
                <table>
                    <thead>
                    <tr>
                        <?php
                        echo "<th colspan=5>";
                        echo $form->generateSubmit('mass_action_group_read', __('Global read counter', 'iservice') . " " . __('Week', 'iservice') . " $column ");
                        echo "</th>";
                        ?>
                    </tr>
                    <tr>
                        <th style="width:10%; min-width: 32px;"></th>
                        <th><?php echo __('Supplier') . ' (' . __('Comments') . ') - ' . __('Inventory number'); ?></th>
                        <th style="width:10%; min-width: 60px;"><?php echo __('Open tickets', 'iservice'); ?></th>
                        <th style="width:10%; min-width: 60px;"><?php echo __('Unpaid invoices', 'iservice'); ?></th>
                        <th style="width:10%; min-width: 60px;"><?php echo __('Total unpaid', 'iservice'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $row_num = 0;
                    if (empty($data) || !isset($data[$column])) {
                        continue;
                    }

                    foreach ($data[$column] as $enterprise_id => $enterprise) {
                        if (empty($enterprise['techs'])) {
                            continue;
                        }

                        foreach ($enterprise['techs'] as $tech_name => $tech) {
                            foreach ($tech as $state_name => $state) {
                                $printers = [];
                                if (empty($tech_filter)) {
                                    $printer_data = [];
                                    if (count($state['printers']) > 1) {
                                        $printer_data['multiple'] = count($state['printers']);
                                    }

                                    $printer_data['all_in_em'] = true;
                                    foreach ($state['printers'] as $printer_id => $printer) {
                                        $printer_data['all_in_em'] &= $printer['em_field'];

                                        if (!isset($printer_data['invoice_date_field']) || $printer['invoice_date_field'] < $printer_data['invoice_date_field']) {
                                            $printer_data['invoice_date_field'] = $printer['invoice_date_field'];
                                        }

                                        if (!isset($printer_data['data_fact_max']) || $printer['invoice_date_field'] > $printer_data['data_fact_max']) {
                                            $printer_data['data_fact_max'] = $printer['invoice_date_field'];
                                        }

                                        if (!isset($printer_data['last_closed_ticket_close_date']) || $printer['last_closed_ticket_close_date'] < $printer_data['last_closed_ticket_close_date']) {
                                            $printer_data['last_closed_ticket_close_date'] = $printer['last_closed_ticket_close_date'];
                                        }

                                        if (!isset($printer_data['last_closed_ticket_close_date_max']) || $printer['last_closed_ticket_close_date'] > $printer_data['last_closed_ticket_close_date_max']) {
                                            $printer_data['last_closed_ticket_close_date_max'] = $printer['last_closed_ticket_close_date'];
                                        }

                                        if (isset($printer_data['otherserial'])) {
                                            $printer_data['otherserial'] .= ', ';
                                        } else {
                                            $printer_data['otherserial'] = '';
                                        }

                                        $printer_data['otherserial'] .= $printer['otherserial'];

                                        if (isset($printer_data['title'])) {
                                            $printer_data['title'] .= "\r\n";
                                        } else {
                                            $printer_data['title'] = '';
                                        }

                                        $printer_data['title'] .= "$printer[otherserial]: $printer[printer_contact_num] $printer[printer_contact]" . (empty($printer['printer_comment']) ? '' : " ($printer[printer_comment])");

                                        if (isset($printer_data['last_closed_ticket_title'])) {
                                            $printer_data['last_closed_ticket_title'] .= "\r\n";
                                        } else {
                                            $printer_data['last_closed_ticket_title'] = '';
                                        }

                                        $printer_data['last_closed_ticket_title'] .= "$printer[otherserial]: $printer[last_closed_ticket_close_date]";

                                        if (isset($printer_data['printer_name'])) {
                                            $printer_data['printer_name'] .= ', ';
                                        } else {
                                            $printer_data['printer_name'] = '';
                                        }

                                        $printer_data['printer_name'] .= $printer['printer_name'];

                                        if (!isset($printer_data['plan_observations_field'])) {
                                            $printer_data['plan_observations_field'] = '';
                                        } elseif (!empty($printer_data['plan_observations_field']) && !empty($printer['plan_observations_field'])) {
                                            $printer_data['plan_observations_field'] .= " AND ";
                                        }

                                        if (!empty($printer['plan_observations_field'])) {
                                            $printer_data['plan_observations_field'] .= $printer['plan_observations_field'];
                                        }

                                        if (!isset($printer_data['open_tickets_count'])) {
                                            $printer_data['open_tickets_count'] = 0;
                                        }

                                        if (!empty($printer['open_tickets_count'])) {
                                            $printer_data['open_tickets_count'] += $printer['open_tickets_count'];
                                        }

                                        $printer_data['printer_ids'][] = $printer_data['printer_id'] = $printer_id;
                                    }

                                    $printers[$printer_data['printer_id']] = $printer_data;
                                } else {
                                    $printers = [];
                                    foreach ($state['printers'] as $printer_id => $printer) {
                                        $printer['all_in_em']   = $printer['em_field'];
                                        $printer['printer_ids'] = [];
                                        $printers[$printer_id]  = $printer;
                                    }
                                }

                                foreach ($printers as $printer_id => $printer) {
                                    if (empty($printer['invoice_date_field']) || $printer['invoice_date_field'] == '0000-00-00') {
                                        $color = "black";
                                    } elseif (strtotime($printer['invoice_date_field']) < mktime(0, 0, 0, $month, 1, $year)) {
                                        if (!isset($printer['multiple'])) {
                                            $color = "red";
                                        } elseif (strtotime($printer['data_fact_max']) < mktime(0, 0, 0, $month, 1, $year)) {
                                            $color = "red";
                                        } else {
                                            $color = "blue";
                                        }

                                        foreach ($printer['printer_ids'] as $printer_id_to_read) {
                                            $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, "item[printer][$printer_id_to_read]", '1');
                                        }
                                    } else {
                                        $color = isset($printer['multiple']) ? "darkgreen" : "green";
                                    }

                                    $style = "color:$color;";
                                    if (isset($enterprise['moved'])) {
                                        $style .= "font-style:italic;font-weight:bold;";
                                    }

                                    if ($enterprise['movements_count'] ?? 0 > 0) {
                                        $style .= "background-color:lightyellow;";
                                    }
                                    ?>
                                    <tr class="tab_bg_<?php echo $row_num++ % 2 + 1; ?>" style="<?php echo $style; ?>">
                                        <?php
                                        global $CFG_GLPI;
                                        $siteUrl     = PluginIserviceConfig::getConfigValue('site_url');
                                        $body        = "Buna ziua!\r\n";
                                        $body       .= "Va rog sa completati starea contoarelor copiatoarelor aflate la dvs. pe interfata web: $siteUrl\r\n";
                                        $body       .= "In cazul in care nu aveti cont de utilizator, sau vi se cere o parola suplimentara pentru a accesa serverul Expert Line, va rog trimiteti o solicitare pe SMS sau WhatsApp la numarul 0722323366\r\n\r\n";
                                        $body       .= "Cu multumiri,\r\nCarmen";
                                        $mail_body   = str_replace('+', ' ', urlencode($body));
                                        $mailto_link = "mailto:$enterprise[enterprise_email_facturi]?subject=Citire contor/contoare copiatoare - $enterprise[enterprise_name]&body=$mail_body";
                                        $icon        = $printer['all_in_em'] ? 'em.png' : 'mail.png';
                                        $title       = $printer['all_in_em'] ? (isset($printer['multiple']) ? 'Toate aparatele în [EM]' : 'Aparat în [EM]') : 'Trimite email';
                                        echo "<td><a href='$mailto_link'><img src='" . $CFG_GLPI['root_doc'] . "/plugins/iservice/pics/$icon' title='$title'/></a></td>";
                                        ?>
                                        <td style="text-align: left;">
                                            <?php
                                            $title  = "Tehnician: $tech_name";
                                            $title .= "\r\nStare: $state_name";
                                            if (!empty($enterprise['enterprise_tel'])) {
                                                $title .= "\r\nTel: $enterprise[enterprise_tel]";
                                            }

                                            if (!empty($enterprise['enterprise_fax'])) {
                                                $title .= "\r\nFax: $enterprise[enterprise_fax]";
                                            }

                                            if (!empty($enterprise['enterprise_comment'])) {
                                                $title .= "\r\nObservații: $enterprise[enterprise_comment]";
                                            }

                                            if (!empty($enterprise['enterprise_email_facturi'])) {
                                                $title .= "\r\nEmail trimis facturi: $enterprise[enterprise_email_facturi]";
                                            }

                                            $title .= "\r\n\r\nData fact " . (isset($printer['multiple']) ? "max: $printer[data_fact_max]" : ":$printer[invoice_date_field]");
                                            echo "<a href='" . $CFG_GLPI['root_doc'] . "/front/supplier.form.php?id=$enterprise_id' title='$title'>$enterprise[enterprise_name]</a>";
                                            $printer_link = $CFG_GLPI['root_doc'] . "/plugins/iservice/front/views.php?view=Printers&printers0[supplier_id]=$enterprise_id&printers0[filter_description]=$enterprise[enterprise_name]";
                                            if (isset($printer['multiple'])) {
                                                echo empty($printer['plan_observations_field']) ? '' : " ($printer[plan_observations_field])";
                                                $search_string = str_replace(' ', '+', urldecode($enterprise['enterprise_name']));
                                                // echo " <a href='".GLPI_ROOT."/front/search.php?x=0&y=0&globalsearch=$search_string' title='$printer[title]'>**&nbsp;$printer[multiple]&nbsp;**</a>";
                                                echo " <a href='$printer_link' title='$printer[title]'>**&nbsp;$printer[multiple]&nbsp;**</a>";
                                            } else {
                                                $title = "Număr inventar: $printer[otherserial]";
                                                if (!empty($printer['printer_contact_num'])) {
                                                    $title .= "\r\nNumăr contact: $printer[printer_contact_num]";
                                                }

                                                if (!empty($printer['printer_contact'])) {
                                                    $title .= "\r\nContact: $printer[printer_contact]";
                                                }

                                                if (!empty($printer['printer_comment'])) {
                                                    $title .= "\r\nObservații: $printer[printer_comment]";
                                                }

                                                // echo " - <a href='".GLPI_ROOT."/front/printer.form.php?id=$printer_id' title='$title'>$printer[otherserial]</a>";
                                                echo " - <a href='$printer_link' title='$title'>$printer[otherserial]</a>";
                                                echo empty($printer['plan_observations_field']) ? '' : " ($printer[plan_observations_field])";
                                            }
                                            ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php
                                            /* if (empty($printer['last_closed_ticket_close_date']) || $printer['last_closed_ticket_close_date'] == '0000-00-00') {
                                              $color2 = "black";
                                              } else */if (strtotime($printer['last_closed_ticket_close_date']) < strtotime('-10 days')) {
                                                if (!isset($printer['multiple'])) {
                                                    $color2 = "red";
                                                } elseif (strtotime($printer['last_closed_ticket_close_date_max']) < strtotime('-10 days')) {
                                                    $color2 = "red";
                                                } else {
                                                    $color2 = "blue";
                                                }
} else {
    $color2 = isset($printer['multiple']) ? "darkgreen" : "green";
}

                                            $link    = $CFG_GLPI['root_doc'] . "/plugins/iservice/front/views.php?view=Tickets&tickets0[ticket_status]=1%2C2%2C3%2C4%2C5&tickets0[supplier_name]=$enterprise[enterprise_name]";
                                            $title2  = "Data închidere ultimul tichet închis\r\n";
                                            $title2 .= !empty($printer['multiple']) ? ($printer['last_closed_ticket_title'] . "\r\n\r\nMax: $printer[last_closed_ticket_close_date_max]") : $printer['last_closed_ticket_close_date'];
                                            echo "<a href='$link' style='color:$color2' title='$title2'>** $enterprise[open_tickets_count] **</a>";
?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php
                                            if ($enterprise['numar_facturi'] > 0) {
                                                echo "<a href='" . $CFG_GLPI['root_doc'] . "/plugins/iservice/front/views.php?view=UnpaidInvoices&unpaidinvoices0[cod]=$enterprise[hmarfa_code_field]'>**&nbsp;$enterprise[numar_facturi]&nbsp;**</a>";
                                            } else {
                                                echo $enterprise['numar_facturi'];
                                            }
                                            ?>
                                        </td>
                                        <td style="text-align: right;"><?php echo $enterprise['total_facturi'] == null ? '0.00' : number_format($enterprise['total_facturi'], 2); ?></td>
                                    </tr>
                                    <?php
                                }
                            }
                        }
                    }
                    ?>
                    </tbody>
                </table>
                <?php $form->closeForm(); ?>
            </td>
        <?php } ?>
    </tr>
    </tbody>
</table>
        <?php
    }

}

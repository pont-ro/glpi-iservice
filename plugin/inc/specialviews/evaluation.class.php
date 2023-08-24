<?php

// Imported from iService2, needs refactoring. Original file: "Evaluation.php".
namespace GlpiPlugin\Iservice\Specialviews;

use GlpiPlugin\Iservice\Views\View;
use \Session;
use \CommonITILActor;

class Evaluation extends View
{
    public static $rightname = 'plugin_iservice_view_evaluation';

    public static $icon = 'ti ti-calculator';

    public static function getName(): string
    {
        return __('Evaluation', 'iService');
    }

    protected function getSettings(): array
    {
        global $CFG_GLPI;
        $subsettings['tehnician']    = [
            'select' => "
									u.id
								, u.name
								, CONCAT(IFNULL(CONCAT(u.realname, ' '), ''), IFNULL(u.firstname, '')) fullname
								, IFNULL(a_t.ticket_count, 0) + IFNULL(o_t.ticket_count, 0) total_ticket_count
								, IFNULL(a_t.ticket_count, 0) assigned_ticket_count
								, IFNULL(o_t.ticket_count, 0) observed_ticket_count
								, IFNULL(c_t0.ticket_count, 0) ticket_count_0
								, IFNULL(c_t1.ticket_count, 0) ticket_count_1
								, IFNULL(c_t1_1.ticket_count, 0) ticket_count_1_1
								, IFNULL(c_t2.ticket_count, 0) ticket_count_2
								, IFNULL(c_t3.ticket_count, 0) ticket_count_3
								, IFNULL(c_t4.ticket_count, 0) ticket_count_4
								, IFNULL(c_t4_1.ticket_count, 0) ticket_count_4_1
								, IFNULL(c_t5.ticket_count, 0) ticket_count_5
								",
            'where' => "",
        ];
        $subsettings['subtehnician'] = [
            'select' => "
									GROUP_CONCAT(u.id SEPARATOR ',') id
								, 'T_*' name
								, 'Subtehnicieni' fullname
								, IFNULL(SUM(IFNULL(a_t.ticket_count, 0)),0) + IFNULL(SUM(IFNULL(o_t.ticket_count, 0)),0) total_ticket_count
								, IFNULL(SUM(IFNULL(a_t.ticket_count, 0)),0) assigned_ticket_count
								, IFNULL(SUM(IFNULL(o_t.ticket_count, 0)),0) observed_ticket_count
								, IFNULL(SUM(IFNULL(c_t0.ticket_count, 0)),0) ticket_count_0
								, IFNULL(SUM(IFNULL(c_t1.ticket_count, 0)),0) ticket_count_1
								, IFNULL(SUM(IFNULL(c_t1_1.ticket_count, 0)),0) ticket_count_1_1
								, IFNULL(SUM(IFNULL(c_t2.ticket_count, 0)),0) ticket_count_2
								, IFNULL(SUM(IFNULL(c_t3.ticket_count, 0)),0) ticket_count_3
								, IFNULL(SUM(IFNULL(c_t4.ticket_count, 0)),0) ticket_count_4
								, IFNULL(SUM(IFNULL(c_t4_1.ticket_count, 0)),0) ticket_count_4_1
								, IFNULL(SUM(IFNULL(c_t5.ticket_count, 0)),0) ticket_count_5
								",
            'where' => "AND u.name LIKE 'T\\_%'",
        ];
        $subsettings['superclient']  = [
            'select' => "
									GROUP_CONCAT(u.id SEPARATOR ',') id
								, 'S_*' name
								, 'Superclienți' fullname
								, IFNULL(SUM(IFNULL(a_t.ticket_count, 0)),0) + IFNULL(SUM(IFNULL(o_t.ticket_count, 0)),0) total_ticket_count
								, IFNULL(SUM(IFNULL(a_t.ticket_count, 0)),0) assigned_ticket_count
								, IFNULL(SUM(IFNULL(o_t.ticket_count, 0)),0) observed_ticket_count
								, IFNULL(SUM(IFNULL(c_t0.ticket_count, 0)),0) ticket_count_0
								, IFNULL(SUM(IFNULL(c_t1.ticket_count, 0)),0) ticket_count_1
								, IFNULL(SUM(IFNULL(c_t1_1.ticket_count, 0)),0) ticket_count_1_1
								, IFNULL(SUM(IFNULL(c_t2.ticket_count, 0)),0) ticket_count_2
								, IFNULL(SUM(IFNULL(c_t3.ticket_count, 0)),0) ticket_count_3
								, IFNULL(SUM(IFNULL(c_t4.ticket_count, 0)),0) ticket_count_4
								, IFNULL(SUM(IFNULL(c_t4_1.ticket_count, 0)),0) ticket_count_4_1
								, IFNULL(SUM(IFNULL(c_t5.ticket_count, 0)),0) ticket_count_5
								",
            'where' => "AND u.name LIKE 'S\\_%'",
        ];
        $subsettings['client']       = [
            'select' => "
									GROUP_CONCAT(u.id SEPARATOR ',') id
								, 'C_*' name
								, 'Clienți' fullname
								, IFNULL(SUM(IFNULL(a_t.ticket_count, 0)),0) + IFNULL(SUM(IFNULL(o_t.ticket_count, 0)),0) total_ticket_count
								, IFNULL(SUM(IFNULL(a_t.ticket_count, 0)),0) assigned_ticket_count
								, IFNULL(SUM(IFNULL(o_t.ticket_count, 0)),0) observed_ticket_count
								, IFNULL(SUM(IFNULL(c_t0.ticket_count, 0)),0) ticket_count_0
								, IFNULL(SUM(IFNULL(c_t1.ticket_count, 0)),0) ticket_count_1
								, IFNULL(SUM(IFNULL(c_t1_1.ticket_count, 0)),0) ticket_count_1_1
								, IFNULL(SUM(IFNULL(c_t2.ticket_count, 0)),0) ticket_count_2
								, IFNULL(SUM(IFNULL(c_t3.ticket_count, 0)),0) ticket_count_3
								, IFNULL(SUM(IFNULL(c_t4.ticket_count, 0)),0) ticket_count_4
								, IFNULL(SUM(IFNULL(c_t4_1.ticket_count, 0)),0) ticket_count_4_1
								, IFNULL(SUM(IFNULL(c_t5.ticket_count, 0)),0) ticket_count_5
								",
            'where' => "AND u.name LIKE 'C\\_%'",
        ];
        $queries                     = [];
        foreach ($subsettings as $profile => $subsetting) {
            $queries[] = "
					SELECT $subsetting[select]
						, '[start_date]' start_date
						, '[end_date]' end_date
					FROM glpi_users u
					LEFT JOIN ( SELECT tu.users_id user_id, count(t.id) ticket_count
											FROM glpi_tickets_users tu
											JOIN glpi_plugin_iservice_tickets t ON t.id = tu.tickets_id
											WHERE NOT t.is_deleted = 1
												AND tu.type = " . CommonITILActor::ASSIGN . "
												AND t.effective_date_field >= '[start_date]'
												AND t.effective_date_field <= '[end_date]'
											GROUP BY tu.users_id
										) a_t ON a_t.user_id = u.id
					LEFT JOIN ( SELECT tu.users_id user_id, count(t.id) ticket_count
											FROM glpi_tickets_users tu
											JOIN glpi_plugin_iservice_tickets t ON t.id = tu.tickets_id
											WHERE NOT t.is_deleted = 1
												AND tu.type = " . CommonITILActor::OBSERVER . "
												AND t.effective_date_field >= '[start_date]'
												AND t.effective_date_field <= '[end_date]'
											GROUP BY tu.users_id
										) o_t ON o_t.user_id = u.id
					LEFT JOIN ( SELECT tu.users_id user_id, count(t.id) ticket_count
											FROM glpi_tickets_users tu
											JOIN glpi_plugin_iservice_tickets t ON t.id = tu.tickets_id
											WHERE NOT t.is_deleted = 1
												AND tu.type in (" . CommonITILActor::OBSERVER . ", " . CommonITILActor::ASSIGN . ")
												AND t.effective_date_field >= '[start_date]'
												AND t.effective_date_field <= '[end_date]'
												AND NOT t.itilcategories_id in (13, 15, 18, 25, 26, 4, 11, 20, 17, 1, 2, 6, 19, 12)
											GROUP BY tu.users_id
										) c_t0 ON c_t0.user_id = u.id
					LEFT JOIN ( SELECT tu.users_id user_id, count(t.id) ticket_count
											FROM glpi_tickets_users tu
											JOIN glpi_plugin_iservice_tickets t ON t.id = tu.tickets_id
											WHERE NOT t.is_deleted = 1
												AND tu.type in (" . CommonITILActor::OBSERVER . ", " . CommonITILActor::ASSIGN . ")
												AND t.effective_date_field >= '[start_date]'
												AND t.effective_date_field <= '[end_date]'
												AND t.itilcategories_id in (13, 15, 18)
											GROUP BY tu.users_id
										) c_t1 ON c_t1.user_id = u.id
					LEFT JOIN ( SELECT tu.users_id user_id, count(t.id) ticket_count
											FROM glpi_tickets_users tu
											JOIN glpi_plugin_iservice_tickets t ON t.id = tu.tickets_id
											WHERE NOT t.is_deleted = 1
												AND tu.type in (" . CommonITILActor::OBSERVER . ", " . CommonITILActor::ASSIGN . ")
												AND t.effective_date_field >= '[start_date]'
												AND t.effective_date_field <= '[end_date]'
												AND t.itilcategories_id in (25, 26)
											GROUP BY tu.users_id
										) c_t1_1 ON c_t1_1.user_id = u.id
					LEFT JOIN ( SELECT tu.users_id user_id, count(t.id) ticket_count
											FROM glpi_tickets_users tu
											JOIN glpi_plugin_iservice_tickets t ON t.id = tu.tickets_id
											WHERE NOT t.is_deleted = 1
												AND tu.type in (" . CommonITILActor::OBSERVER . ", " . CommonITILActor::ASSIGN . ")
												AND t.effective_date_field >= '[start_date]'
												AND t.effective_date_field <= '[end_date]'
												AND t.itilcategories_id in (4, 11, 20)
											GROUP BY tu.users_id
										) c_t2 ON c_t2.user_id = u.id
					LEFT JOIN ( SELECT tu.users_id user_id, count(t.id) ticket_count
											FROM glpi_tickets_users tu
											JOIN glpi_plugin_iservice_tickets t ON t.id = tu.tickets_id
											WHERE NOT t.is_deleted = 1
												AND tu.type in (" . CommonITILActor::OBSERVER . ", " . CommonITILActor::ASSIGN . ")
												AND t.effective_date_field >= '[start_date]'
												AND t.effective_date_field <= '[end_date]'
												AND t.itilcategories_id in (17)
											GROUP BY tu.users_id
										) c_t3 ON c_t3.user_id = u.id
					LEFT JOIN ( SELECT tu.users_id user_id, count(t.id) ticket_count
											FROM glpi_tickets_users tu
											JOIN glpi_plugin_iservice_tickets t ON t.id = tu.tickets_id
											WHERE NOT t.is_deleted = 1
												AND tu.type in (" . CommonITILActor::OBSERVER . ", " . CommonITILActor::ASSIGN . ")
												AND t.effective_date_field >= '[start_date]'
												AND t.effective_date_field <= '[end_date]'
												AND t.itilcategories_id in (1, 2, 6)
											GROUP BY tu.users_id
										) c_t4 ON c_t4.user_id = u.id
					LEFT JOIN ( SELECT tu.users_id user_id, count(t.id) ticket_count
											FROM glpi_tickets_users tu
											JOIN glpi_plugin_iservice_tickets t ON t.id = tu.tickets_id
											WHERE NOT t.is_deleted = 1
												AND tu.type in (" . CommonITILActor::OBSERVER . ", " . CommonITILActor::ASSIGN . ")
												AND t.effective_date_field >= '[start_date]'
												AND t.effective_date_field <= '[end_date]'
												AND t.itilcategories_id in (19)
											GROUP BY tu.users_id
										) c_t4_1 ON c_t4_1.user_id = u.id
					LEFT JOIN ( SELECT tu.users_id user_id, count(t.id) ticket_count
											FROM glpi_tickets_users tu
											JOIN glpi_plugin_iservice_tickets t ON t.id = tu.tickets_id
											WHERE NOT t.is_deleted = 1
												AND tu.type in (" . CommonITILActor::OBSERVER . ", " . CommonITILActor::ASSIGN . ")
												AND t.effective_date_field >= '[start_date]'
												AND t.effective_date_field <= '[end_date]'
												AND t.itilcategories_id in (12)
											GROUP BY tu.users_id
										) c_t5 ON c_t5.user_id = u.id
					WHERE u.id in (SELECT pu.users_id
												 FROM glpi_profiles_users pu 
												 JOIN glpi_profiles p on p.id = pu.profiles_id
												 WHERE p.name in ('$profile')
												)
					$subsetting[where]
					";
        }

        return [
            'name' => __('Evaluation', 'iservice'),
            'query' => join(' UNION ', $queries),
            'default_limit' => 100,
            'show_filter_buttons' => Session::haveRight('plugin_iservice_view_evaluation', UPDATE),
            'show_limit' => 'ajax', //Session::haveRight('plugin_iservice_view_evaluation', UPDATE),
            'filters' => [
                'start_date' => [
                    'type' => 'date',
                    'caption' => 'Perioadă evaluare',
                    'format' => 'Y-m-d',
                    'empty_value' => date("Y-m-d", strtotime("-" . (date('w') - 1) . " days")),
                    'pre_widget' => "{$this->getWidgets()[self::WIDGET_LAST_WEEK]} {$this->getWidgets()[self::WIDGET_THIS_WEEK]}",
                ],
                'end_date' => [
                    'type' => 'date',
                    'caption' => ' - ',
                    'format' => 'Y-m-d',
                    'empty_value' => date('Y-m-d'),
                ],
            ],
            'columns' => [
                'name' => [
                    'title' => 'Utilizator',
                ],
                'fullname' => [
                    'title' => 'Nume complet',
                    'link' => [
                        'type' => 'detail',
                        'query' => "
												SELECT
													  t.id ticket_id
													, t.status
													, t.name ticket_name
													, t.content ticket_content
													, i.name ticket_category
													, p.id printer_id
													, p.name printer_name
													, p.serial printer_serial
													, l.completename printer_location
													, s.id supplier_id
													, s.name supplier_name
													, t.date date_open
													, CASE t.effective_date_field WHEN '0000-00-00' THEN NULL	ELSE t.effective_date_field END effective_date_field
													, u.id tech_park_id
													, CONCAT(IFNULL(CONCAT(u.realname, ' '),''), IFNULL(u.firstname, '')) tech_park_name
													, a.id tech_assign_id
													, CONCAT(IFNULL(CONCAT(a.realname, ' '),''), IFNULL(a.firstname, '')) tech_assign_name
													, o.id observer_id
													, CASE WHEN o.name IS NULL THEN NULL ELSE CONCAT(IFNULL(CONCAT(o.realname, ' '),''), IFNULL(o.firstname, '')) END observer_name
												FROM glpi_plugin_iservice_tickets t
												LEFT JOIN glpi_itilcategories i ON i.id = t.itilcategories_id
												LEFT JOIN glpi_items_tickets it ON it.tickets_id = t.id AND it.itemtype = 'Printer'
												LEFT JOIN glpi_printers p ON p.id = it.items_id
												LEFT JOIN glpi_locations l ON l.id = p.locations_id
												LEFT JOIN glpi_suppliers_tickets st ON st.tickets_id = t.id AND st.type = " . CommonITILActor::ASSIGN . "
												LEFT JOIN glpi_suppliers s ON s.id = st.suppliers_id
												LEFT JOIN glpi_users u ON u.id = p.users_id_tech
												LEFT JOIN glpi_tickets_users tua ON tua.tickets_id = t.id AND tua.type = " . CommonITILActor::ASSIGN . "
												LEFT JOIN glpi_users a ON a.id = tua.users_id
												LEFT JOIN glpi_tickets_users tuo ON tuo.tickets_id = t.id AND tuo.type = " . CommonITILActor::OBSERVER . "
												LEFT JOIN glpi_users o ON o.id = tuo.users_id
											  WHERE t.effective_date_field >= '[start_date]' AND t.effective_date_field <= '[end_date]'
												  AND (tua.users_id IN ([id]) OR tuo.users_id IN ([id]))
												",
                        'default_limit' => 50,
                        'show_filter_buttons' => false,
                        'show_limit' => true,
                        'columns' => [
                            'status' => [
                                'title' => 'Stare tichet',
                                'format' => 'function:\GlpiPlugin\Iservice\Specialviews\Tickets::getTicketStatusDisplay($row);',
                                'align' => 'center',
                            ],
                            'ticket_id' => [
                                'title' => 'Număr',
                                'align' => 'center',
                            ],
                            'ticket_name' => [
                                'title' => 'Titlu',
                                'tooltip' => '[ticket_content]',
                                'link' => [
                                    'href' => $CFG_GLPI['root_doc'] . '/front/ticket.form.php?id=[ticket_id]',
                                    'visible' => Session::haveRight('plugin_iservice_interface_original', READ),
                                ]
                            ],
                            'printer_name' => [
                                'title' => 'Nume aparat',
                                'link' => [
                                    'href' => $CFG_GLPI['root_doc'] . '/front/printer.form.php?id=[printer_id]',
                                    'visible' => Session::haveRight('plugin_iservice_interface_original', READ),
                                ],
                                'format' => '%s ([printer_location])',
                            ],
                            'supplier_name' => [
                                'title' => 'Partener',
                                'link' => [
                                    'href' => $CFG_GLPI['root_doc'] . '/front/supplier.form.php?id=[supplier_id]',
                                    'visible' => Session::haveRight('plugin_iservice_interface_original', READ),
                                ],
                            ],
                            'date_open' => [
                                'title' => 'Data deschiderii',
                                'style' => 'white-space: nowrap;',
                                'default_sort' => 'DESC'
                            ],
                            'effective_date_field' => [
                                'title' => 'Data efectivă',
                                'sort_default_dir' => 'DESC',
                                'style' => 'white-space: nowrap;',
                            ],
                            'tech_assign_name' => [
                                'title' => 'Tehnician alocat',
                                'format' => 'function:\GlpiPlugin\Iservice\Specialviews\Tickets::getTicketAssignTechDisplay($row);',
                            ],
                            'printer_serial' => [
                                'title' => 'Număr serie',
                            ],
                            'ticket_category' => [
                                'title' => 'Categorie'
                            ]
                        ],
                    ],
                ],
                'total_ticket_count' => [
                    'title' => 'Număr lucrări<br>(tichete&nbsp;asociate&nbsp;+&nbsp;observate)',
                    'align' => 'center',
                ],
                'observed_ticket_count' => [
                    'title' => 'Număr lucrări observate',
                    'align' => 'center',
                ],
                'ticket_count_1' => [
                    'title' => 'Livrare echipamente + Preluare echipamante + Mutare',
                    'align' => 'center',
                ],
                'ticket_count_1_1' => [
                    'title' => 'Livrare router + Preluare router',
                    'align' => 'center',
                ],
                'ticket_count_2' => [
                    'title' => 'Livrare consumabile + Livrare marfa + Aprovizionare',
                    'align' => 'center',
                ],
                'ticket_count_3' => [
                    'title' => 'Citire contor',
                    'align' => 'center',
                ],
                'ticket_count_4' => [
                    'title' => 'Interventie regulata + Interventie urgenta + Interventie la comanda',
                    'align' => 'center',
                ],
                'ticket_count_4_1' => [
                    'title' => 'Plati',
                    'align' => 'center',
                ],
                'ticket_count_5' => [
                    'title' => 'Probleme soft',
                    'align' => 'center',
                ],
                'ticket_count_0' => [
                    'title' => 'Alte',
                    'align' => 'center',
                ],
            ],
        ];
    }

}

<?php

// Imported from iService2, needs refactoring.
class PluginIserviceView_Unpaid_Invoices extends PluginIserviceView {

    static $order = 10;

    static function getName() {
        return 'Facturi';
    }

    static function getCodDisplay($row_data) {
        $color = $row_data['data_factura'] < $row_data['last_magic_link_access'] ? 'green' : 'red';
        return "<span style='color:$color'  title='Link magic nu a fost accesat'>$row_data[cod]</span>";
    }

    static function getInvoiceConfirmationDisplay($row_data) {
        if ($row_data['invoice_confirmation'] == 'da') {
            $tooltip = "Confirmat de $row_data[invoice_confirmer] pe $row_data[invoice_confirmation_date]";
            return "<span class='form-group-checkbox' title='$tooltip'><input type='checkbox' class='new_checkbox' checked/><label class='label-checkbox'><span class=check></span></label></span>";
        } else {
            return '';
        }
    }

    protected function getSettings() {
        global $CFG_GLPI;
        return array(
            'name' => self::getName(),
            'query' => "
						SELECT
							  tehnician
							, cod
							, nume_client
							, nrfac
							, data_factura
							, valoare
							, valoare_neincasata
							, last_magic_link_access
							, nume_client_glpi
							, CASE invoice_confirmation WHEN 0 THEN 'nu' ELSE 'da' END invoice_confirmation
							, invoice_confirmation_date
							, invoice_confirmer
						FROM
							(
								SELECT
										fa.nrcmd AS tehnician
									, fa.codbenef AS cod
									, fi.initiale AS nume_client
									, fa.nrfac AS nrfac
									, fa.datafac AS data_factura
									, ROUND(fa.valinc,2) AS valoare
									, SUM(ROUND(fa.valinc-fa.valpla,2)) AS valoare_neincasata
									, ma.last_magic_link_access
									, s.name AS nume_client_glpi
									, IFNULL(ic.id, 0) invoice_confirmation
									, ic.date invoice_confirmation_date
									, CONCAT(IFNULL(CONCAT(u.realname, ' '),''), IFNULL(u.firstname, '')) invoice_confirmer
								FROM {$this->table_prefix}hmarfa_facturi fa
								JOIN {$this->table_prefix}hmarfa_firme fi ON fa.codbenef = fi.cod
								LEFT JOIN glpi_plugin_fields_suppliercustomfields sc ON sc.cod_hmarfa = fi.cod and sc.itemtype = 'Supplier'
								LEFT JOIN (
										SELECT MAX(date) last_magic_link_access, items_id
										FROM glpi_plugin_iservice_downloads
										WHERE not ip like '10.%'
										  AND downloadtype = '" . PluginIserviceDownload::DOWNLOAD_TYPE_MAGIC_LINK . "'
										GROUP BY items_id
										) ma on ma.items_id = sc.items_id
								LEFT JOIN glpi_suppliers s ON s.id = sc.items_id
								LEFT JOIN glpi_plugin_iservice_downloads ic ON ic.items_id = fa.nrfac AND ic.downloadtype = '" . PluginIserviceDownload::DOWNLOAD_TYPE_INVOICE_CONFIRMED . "'
								LEFT JOIN glpi_users u ON u.id = ic.users_id
								WHERE (fa.codl = 'F' OR fa.stare like 'V%') AND fa.tip like 'TF%'
								GROUP BY fa.nrfac
							) as t
						WHERE t.valoare > [rest]
							AND t.valoare_neincasata > [neinc]
							AND t.data_factura >= '[start_date]'
							AND t.data_factura <= '[end_date]'
							AND t.cod LIKE '[cod]'
							AND t.nrfac LIKE '[nrfac]'
							AND t.nume_client LIKE '[nume_client]'
							AND t.tehnician LIKE '[tehnician]'
							[confirmed]
						",
            'default_limit' => 25,
            'id_field' => 'nrfac',
            'itemtype' => 'invoice',
            'mass_actions' => array(
                'confirm' => array(
                    'caption' => 'Confirmă primirea facturii',
                    'action' => $CFG_GLPI['root_doc'] . '/plugins/iservice/front/invoice_confirm.php',
                ),
                'unconfirm' => array(
                    'caption' => 'Revocă Confirmarea',
                    'action' => $CFG_GLPI['root_doc'] . '/plugins/iservice/front/invoice_confirm.php',
                ),
            ),
            'filters' => array(
                'start_date' => array(
                    'type' => 'date',
                    'caption' => 'Data factură',
                    'format' => 'Y-m-d',
                    'empty_value' => '2000-01-01',
                    'pre_widget' => "{$this->getWidgets()[self::WIDGET_LAST_6_MONTH]} {$this->getWidgets()[self::WIDGET_LAST_MONTH]} {$this->getWidgets()[self::WIDGET_THIS_MONTH]} ",
                ),
                'confirmed' => array(
                    'type' => self::FILTERTYPE_SELECT,
                    'header' => 'invoice_confirmation',
                    'options' => array(
                        '0' => 'toate',
                        'da' => 'da',
                        'nu' => 'nu',
                    ),
                    'format' => "AND CASE invoice_confirmation WHEN 0 THEN 'nu' ELSE 'da' END = '%s'",
                ),
                'end_date' => array(
                    'type' => 'date',
                    'caption' => '-&nbsp;&nbsp;&nbsp;',
                    'format' => 'Y-m-d',
                    'empty_value' => date('Y-m-d'),
                ),
                'cod' => array(
                    'type' => 'text',
                    'caption' => 'Cod',
                    'format' => '%%%s%%',
                    'header' => 'cod',
                ),
                'nrfac' => array(
                    'type' => 'text',
                    'caption' => 'Nrfac',
                    'format' => '%%%s%%',
                    'header' => 'nrfac',
                ),
                'rest' => array(
                    'type' => 'int',
                    'caption' => 'Rest de plată',
                    'format' => '%d',
                    'default' => -999999,
                    'empty_value' => -999999,
                    'style' => 'text-align:right;width:5em;',
                    'header' => 'valoare',
                    'header_caption' => '> ',
                ),
                'neinc' => array(
                    'type' => 'int',
                    'caption' => 'Valoare neincasată',
                    'format' => '%d',
                    'default' => -999999,
                    'empty_value' => -999999,
                    'style' => 'text-align:right;width:5em;',
                    'header' => 'valoare_neincasata',
                    'header_caption' => '> ',
                ),
                'nume_client' => array(
                    'type' => 'text',
                    'caption' => 'Nume client',
                    'format' => '%%%s%%',
                    'header' => 'nume_client',
                ),
                'tehnician' => array(
                    'type' => 'text',
                    'caption' => 'Tehnician',
                    'format' => '%%%s%%',
                    'header' => 'tehnician',
                ),
            ),
            'columns' => array(
                'tehnician' => array(
                    'title' => __('Technician'),
                ),
                'invoice_confirmation' => array(
                    'title' => 'Confirmat',
                    'format' => 'function:PluginIserviceView_Unpaid_Invoices::getInvoiceConfirmationDisplay($row);',
                ),
                'cod' => array(
                    'title' => 'Cod',
                    'format' => 'function:PluginIserviceView_Unpaid_Invoices::getCodDisplay($row);',
                    'link' => array(
                        'href' => $CFG_GLPI['root_doc'] . '/plugins/iservice/front/view.php?view=partners&partners0[partener]=[nume_client_glpi]',
                    ),
                ),
                'last_magic_link_access' => array(
                    'title' => 'Last magic link access',
                ),
                'nume_client' => array(
                    'title' => 'Nume client',
                ),
                'nrfac' => array(
                    'title' => 'Nrfac',
                    'tooltip' => 'Detalii factură',
                    'link' => array(
                        'type' => 'detail',
                        'query' => "
												SELECT
														codmat AS Cod_Articol
													, n.denum AS Denumire_Articol
													, f.descr AS Descriere
													, gest AS Gest
													, cant AS Cant
													, pucont AS Pu_Intr
													, puliv AS Pu_Liv
													, vbaza AS Valoare
													, vtva AS TVA
													, ROUND((puliv-pucont)*cant,2) AS Adaos
													, ROUND(vbaza+vtva,2) AS Total
												FROM {$this->table_prefix}hmarfa_facrind f
												LEFT JOIN {$this->table_prefix}hmarfa_nommarfa n ON f.codmat = n.cod
												WHERE f.nrfac = '[#detail#key#]'
												",
                        'detail_key' => '[nrfac]',
                        'name' => 'Detalii factura numărul: [#detail#key#]',
                        'columns' => array(
                            'Cod_Articol' => array(
                                'title' => 'Cod articol',
                            ),
                            'Denumire_Articol' => array(
                                'title' => 'Denumire articol',
                            ),
                            'Descriere' => array(
                                'title' => 'Descriere',
                            ),
                            'Gest' => array(
                                'title' => 'Gest',
                            ),
                            'Cant' => array(
                                'title' => 'Cant',
                                'align' => 'center',
                            ),
                            'Pu_Intr' => array(
                                'title' => 'Pu intr',
                                'align' => 'right',
                            ),
                            'Pu_Liv' => array(
                                'title' => 'Pu liv',
                                'align' => 'right',
                            ),
                            'Valoare' => array(
                                'title' => 'Valoare',
                                'align' => 'right',
                                'total' => true,
                            ),
                            'TVA' => array(
                                'title' => 'TVA',
                                'align' => 'right',
                            ),
                            'Adaos' => array(
                                'title' => 'Adaos',
                                'align' => 'right',
                            ),
                            'Total' => array(
                                'title' => 'Total',
                                'align' => 'right',
                                'total' => true,
                            ),
                        )
                    )
                ),
                'data_factura' => array(
                    'title' => 'Data factură',
                    'align' => 'center',
                    'default_sort' => 'DESC',
                    'style' => 'white-space: nowrap;',
                ),
                'valoare' => array(
                    'title' => 'Valoare',
                    'align' => 'right',
                    'total' => true,
                ),
                'valoare_neincasata' => array(
                    'title' => 'Valoare neincasată',
                    'align' => 'right',
                    'total' => true,
                ),
            ),
        );
    }

}

<?php

// Imported from iService2, needs refactoring. Original file: "Facturi_Client.php".
// File and class will be renamed after review.
namespace GlpiPlugin\Iservice\Views;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use GlpiPlugin\Iservice\Views\View;
use GlpiPlugin\Iservice\Views\Views;
use GlpiPlugin\Iservice\Views\LastNTickets;
use Html;
use PluginIserviceDownload;
use PluginIserviceHtml;
use PluginIserviceHtml_table;
use PluginIservicePartner;
use PluginIserviceTicket;
use \Session;
use Supplier;

class ClientInvoices extends View
{

    public static $rightname = 'plugin_iservice_view_facturi_client';

    public static $icon = 'ti ti-file-invoice';

    protected $partner       = null;
    protected $client_access = false;

    public static function getRowBackgroundClass($rowData)
    {
        if (empty($rowData)) {
            return "";
        }

        return $rowData['valoare_neincasata'] == 0 ? "bg_payed" : ($rowData['valoare_neincasata'] == $rowData['valoare'] ? "bg_not_payed" : "bg_partially_payed");
    }

    public static function getDataPlataDisplay($row_data)
    {
        return $row_data['valoare_neincasata'] == 0 ? "DA" : ($row_data['valoare_neincasata'] == $row_data['valoare'] ? "NU" : "PARȚIAL");
    }

    public static function getDownloadDisplay($row_data, $magic_link)
    {
        $download = new PluginIserviceDownload(PluginIserviceDownload::DOWNLOAD_TYPE_INVOICE);
        if ($download->exists($row_data['nrfac'])) {
            if (empty($magic_link)) {
                return "Nu exista linkul magic!";
            } else {
                return "<a href='download.php?id=$magic_link&nrfac=$row_data[nrfac]'>Descarcă factura</a>";
            }
        } else {
            return "Factură indisponibilă";
        }
    }

    public static function getName($partner = '', $client_access = true): string
    {
        if (empty($partner) || !($partner instanceof Supplier)) {
            return "Facturi";
        }

        $name = 'Facturi ' . $partner->fields['name'];
        if ($client_access) {
            $debt = number_format($partner->getInvoiceInfo(PluginIservicePartner::INVOICEINFO_DEBT), 2, '.', '');
            if ($debt > 0) {
                $name .= " - <span style='color: red'>Valoare neîncasată: $debt RON</span>";
            } else {
                $name .= " - <span style='color: green'>Nu aveți facturi neachitate, vă mulțumim!</span>";
            }
        }

        return $name;
    }

    public static function getDescription($partner)
    {
        global $CFG_PLUGIN_ISERVICE;
        $mail_recipient = $partner->customfields->fields['email_for_invoices_field'];
        if (empty($partner->customfields->fields['magic_link_field'])) {
            $magic_link = null;
        } else {
            $magic_link                                  = $partner->getMagicLink();
            $generate_magic_link_button_options['class'] = 'submit new';
        }

        $months                                     = [
            1 => 'ianuarie',
            2 => 'februarie',
            3 => 'martie',
            4 => 'aprilie',
            5 => 'mai',
            6 => 'iunie',
            7 => 'iulie',
            8 => 'august',
            9 => 'septembrie',
            10 => 'octombrie',
            11 => 'noiembrie',
            12 => 'decembrie',
        ];
        $mail_subject                               = "Factura ExpertLine - {$partner->fields['name']} - " . $months[date("n")] . ", " . date("Y");
        $mail_body                                  = $partner->getMailBody('scadente');
        $generate_magic_link_button_options['type'] = 'submit';
        $contact_partner_link                       = "$CFG_PLUGIN_ISERVICE[root_doc]/front/ticket.form.php?_suppliers_id_assign={$partner->getID()}&mode=" . PluginIserviceTicket::MODE_PARTNERCONTACT;
        ob_start();
        $form = new PluginIserviceHtml();
        $form->openForm(
            [
                'action' => PluginIservicePartner::getFormURL(),
                'class' => 'iservice-form two-column',
                'method' => 'post'
            ]
        );
        $form->displayField(PluginIserviceHtml::FIELDTYPE_HIDDEN, 'id', $partner->getID());
        $partner_table = new PluginIserviceHtml_table(
            'tab_cadre_fixe wide', "<tr><th style='padding:0;width:200px;'></th><th style='padding:0;'></th>", [
                $form->generateFieldTableRow(__('Phone'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'phonenumber', $partner->fields['phonenumber'])),
                $form->generateFieldTableRow(__('Address'), $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, 'address', $partner->fields['address'])),
                $form->generateFieldTableRow(__('Comments'), $form->generateField(PluginIserviceHtml::FIELDTYPE_MEMO, 'comment', $partner->fields['comment'])),
                $form->generateFieldTableRow('Email pentru trimis facturi', $form->generateField(PluginIserviceHtml::FIELDTYPE_TEXT, '_customfields[email_for_invoices_field]', $partner->customfields->fields['email_for_invoices_field'])),
                $form->generateButtonsTableRow(
                    [
                        $form->generateButton('update', __('Save') . " " . __('Supplier'), ['type' => 'submit']),
                        $form->generateButton('generate_magic_link', 'Generează link magic' . (empty($magic_link) ? '' : ' nou'), $generate_magic_link_button_options),
                        empty($magic_link) ? '' : "<a href='mailto:$mail_recipient?subject=$mail_subject&body=$mail_body' class='vsubmit' style='margin:1em;'>Trimite email</a>",
                        "<a href='$contact_partner_link' class='vsubmit' style='margin:1em;' target='_blank'>Ticket plăți</a>",
                    ]
                ),
            ]
        );

        echo $partner_table;
        $form->closeForm();
        $description = ob_get_contents();
        ob_end_clean();
        return $description;
    }

    public static function getSuffix($partner, $client_access)
    {
        if (!in_array($_SESSION["glpiactiveprofile"]["name"], ['tehnician', 'admin', 'super-admin'])) {
            return "";
        }

        ob_start();
        echo '<br/><h1>Ultimele 5 tichete cu categoria "Plati" pentru clientul ' . self::getName($partner, $client_access) . '</h1>';
        $view = Views::getView('GlpiPlugin\Iservice\Views\LastNTickets', false);
        $view->customize(['type' => LastNTickets::TYPE_PLATI, 'n' => 10, 'supplier_id' => $partner->getID()]);
        $view->display(true, false, 0, false);
        $suffix = ob_get_contents();
        ob_end_clean();
        return $suffix;
    }

    public function customize($params = [])
    {
        parent::customize($params);
        $this->client_access = isset($params['client_access']) ? (empty($params['client_access']) ? false : true) : false;
        $this->partner       = isset($params['partner']) ? $params['partner'] : null;
    }

    protected function getSettings(): array
    {
        if (empty($this->partner)) {
            $request_variables = IserviceToolBox::getArrayInputVariable($this->getRequestArrayName(), []);
            if (!isset($request_variables['partner_id'])) {
                die("Partner should be given!");
            }

            $this->partner = new PluginIservicePartner();
            $this->partner->getFromDB($request_variables['partner_id']);
            if ($this->partner->isNewItem()) {
                Html::displayNotFoundError();
            }
        }

        return [
            'id' => 'facturi_client',
            'name' => ClientInvoices::getName($this->partner, $this->client_access),
            'description' => $this->client_access ? '' : ClientInvoices::getDescription($this->partner),
            'postfix' => ClientInvoices::getSuffix($this->partner, $this->client_access),
            'params' => [
                'id' => $this->client_access ? $this->partner->customfields->fields['magic_link_field'] : '',
                'cui' => isset($this->partner->customfields->fields['uic_field']) ? $this->partner->customfields->fields['uic_field'] : "",
                'partner_id' => $this->client_access ? '' : ($this->partner->getID()),
            ],
            'query' => "
                        SELECT
                              tehnician
                            , nrfac
                            , download_count
                            , data_factura
                            , data_scadenta
                            , valoare
                            , valoare_neincasata
                        FROM
                            (
                                SELECT
                                        fa.nrcmd AS tehnician
                                    , fa.nrfac AS nrfac
                                    , coalesce(d.download_count, '0') download_count
                                    , fa.datafac AS data_factura
                                    , fa.dscad AS data_scadenta
                                    , ROUND(fa.valinc,2) AS valoare
                                    , SUM(ROUND(fa.valinc-fa.valpla,2)) AS valoare_neincasata
                                FROM hmarfa_facturi fa
                                LEFT JOIN (
                                      SELECT items_id, count(items_id) download_count
                                            FROM glpi_plugin_iservice_downloads
                                            WHERE downloadtype = 'invoice'
                                            GROUP BY items_id
                                     ) d ON d.items_id = fa.nrfac
                                WHERE (fa.codl = 'F' OR fa.stare like 'V%') AND fa.tip like 'TF%'
                                  AND fa.codbenef = '{$this->partner->customfields->fields['hmarfa_code_field']}'
                                GROUP BY fa.nrfac
                            ) as t
                        WHERE t.nrfac LIKE '[nrfac]'
                          AND (tehnician is null and '[tehnician]' = '%%' OR tehnician LIKE '[tehnician]')
                        ",
            'default_limit' => 10,
            'show_limit' => 'ajax', //Session::haveRight('plugin_iservice_view_facturi_client', UPDATE),
            'row_class' => 'function:\GlpiPlugin\Iservice\Views\ClientInvoices::getRowBackgroundClass($row_data);',
            'filters' => [
                'nrfac' => [
                    'type' => 'text',
                    'caption' => 'Nrfac',
                    'format' => '%%%s%%',
                    'header' => 'nrfac',
                ],
                'tehnician' => [
                    'type' => 'text',
                    'caption' => 'Tehnician',
                    'format' => '%%%s%%',
                    'header' => 'tehnician',
                ],
            ],
            'columns' => [
                'tehnician' => [
                    'title' => __('Technician'),
                    'visible' => !$this->client_access,
                ],
                'nrfac' => [
                    'title' => 'Număr factură',
                    'tooltip' => 'Detalii factură',
                    'link' => [
                        'visible' => !$this->client_access,
                        'type' => 'detail',
                        'name' => 'Detalii factura numărul: [nrfac]',
                        'query' => "
                                    SELECT
                                            codmat AS Cod_Articol
                                        , n.denum AS Denumire_Articol
                                        , f.descr AS Descriere
                                        , cant AS Cant
                                        , puliv AS Pu_Liv
                                        , vbaza AS Valoare
                                        , vtva AS TVA
                                        , ROUND(vbaza+vtva,2) AS Total
                                    FROM hmarfa_facrind f
                                    LEFT JOIN hmarfa_nommarfa n ON f.codmat = n.cod
                                    WHERE f.nrfac = '[nrfac]'
                                    ",
                        'columns' => [
                            'Cod_Articol' => [
                                'title' => 'Cod articol',
                            ],
                            'Denumire_Articol' => [
                                'title' => 'Denumire articol',
                            ],
                            'Descriere' => [
                                'title' => 'Descriere',
                            ],
                            'Cant' => [
                                'title' => 'Cant',
                                'align' => 'center',
                            ],
                            'Pu_Liv' => [
                                'title' => 'Pu',
                                'align' => 'right',
                            ],
                            'Valoare' => [
                                'title' => 'Valoare',
                                'align' => 'right',
                                'total' => true,
                            ],
                            'TVA' => [
                                'title' => 'TVA',
                                'align' => 'right',
                                'total' => true,
                            ],
                            'Total' => [
                                'title' => 'Total',
                                'align' => 'right',
                                'total' => true,
                            ],
                        ]
                    ]
                ],
                'download_count' => [
                    'title' => 'Număr descărcări',
                    'tooltip' => 'Detalii descărcări',
                    'align' => 'center',
                    'visible' => !$this->client_access,
                    'link' => [
                        'type' => 'detail',
                        'name' => 'Descărcări factura [nrfac]',
                        'query' => "
                                    SELECT *
                                    FROM glpi_plugin_iservice_downloads
                                    WHERE downloadtype='invoice'
                                      AND items_id = '[nrfac]'
                                    ORDER BY date DESC
                                    ",
                        'columns' => [
                            'date' => [
                                'title' => 'Data descărcării',
                                'align' => 'center',
                            ],
                            'ip' => [
                                'title' => 'IP',
                                'align' => 'center',
                            ],
                        ],
                    ],
                ],
                'data_factura' => [
                    'title' => 'Data factură',
                    'align' => 'center',
                    'default_sort' => 'DESC',
                    'style' => 'white-space: nowrap;',
                ],
                'data_scadenta' => [
                    'title' => 'Data scadență',
                    'align' => 'center',
                    'style' => 'white-space: nowrap;',
                ],
                'data_plata' => [
                    'title' => 'Achitat',
                    'tooltip' => 'Accesări ale linkului magic',
                    'format' => 'function:\GlpiPlugin\Iservice\Views\ClientInvoices::getDataPlataDisplay($row);',
                    'align' => 'center',
                    'style' => 'white-space: nowrap;',
                    'link' => [
                        'visible' => !$this->client_access,
                        'type' => 'detail',
                        'name' => 'Accesări ale linkului magic',
                        'query' => "
                                    SELECT *
                                    FROM glpi_plugin_iservice_downloads
                                    WHERE downloadtype='magic_link_field'
                                        AND items_id = '{$this->partner->getID()}'
                                    ORDER BY date DESC
                                    ",
                        'columns' => [
                            'date' => [
                                'title' => 'Data accesării',
                                'align' => 'center',
                            ],
                            'ip' => [
                                'title' => 'IP',
                                'align' => 'center',
                            ],
                        ],
                    ],
                ],
                'valoare' => [
                    'title' => 'Valoare',
                    'align' => 'right',
                ],
                'valoare_neincasata' => [
                    'title' => 'Valoare neincasată',
                    'align' => 'right',
                    'total' => true,
                ],
                'download' => [
                    'title' => 'Descarcă',
                    'align' => 'center',
                    'format' => "function:\GlpiPlugin\Iservice\Views\ClientInvoices::getDownloadDisplay(\$row, '{$this->partner->customfields->fields['magic_link_field']}');",
                ],
            ],
        ];
    }

}

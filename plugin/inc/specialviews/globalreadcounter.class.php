<?php

// Imported from iService2, needs refactoring. Original file: "Global_ReadCounter.php".
namespace GlpiPlugin\Iservice\Specialviews;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;
use GlpiPlugin\Iservice\Views\View;
use \Session;
use \PluginIserviceTicket;
use \PluginIserviceEmaintenance;
use \PluginIservicePrinter;

class GlobalReadCounter extends View
{

    public static $rightname = 'plugin_iservice_view_global_readcounter';

    public static $icon = 'ti ti-brand-days-counter';

    public static function getName(): string
    {
        return __('GlobalReadCounter', 'iService');
    }

    public static function getPrinterDisplay($row_data, $import_data): string
    {
        $hidden_data = "<input type='hidden' name='globalreadcounter0[printer][$row_data[id]][items_id][Printer][0]' value='$row_data[id]' /><input type='hidden' name='globalreadcounter0[printer][$row_data[id]][_suppliers_id_assign]' value='$row_data[supplier_id]' />";
        if ($import_data === null || isset($import_data[$row_data['spaceless_serial']])) {
            return $hidden_data . $row_data['printer_name'];
        }

        return "$hidden_data<span style='color:red' title='Aparatul nu există în fișierul de import'>$row_data[printer_name]</span>";
    }

    public static function getOtherSerialDisplay($row_data): string
    {
        if ($row_data['no_invoice_field']) {
            return "<span class='error' title='Aparat exclus din facturare'>" . $row_data['otherserial'] . "</span>";
        }

        return $row_data['otherserial'];
    }

    public static function getSupplierDisplay($row_data, $import_data): ?string
    {
        if (empty($import_data[$row_data['spaceless_serial']])) {
            return $row_data['supplier_name'];
        } else {
            $import_data = $import_data[$row_data['spaceless_serial']];
        }

        $display_data = [];
        foreach (['effective_date_field', 'total2_black_field', 'total2_color_field', 'date_use', 'date_out'] as $field_name) {
            // $import_data[$field_name] is an array in case of an error
            $display_data[$field_name] = is_array($import_data[$field_name]) ? '' : $import_data[$field_name];
        }

        $title = str_replace(
            '#empty#import#data#', '',
            "Date citite din fișier:\n" .
            "&nbsp;- Client: $import_data[partner_name] ($import_data[partner_id] - $import_data[partner_resolved_name])\n" .
            "&nbsp;- Aparat: $row_data[spaceless_serial]\n" .
            "&nbsp;&nbsp;&nbsp;- data citirii: $display_data[effective_date_field]\n" .
            "&nbsp;&nbsp;&nbsp;- contor black: $display_data[total2_black_field]\n" .
            "&nbsp;&nbsp;&nbsp;- contor color: $display_data[total2_color_field]\n" .
            "&nbsp;&nbsp;&nbsp;- data instalarii: $display_data[date_use]\n" .
            "&nbsp;&nbsp;&nbsp;- data predarii: $display_data[date_out]"
        );
        $info  = "&nbsp;<i class='fa fa-info-circle' title='$title'></i>";

        if ($row_data['supplier_name'] === $import_data['partner_name']) {
            return $row_data['supplier_name'] . $info;
        }

        return "<span style='color:red' title='Partenereul are numele \"$row_data[supplier_name]\" în iService, dar \"{$import_data['partner_name']}\" în E-Maintenance'>$row_data[supplier_name]</span>$info";
    }

    public static function generateBadgeClickHandler($badge_type = "error"): string
    {
        global $param_data;
        if (!empty($param_data['row_data']['no_invoice_field'])) {
            return '';
        }

        $data_difference = round((time() - strtotime($param_data['row_data']['last_effective_date'])) / (60 * 60 * 24));
        $estimate_value  = '';
        $icon_click      = "";
        foreach (['total2_black_field', 'total2_color_field', 'effective_date_field'] as $fieldname) {
            switch ($fieldname) {
            case 'total2_black_field':
                $estimate_value = $param_data['row_data']['last_total2_black'] + ($param_data['row_data']['daily_bk_average_field'] * $data_difference);
                $icon_click    .= sprintf("$(\"<i></i>\").addClass(\"fa fa-exclamation-triangle badge-error\").attr(\"style\", \"color:orange;\").attr(\"title\",\"Valoare estimată: %s + (%s * %s zile)\").insertAfter($(\"[name=globalreadcounter0\\\[printer\\\]\\\[%s\\\]\\\[%s\\\]]\")).parent().find(\"input\");", $param_data['row_data']['last_total2_black'], $param_data['row_data']['daily_bk_average_field'], $data_difference, $param_data['row_id'], $fieldname);
                break;
            case 'total2_color_field':
                if ($param_data['row_data']['daily_color_average_field'] == 0) {
                    $estimate_value = 0;
                } else {
                    $estimate_value = $param_data['row_data']['last_total2_color'] + ($param_data['row_data']['daily_color_average_field'] * $data_difference);
                    $icon_click    .= sprintf("$(\"<i></i>\").addClass(\"fa fa-exclamation-triangle badge-error\").attr(\"style\", \"color:orange;\").attr(\"title\",\"Valoare estimată: %s + (%s * %s zile)\").insertAfter($(\"[name=globalreadcounter0\\\[printer\\\]\\\[%s\\\]\\\[%s\\\]]\")).parent().find(\"input\");", $param_data['row_data']['last_total2_color'], $param_data['row_data']['daily_color_average_field'], $data_difference, $param_data['row_id'], $fieldname);
                }
                break;
            case 'effective_date_field':
                $estimate_value = date("Y-m-d h:m:s");
                break;
            }

            if ($estimate_value === 0) {
                continue;
            }

            $icon_click .= sprintf("$(\"[name=globalreadcounter0\\\\[printer\\\\]\\\\[%s\\\\]\\\\[%s\\\\]]\").parent().find(\"input\").val(\"%s\");", $param_data['row_id'], $fieldname, $estimate_value);
            $icon_click .= sprintf("$(\"#badge-%s-%s-%s\").hide();", $badge_type, $param_data['row_id'], $fieldname);
        }

        $icon_click .= sprintf("setSelectField($(\"[name=globalreadcounter0\\\\[printer\\\\]\\\\[%s\\\\]\\\\[itilcategories_id\\\\]]\") , \"30\", \"Citire contor - estimat\");", $param_data['row_id']);
        return "onclick='$icon_click'";
    }

    public static function generateEstimateBadgeText($text = null): string
    {
        global $param_data;
        return ($param_data['row_data']['no_invoice_field'] ?? '') ? 'Aparat exclus din facturare' : $text ?? 'Click pentru estimare';
    }

    public static function generateErrorBadgeText(): string
    {
        global $param_data;
        return $param_data['row_data']['no_invoice_field'] ? 'Aparat exclus din facturare' : '';
    }

    protected function getSettings(): array
    {
        global $CFG_GLPI;
        $items                  = IserviceToolBox::getArrayInputVariable('globalreadcounter0', null);
        $import                 = IserviceToolBox::getInputVariable('import');
        $iwm_import             = IserviceToolBox::getInputVariable('iwm_import');
        $avitum_import          = IserviceToolBox::getInputVariable('avitum_import');
        $mass_action_group_read = IserviceToolBox::getInputVariable('mass_action_group_read');
        if (!empty($import)) {
            $default_itil_category = PluginIserviceTicket::getItilCategoryId('Citire emaintenance');
            $this->import_data     = PluginIserviceEmaintenance::getDataFromCsv(IserviceToolBox::getInputVariable('import_file'));
        } elseif (!empty($iwm_import)) {
            $default_itil_category = PluginIserviceTicket::getItilCategoryId('Citire emaintenance');
            $this->import_data     = PluginIserviceEmaintenance::getDataFromCsv($_FILES['iwm_import_file']['tmp_name'], 'IW');
        } elseif (!empty($avitum_import)) {
            $default_itil_category = PluginIserviceTicket::getItilCategoryId('Citire emaintenance');
            $this->import_data     = PluginIserviceEmaintenance::getDataFromCsv($_FILES['iwm_import_file']['tmp_name'], 'AVITUM');
        } elseif (!empty($mass_action_group_read)) {
            $items = IserviceToolBox::getArrayInputVariable('item', []);
        }

        if (empty($default_itil_category)) {
            $default_itil_category = PluginIserviceTicket::getItilCategoryId('Citire contor');
        }

        if ($items !== null) {
            if (isset($items['printer']) && is_array($items['printer'])) {
                $accessible_printer_ids = array_keys($items['printer']);
            } else {
                $accessible_printer_ids = [];
            }
        } else {
            $accessible_printer_ids = PluginIservicePrinter::getAccessibleIds();
        }

        if (!empty($accessible_printer_ids) && count($accessible_printer_ids)) {
            $printer_condition = 'p.id in (' . implode(',', $accessible_printer_ids) . ')';
        } elseif ($accessible_printer_ids === null) {
            $printer_condition = '1';
        } else {
            $printer_condition = '1=2';
        }

        $import_button        = self::inProfileArray('tehnician', 'admin', 'super-admin') ? PluginIserviceEmaintenance::getImportControl('Importă din CSV EM', IserviceToolBox::getInputVariable('import_file', '')) : '';
        $import_button_IWM    = self::inProfileArray('tehnician', 'admin', 'super-admin') ? PluginIserviceEmaintenance::getIwmImportControl('Importă din CSV IWM') : '';
        $import_button_AVITUM = self::inProfileArray('tehnician', 'admin', 'super-admin') ? PluginIserviceEmaintenance::getAvitumImportControl('Importă din CSV AVITUM') : '';

        return [
            'name' => __('Global read counter', 'iservice'),
            'postfix' => "<div style='text-align: center'><input type='submit' class='submit' name='global_readcounter' onclick='$(this).closest(\"form\").attr(\"action\", \"ticket.form.php\");' value='" . __('Send data', 'iservice') . "' /></div>",
            'query' => "
                        SELECT p.id 
                            , p.original_name printer_name
                            , p.serial
                            , " . PluginIservicePrinter::getSerialFieldForEM('p') . " spaceless_serial
                            , p.printertypes_id
                            , p.otherserial
                            , p.daily_bk_average_field
                            , p.daily_color_average_field
                            , p.invoice_date_field
                            , p.no_invoice_field
                            , p.invoiced_total_black_field
                            , p.invoiced_total_color_field
                            , p.usage_address_field
                            , plct.effective_date_field last_effective_date
                            , COALESCE(plct.total2_black_field, 0) last_total2_black
                            , COALESCE(plct.total2_color_field, 0) last_total2_color
                            , s.id supplier_id
                            , s.name supplier_name
                        FROM glpi_plugin_iservice_printers p
                        LEFT JOIN glpi_plugin_iservice_printers_last_closed_tickets plct on plct.printers_id = p.id
                        LEFT JOIN glpi_infocoms i ON i.items_id = p.id and i.itemtype = 'Printer'
                        LEFT JOIN glpi_suppliers s ON s.id = i.suppliers_id
                        WHERE $printer_condition
                        ",
            'show_filter_buttons' => false,
            'show_limit' => false,
            'itemtype' => 'printer',
            'filters' => [
                'filter_buttons_prefix' => "<p>$import_button_IWM $import_button_AVITUM &nbsp;&nbsp;&nbsp; | &nbsp;&nbsp;&nbsp; $import_button</p><div style='color: saddlebrown;font-size:1.2em;margin-top:1em;'>Dacă nu vreți să raportați un aparat atunci ștergeți câmpul Data citire</div>",
            ],
            'columns' => [
                'printer_name' => [
                    'title' => 'Nume',
                    'format' => 'function:\GlpiPlugin\Iservice\Specialviews\GlobalReadCounter::getPrinterDisplay($row, $this->import_data);',
                ],
                'supplier_name' => [
                    'title' => 'Nume',
                    'format' => 'function:\GlpiPlugin\Iservice\Specialviews\GlobalReadCounter::getSupplierDisplay($row, $this->import_data);',
                    'link' => [
                        'href' => $CFG_GLPI['root_doc'] . '/front/supplier.form.php?id=[supplier_id]',
                        'target' => '_blank',
                        'visible' => Session::haveRight('plugin_iservice_interface_original', READ),
                    ]
                ],
                'serial' => [
                    'title' => 'Număr serie',
                    'format' => '%s',
                ],
                'otherserial' => [
                    'title' => 'Număr inventar',
                    'format' => 'function:\GlpiPlugin\Iservice\Specialviews\GlobalReadCounter::getOtherSerialDisplay($row);',
                ],
                'usage_address_field' => [
                    'title' => 'Adresa de exploatare',
                    'default_sort' => 'ASC',
                    'editable' => true,
                    'edit_settings' => [
                        'callback' => 'managePrinter',
                        'operation' => 'set_usage_address_field'
                    ],
                ],
                'last_effective_date' => [
                    'title' => 'Data lucrare<br>ultimul tichet închis',
                    'align' => 'center',
                    'style' => 'white-space: nowrap;',
                    'format' => "%s<input type='hidden' name='globalreadcounter0[printer][[id]][effective_date_old]' value='[last_effective_date]'>"
                ],
                'last_total2_black' => [
                    'title' => 'Black2<br>u. t. î.',
                    'align' => 'right',
                    'format' => "%s<input type='hidden' name='globalreadcounter0[printer][[id]][total2_black_old]' value='[last_total2_black]'>"
                ],
                'last_total2_color' => [
                    'title' => 'Color2<br>u. t. î.',
                    'align' => 'right',
                    'format' => "%s<input type='hidden' name='globalreadcounter0[printer][[id]][total2_color_old]' value='[last_total2_color]'>"
                ],
                'total2_black_current' => [
                    'title' => 'Black2 curent',
                    'align' => 'center',
                    'sortable' => false,
                    'edit_field' => [
                        'type' => self::FILTERTYPE_TEXT,
                        'name' => 'total2_black_field',
                        'empty_value' => '[last_total2_black]',
                        'min_value' => '[last_total2_black]',
                        'ignore_min_value_if_not_set' => '[name="globalreadcounter0[printer][[id]][effective_date_field]"]',
                        'label' => 'Black2 curent pentru [serial]',
                        'class' => 'agressive',
                        'style' => 'text-align:right; width: 5em;',
                        'import' => [
                            'id' => '[spaceless_serial]',
                            'index' => 'total2_black_field',
                            'error_handler' => 'function:\GlpiPlugin\Iservice\Specialviews\GlobalReadCounter::generateBadgeClickHandler();',
                            'error_text' => 'function:\GlpiPlugin\Iservice\Specialviews\GlobalReadCounter::generateErrorBadgeText();',
                            'estimate_text' => 'function:\GlpiPlugin\Iservice\Specialviews\GlobalReadCounter::generateEstimateBadgeText();',
                            'estimate_handler' => 'function:\GlpiPlugin\Iservice\Specialviews\GlobalReadCounter::generateBadgeClickHandler("estimate");'
                        ]
                    ],
                ],
                'total2_color_current' => [
                    'title' => 'Color2 curent',
                    'align' => 'center',
                    'sortable' => false,
                    'edit_field' => [
                        'type' => self::FILTERTYPE_TEXT,
                        'name' => 'total2_color_field',
                        'empty_value' => '[last_total2_color]',
                        'min_value' => '[last_total2_color]',
                        'ignore_min_value_if_not_set' => '[name="globalreadcounter0[printer][[id]][effective_date_field]"]',
                        'label' => 'Color2 curent pentru [serial]',
                        'class' => 'agressive',
                        'style' => 'text-align:right; width: 5em;',
                        'import' => [
                            'id' => '[spaceless_serial]',
                            'index' => 'total2_color_field',
                            'error_handler' => 'function:\GlpiPlugin\Iservice\Specialviews\GlobalReadCounter::generateBadgeClickHandler();',
                            'error_text' => 'function:\GlpiPlugin\Iservice\Specialviews\GlobalReadCounter::generateErrorBadgeText();',
                            'estimate_text' => 'function:\GlpiPlugin\Iservice\Specialviews\GlobalReadCounter::generateEstimateBadgeText();',
                            'estimate_handler' => 'function:\GlpiPlugin\Iservice\Specialviews\GlobalReadCounter::generateBadgeClickHandler("estimate");'
                        ],
                        'post_widget' => '
                            <script>
                                if (![' . PluginIservicePrinter::ID_COLOR_TYPE . ', ' . PluginIservicePrinter::ID_PLOTTER_TYPE . '].includes([printertypes_id])) {
                                    $("[name=\'globalreadcounter0\\\\[printer\\\\]\\\\[[id]\\\\]\\\\[total2_color_field\\\\]\']").parent().children().hide();
                                }
                            </script>',
                    ],
                ],
                'data_luc_current' => [
                    'title' => 'Data citire',
                    'align' => 'center',
                    'sortable' => false,
                    'edit_field' => [
                        'type' => self::FILTERTYPE_DATETIME,
                        'name' => 'effective_date_field',
                        'empty_value' => date('Y-m-d H:i:s'),
                        'min_value' => '[last_effective_date]',
                        'label' => 'Data citire pentru [serial]',
                        'import' => [
                            'id' => '[spaceless_serial]',
                            'index' => 'effective_date_field',
                            'error_handler' => 'function:\GlpiPlugin\Iservice\Specialviews\GlobalReadCounter::generateBadgeClickHandler();',
                            'error_text' => 'function:\GlpiPlugin\Iservice\Specialviews\GlobalReadCounter::generateErrorBadgeText();',
                            'minimum_error_handler' => 'function:\GlpiPlugin\Iservice\Specialviews\GlobalReadCounter::generateBadgeClickHandler();',
                            'minimum_error_hint' => 'function:\GlpiPlugin\Iservice\Specialviews\GlobalReadCounter::generateEstimateBadgeText("Click pentru a estima toate valorile");'
                        ]
                    ],
                ],
                'nu_inchide' => [
                    'title' => 'Nu închide',
                    'align' => 'center',
                    'visible' => self::inProfileArray('tehnician', 'admin', 'super-admin'),
                    'edit_field' => [
                        'type' => self::FILTERTYPE_CHECKBOX,
                        'name' => '_dont_close',
                    ],
                ],
                'without_paper_field' => [
                    'title' => 'Fără hârtii',
                    'align' => 'center',
                    'visible' => self::inProfileArray('tehnician', 'admin', 'super-admin'),
                    'edit_field' => [
                        'type' => self::FILTERTYPE_CHECKBOX,
                        'name' => '_without_papers',
                        'default' => 1,
                    ],
                ],
                'no_travel_field' => [
                    'title' => 'Fără deplasare',
                    'align' => 'center',
                    'visible' => self::inProfileArray('tehnician', 'admin', 'super-admin'),
                    'edit_field' => [
                        'type' => self::FILTERTYPE_CHECKBOX,
                        'name' => '_without_moving',
                        'default' => 1,
                    ],
                ],
                'itilcategories_id' => [
                    'title' => 'Categorie',
                    'align' => 'center',
                    'sortable' => false,
                    'edit_field' => [
                        'type' => self::FILTERTYPE_SELECT,
                        'glpi_class' => 'ITILCategory',
                        'name' => 'itilcategories_id',
                        'class' => 'wide',
                        'empty_value' => $default_itil_category,
                    ],
                ],
            ],
        ];
    }

}

<?php

// Imported from iService2, needs refactoring. Original file: "Partners.php".
namespace GlpiPlugin\Iservice\Views;

use GlpiPlugin\Iservice\Views\View;

class Partners extends View
{

    public static $rightname = 'plugin_iservice_view_partners';

    public static $icon = 'ti ti-friends';

    public static function getName(): string
    {
        return _t('Due partners');
    }

    public static function getAdditionalMenuOptions()
    {
        return [
            'sortOrder' => 60,
        ];
    }

    public static function getNumePartenerDisplay($row_data): string
    {
        if ($row_data['printer_count'] === 0) {
            if ($row_data['deleted_printer_count'] === 0) {
                $class = " style='color:red'";
            } else {
                $class = " style='color:orange'";
            }
        } else {
            $class = '';
        }
        return "<a href='views.php?view=ClientInvoices&clientinvoices0[partner_id]=$row_data[id]' target='_blank'$class>$row_data[Nume_Partener]</a>";
    }

    protected function getSettings(): array
    {
        return [
            'name' => _t('Due partner list'),
            'query' => "
                SELECT 
                      s.id id
                    , s.name Nume_Partener
                    , d.date Data_Ultima_Contactare
                    , sc.magic_link_field partner_magic_link
                    , t1.data Data_Ultima_Plata
                    , t1.sum Valoare_Ulitma_Plata
                    , t.codbenef
                    , t2.Numar_Facturi_Neplatite
                    , t.Data_Ultima_Factura
                    , t.Valoare_Scadenta
                    , t3.Numar_Facturi_Neplatite Numar_Facturi_Neplatite2
                    , TIMESTAMPDIFF(DAY, t1.data, NOW()) Zile_De_La_Ultima_Plata
                    , COALESCE(t4.printer_count, 0) printer_count 
                    , COALESCE(t5.printer_count, 0) deleted_printer_count 
                FROM (SELECT
                          codbenef
                        , count(codbenef) Numar_Facturi_Neplatite
                        , MAX(datafac) Data_Ultima_Factura
                        , ROUND(SUM(valinc-valpla),2) Valoare_Scadenta
                      FROM hmarfa_facturi 
                      WHERE (codl = 'F' OR stare like 'V%') AND tip like 'TF%'
                      GROUP BY codbenef) t
                LEFT JOIN (SELECT
                                codbenef
                              , count(codbenef) Numar_Facturi_Neplatite
                            FROM hmarfa_facturi 
                            WHERE (codl = 'F' OR stare like 'V%') AND tip like 'TF%'
                              AND valinc-valpla > 0
                            GROUP BY codbenef) t2 ON t2.codbenef = t.codbenef
                LEFT JOIN (SELECT
                                pl1.partener
                              , pl1.data
                              , ROUND(SUM(pl1.suma),2) sum
                            FROM hmarfa_incpla pl1
                            JOIN (SELECT
                                      partener
                                    , MAX(data) data_ultima_plata
                                  FROM hmarfa_incpla
                                  GROUP BY partener) pl2 ON pl2.partener = pl1.partener AND pl1.data = pl2.data_ultima_plata
                            GROUP BY pl1.partener, pl1.data) t1 ON t1.partener = t.codbenef
                LEFT JOIN (SELECT
                                codbenef
                              , COUNT(codbenef) Numar_Facturi_Neplatite
                            FROM hmarfa_facturi
                            WHERE (codl = 'F' OR stare like 'V%') AND tip like 'TF%'
                              AND valinc-valpla > 0 AND dscad < NOW()
                            GROUP BY codbenef) t3 ON t1.partener = t3.codbenef
                LEFT JOIN glpi_plugin_fields_suppliersuppliercustomfields sc ON sc.hmarfa_code_field = t.codbenef and sc.itemtype = 'Supplier'
                LEFT JOIN glpi_suppliers s ON s.id = sc.items_id and s.is_deleted = 0
                LEFT JOIN (SELECT suppliers_id, count(*) printer_count
                           FROM glpi_infocoms ic
                           JOIN glpi_suppliers s ON s.id = ic.items_id and s.is_deleted = 0 and ic.itemtype = 'Printer'
                           GROUP BY ic.suppliers_id
                          ) t4 ON t4.suppliers_id = s.id
                LEFT JOIN (SELECT suppliers_id, count(*) printer_count
                           FROM glpi_infocoms ic
                           JOIN glpi_suppliers s ON s.id = ic.items_id and s.is_deleted = 1 and ic.itemtype = 'Printer'
                           GROUP BY ic.suppliers_id
                          ) t5 ON t5.suppliers_id = s.id
                LEFT JOIN (SELECT items_id id, MAX(date) date
                      FROM glpi_plugin_iservice_downloads
                            WHERE downloadtype = 'partner_contacted'
                            GROUP BY items_id) d ON d.id = s.id
                WHERE s.name LIKE '[partener]'
                    AND (t1.sum IS NULL OR t1.sum >= [val_ult_pla])
                    AND (t1.data IS NULL OR t1.data <= '[ult_pla]')
                    AND (t1.data IS NULL OR TIMESTAMPDIFF(DAY, t1.data, NOW()) > [zile_ult_pla])
                    AND t.Valoare_Scadenta > [val_scad]
                    AND t.Data_Ultima_Factura <= '[ult_fact]'
                    AND (t2.Numar_Facturi_Neplatite IS NULL OR t2.Numar_Facturi_Neplatite > [nr_fac_nepla])
                    AND (t3.Numar_Facturi_Neplatite IS NULL OR t3.Numar_Facturi_Neplatite > [nr_fac_nepla2])
                    AND (d.date IS NULL OR d.date <= '[ult_cont]')
                ",
            'default_limit' => 25,
            'filters' => [
                'filter_buttons_prefix' =>
                                " <input type='submit' class='submit noprint' name='filter' value='Toți partenerii' onclick='changeValByName(\"partners0[nr_fac_nepla]\", -1);changeValByName(\"partners0[nr_fac_nepla2]\", -1);changeValByName(\"partners0[val_scad]\", -1);changeValByName(\"partners0[zile_ult_pla]\", -1);'/>"
                                . " <input type='submit' class='submit noprint' name='filter' value='Partenerii cu datorie' onclick='changeValByName(\"partners0[nr_fac_nepla]\", 0);changeValByName(\"partners0[nr_fac_nepla2]\", 0);changeValByName(\"partners0[val_scad]\", 0);'/>",
                'partener' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => '',
                    'format' => '%%%s%%',
                    'header' => 'Nume_Partener',
                ],
                'ult_cont' => [
                    'type' => self::FILTERTYPE_DATE,
                    'header' => 'Data_Ultima_Contactare',
                    'header_caption' => '< ',
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => date('Y-m-d'),
                ],
                'nr_fac_nepla' => [
                    'type' => self::FILTERTYPE_INT,
                    'caption' => '',
                    'format' => '%d',
                    'default' => 0,
                    'empty_value' => 0,
                    'style' => 'text-align:right;width:5em;',
                    'header' => 'Numar_Facturi_Neplatite',
                    'header_caption' => '> ',
                ],
                'nr_fac_nepla2' => [
                    'type' => self::FILTERTYPE_INT,
                    'caption' => '',
                    'format' => '%d',
                    'default' => 0,
                    'empty_value' => 0,
                    'style' => 'text-align:right;width:5em;',
                    'header' => 'Numar_Facturi_Neplatite2',
                    'header_caption' => '> ',
                ],
                'ult_fact' => [
                    'type' => self::FILTERTYPE_DATE,
                    'header' => 'Data_Ultima_Factura',
                    'header_caption' => '< ',
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => date('Y-m-d'),
                ],
                'val_scad' => [
                    'type' => self::FILTERTYPE_INT,
                    'caption' => '',
                    'format' => '%d',
                    'default' => 0,
                    'empty_value' => 0,
                    'style' => 'text-align:right;width:5em;',
                    'header' => 'Valoare_Scadenta',
                    'header_caption' => '> ',
                ],
                'ult_pla' => [
                    'type' => self::FILTERTYPE_DATE,
                    'header' => 'Data_Ultima_Plata',
                    'header_caption' => '< ',
                    'format' => 'Y-m-d 23:59:59',
                    'empty_value' => date('Y-m-d'),
                ],
                'zile_ult_pla' => [
                    'type' => self::FILTERTYPE_INT,
                    'header' => 'Zile_De_La_Ultima_Plata',
                    'header_caption' => '> ',
                    'format' => '%d',
                    'empty_value' => '0',
                    'style' => 'text-align:right;width:5em;',
                ],
                'val_ult_pla' => [
                    'type' => self::FILTERTYPE_INT,
                    'caption' => '',
                    'format' => '%d',
                    'default' => 0,
                    'empty_value' => 0,
                    'style' => 'text-align:right;width:5em;',
                    'header' => 'Valoare_Ulitma_Plata',
                    'header_caption' => '>= ',
                ],
            ],
            'columns' => [
                'Nume_Partener' => [
                    'title' => 'Partener',
                    'sort_default_dir' => 'DESC',
                    'tooltip' => _t('See invoice list'),
                    'format' => 'function:default',
                ],
                'Data_Ultima_Contactare' => [
                    'title' => 'Data ultima contactare',
                    'align' => 'center',
                    'style' => 'white-space: nowrap;',
                    'sort_default_dir' => 'DESC',
                ],
                'Numar_Facturi_Neplatite' => [
                    'title' => 'Număr facturi<br>neplătite',
                    'align' => 'center',
                    'sort_default_dir' => 'DESC',
                ],
                'Numar_Facturi_Neplatite2' => [
                    'title' => 'Număr facturi neplătite<br>cu scadență depășită',
                    'align' => 'center',
                    'sort_default_dir' => 'DESC',
                ],
                'Data_Ultima_Factura' => [
                    'title' => 'Data ultima factură',
                    'align' => 'center',
                    'style' => 'white-space: nowrap;',
                    'sort_default_dir' => 'DESC',
                ],
                'Valoare_Scadenta' => [
                    'title' => 'Valoare facturi neplătite',
                    'align' => 'right',
                    'sort_default_dir' => 'DESC',
                ],
                'Data_Ultima_Plata' => [
                    'title' => 'Data ultimei plăți',
                    'align' => 'center',
                    'style' => 'white-space: nowrap;',
                    'sort_default_dir' => 'DESC',
                    'default_sort' => 'DESC',
                ],
                'Zile_De_La_Ultima_Plata' => [
                    'title' => 'Zile de la<br>ultima plată',
                    'align' => 'center',
                    'style' => 'white-space: nowrap;',
                    'sort_default_dir' => 'DESC',
                ],
                'Valoare_Ulitma_Plata' => [
                    'title' => 'Valoare<br>ultima plată',
                    'align' => 'right',
                    'sort_default_dir' => 'DESC',
                ],
            ],
        ];
    }

}

<?php

namespace GlpiPlugin\Iservice\Views;

// Imported from iService2, needs refactoring. Original file: "Skipped_Payment.php".
class SkippedPayment extends View
{

    public static $rightname = 'plugin_iservice_view_skipped_payment';

    public static $icon = 'ti ti-credit-card-off';

    public static function getName(): string
    {
        return _t('Clients with skipped payment');
    }

    protected function getSettings(): array
    {
        return [
            'name' => self::getName(),
            'query' => "
						SELECT
								MAX(f.valinc - f.valpla) as restpla
							, nepla.min as min_nepla
							, pla.max as max_pla
							, e.initiale as nume_part
							, CONCAT(u.realname, ' ', u.firstname) as tech_num
						    , scf.items_id as supplier_id
						FROM (SELECT codbenef FROM {$this->table_prefix}hmarfa_facturi WHERE tip LIKE 'TF%' AND (codl = 'F' OR stare like 'V%') GROUP BY codbenef) b
						LEFT JOIN {$this->table_prefix}hmarfa_firme e ON e.cod = b.codbenef
						LEFT JOIN (SELECT codbenef, MIN(datafac) as min, MAX(datafac) as max 
											 FROM {$this->table_prefix}hmarfa_facturi 
											 WHERE tip LIKE 'TF%' AND (codl = 'F' OR stare like 'V%')
												 AND valinc - valpla = 0
												 AND valinc > 0
											 GROUP BY codbenef)
							pla on pla.codbenef = b.codbenef
						LEFT JOIN (SELECT codbenef, MIN(datafac) as min, MAX(datafac) as max
											 FROM {$this->table_prefix}hmarfa_facturi
											 WHERE tip LIKE 'TF%' AND (codl = 'F' OR stare like 'V%')
											   AND valinc - valpla > 0
											 GROUP BY codbenef)
							nepla on nepla.codbenef = b.codbenef
						LEFT JOIN {$this->table_prefix}hmarfa_facturi f on f.tip LIKE 'TF%' AND (codl = 'F' OR stare like 'V%') AND f.codbenef = b.codbenef AND f.datafac = nepla.min
						LEFT JOIN glpi_users u on SUBSTRING(u.name FROM 1 FOR 3) = SUBSTRING(f.nrcmd FROM 1 FOR 3)
						LEFT JOIN glpi_plugin_fields_suppliersuppliercustomfields scf on scf.hmarfa_code_field = e.cod
						WHERE nepla.min < pla.max
						GROUP BY b.codbenef
						",
            'default_limit' => 100,
            'columns' => [
                'min_nepla' => [
                    'title' => 'Data primei facturi neplătite',
                    'align' => 'center',
                    'default_sort' => 'ASC',
                ],
                'restpla' => [
                    'title' => 'Rest de plată',
                    'align' => 'right',
                    'format' => '%.2f RON',
                ],
                'max_pla' => [
                    'title' => 'Data ultimei facturi plătite',
                    'align' => 'center',
                ],
                'nume_part' => [
                    'title' => 'Nume partener',
                    'link' => [
                        'href' => 'views.php?view=ClientInvoices&clientinvoices0[partner_id]=[supplier_id]',
                        'title' => _t('See invoice list'),
                        'target' => '_blank',
                    ],
                ],
            ],
        ];
    }

}

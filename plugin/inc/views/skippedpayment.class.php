<?php

namespace GlpiPlugin\Iservice\Views;

use \Session;

// Imported from iService2, needs refactoring. Original file: "Skipped_Payment.php".
class SkippedPayment extends View
{

    public static $order = 20;

    public static $rightname = 'entity';

    public static function getMenuName(): string
    {
        return self::getName();
    }

    public static function getMenuContent(): array
    {
        if (!Session::haveRight(self::$rightname, READ)) {
            return [];
        }

        return [
            'title' => self::getMenuName(),
            'page' => '/plugins/iservice/front/views.php?view=' . self::class,
            'icon'  => 'fa-fw ti ti-credit-card-off',
        ];
    }

    public static function getName(): string
    {
        return __('Clienți cu facturi omise', 'iservice');
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
                ],
            ],
        ];
    }

}

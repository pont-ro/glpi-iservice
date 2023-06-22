<?php

namespace GlpiPlugin\Iservice\Views;

use \Session;

// Imported from iService2, needs refactoring. Original file: "Price_List.php".
class PriceList extends View
{

    public static $rightname = 'entity';

    public static $icon = 'ti ti-receipt-2';

    public static function getName(): string
    {
        return __('Price List', 'iservice');
    }

    protected function getSettings(): array
    {
        return [
            'name' => self::getName(),
            'query' => "
						SELECT
								COD_PRODUC
							, COD_ECH
							, COD
							, DENUM
							, DESCR
							, UM
							, GRUPA
							, PVINV
							, MONEDA
							, PVIN
							, P_PRETURI
							, P_PRET1
							, P_PRET2
							, P_PRET3
						FROM {$this->table_prefix}hmarfa_nommarfa n
						WHERE COD LIKE '[COD]'
						  AND COD_ECH LIKE '[COD_ECH]'
						  AND COD_PRODUC LIKE '[COD_PRODUC]'
						  AND DENUM LIKE '[DENUM]'
							AND GRUPA LIKE '[GRUPA]'
						",
            'default_limit' => 50,
            'filters' => [
                'COD_PRODUC' => [
                    'type' => 'text',
                    'caption' => 'COD_PRODUC',
                    'format' => '%%%s%%',
                    'header' => 'COD_PRODUC',
                ],
                'COD_ECH' => [
                    'type' => 'text',
                    'caption' => 'COD_ECH',
                    'format' => '%%%s%%',
                    'header' => 'COD_ECH',
                ],
                'COD' => [
                    'type' => 'text',
                    'caption' => 'COD',
                    'format' => '%%%s%%',
                    'header' => 'COD',
                ],
                'DENUM' => [
                    'type' => 'text',
                    'caption' => 'DENUM',
                    'format' => '%%%s%%',
                    'header' => 'DENUM',
                ],
                'GRUPA' => [
                    'type' => 'text',
                    'caption' => 'GRUPA',
                    'format' => '%%%s%%',
                    'header' => 'GRUPA',
                ],
            ],
            'columns' => [
                'COD_PRODUC' => ['title' => 'COD_PRODUC',],
                'COD_ECH' => ['title' => 'COD_ECH',],
                'COD' => ['title' => 'COD',],
                'DENUM' => ['title' => 'DENUM',],
                'DESCR' => ['title' => 'DESCR',],
                'UM' => ['title' => 'UM',],
                'GRUPA' => ['title' => 'GRUPA',],
                'PVINV' => [
                    'title' => 'PVINV',
                    'align' => 'right',
                    'format' => '%.2f',
                ],
                'MONEDA' => [
                    'title' => 'MONEDA',
                    'align' => 'right',
                ],
                'PVIN' => [
                    'title' => 'PVIN',
                    'align' => 'right',
                    'format' => '%.2f',
                ],
                'P_PRETURI' => ['title' => 'P_PRETURI',],
                'P_PRET1' => ['title' => 'P_PRET1',],
                'P_PRET2' => ['title' => 'P_PRET2',],
                'P_PRET3' => ['title' => 'P_PRET3',],
            ],
        ];
    }

}

<?php

// Imported from iService2, needs refactoring.
class PluginIserviceView_Price_List extends PluginIserviceView {

    static $order = 70;

    static function getName() {
        return 'Lista de prețuri';
    }

    protected function getSettings() {
        return array(
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
            'filters' => array(
                'COD_PRODUC' => array(
                    'type' => 'text',
                    'caption' => 'COD_PRODUC',
                    'format' => '%%%s%%',
                    'header' => 'COD_PRODUC',
                ),
                'COD_ECH' => array(
                    'type' => 'text',
                    'caption' => 'COD_ECH',
                    'format' => '%%%s%%',
                    'header' => 'COD_ECH',
                ),
                'COD' => array(
                    'type' => 'text',
                    'caption' => 'COD',
                    'format' => '%%%s%%',
                    'header' => 'COD',
                ),
                'DENUM' => array(
                    'type' => 'text',
                    'caption' => 'DENUM',
                    'format' => '%%%s%%',
                    'header' => 'DENUM',
                ),
                'GRUPA' => array(
                    'type' => 'text',
                    'caption' => 'GRUPA',
                    'format' => '%%%s%%',
                    'header' => 'GRUPA',
                ),
            ),
            'columns' => array(
                'COD_PRODUC' => array('title' => 'COD_PRODUC',),
                'COD_ECH' => array('title' => 'COD_ECH',),
                'COD' => array('title' => 'COD',),
                'DENUM' => array('title' => 'DENUM',),
                'DESCR' => array('title' => 'DESCR',),
                'UM' => array('title' => 'UM',),
                'GRUPA' => array('title' => 'GRUPA',),
                'PVINV' => array(
                    'title' => 'PVINV',
                    'align' => 'right',
                    'format' => '%.2f',
                ),
                'MONEDA' => array(
                    'title' => 'MONEDA',
                    'align' => 'right',
                ),
                'PVIN' => array(
                    'title' => 'PVIN',
                    'align' => 'right',
                    'format' => '%.2f',
                ),
                'P_PRETURI' => array('title' => 'P_PRETURI',),
                'P_PRET1' => array('title' => 'P_PRET1',),
                'P_PRET2' => array('title' => 'P_PRET2',),
                'P_PRET3' => array('title' => 'P_PRET3',),
            ),
        );
    }

}

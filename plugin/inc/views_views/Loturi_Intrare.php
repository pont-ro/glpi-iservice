<?php

// Imported from iService2, needs refactoring.
class PluginIserviceView_Loturi_Intrare extends PluginIserviceView {
    
    static $order = 50;

    static function getName() {
        return 'Loturi de intrare';
    }

    protected function getSettings() {
        return array(
            'name' => self::getName(),
            'query' => "
								SELECT
										l.nrtran
									, t.dataint
									, l.codmat
									, l.grupa
									, l.pcont
									, l.stoci
									, f.initiale as denumire_partener
									, n.denum as denumire_material
								FROM {$this->table_prefix}hmarfa_lotm l
								LEFT JOIN {$this->table_prefix}hmarfa_tran t ON t.nrtran = l.nrtran
								LEFT JOIN {$this->table_prefix}hmarfa_firme f ON f.cod = t.furnizor
								LEFT JOIN {$this->table_prefix}hmarfa_nommarfa n ON n.cod = l.codmat
								WHERE (f.initiale LIKE '[denum_part]' or f.denum is null)
									AND t.datadoc >= '[start_date]'
									AND t.datadoc <= '[end_date]'
									AND (l.nrtran LIKE '[nrtran]' or l.nrtran is null)
									AND (l.codmat LIKE '[codmat]' or l.codmat is null)
									AND (l.grupa LIKE '[grupa]' or l.grupa is null)
									AND (n.denum LIKE '[denum_mat]' or n.denum is null)
								",
            'default_limit' => 25,
            'filters' => array(
                'start_date' => array(
                    'type' => 'date',
                    'caption' => 'Data factură',
                    'format' => 'Y-m-d',
                    'empty_value' => '2000-01-01',
                    'pre_widget' => "{$this->getWidgets()[self::WIDGET_LAST_6_MONTH]} {$this->getWidgets()[self::WIDGET_LAST_MONTH]} {$this->getWidgets()[self::WIDGET_THIS_MONTH]} ",
                ),
                'end_date' => array(
                    'type' => 'date',
                    'caption' => ' - ',
                    'format' => 'Y-m-d',
                    'empty_value' => date('Y-m-d'),
                ),
                'nrtran' => array(
                    'type' => 'text',
                    'caption' => 'Nrtran',
                    'format' => '%%%s%%',
                    'header' => "nrtran",
                ),
                'codmat' => array(
                    'type' => 'text',
                    'caption' => 'Cod material',
                    'format' => '%%%s%%',
                    'header' => 'codmat',
                ),
                'grupa' => array(
                    'type' => 'text',
                    'caption' => 'Grupa',
                    'format' => '%%%s%%',
                    'header' => 'grupa',
                ),
                'denum_mat' => array(
                    'type' => 'text',
                    'caption' => 'Denumire material',
                    'format' => '%%%s%%',
                    'header' => 'denumire_material',
                ),
                'denum_part' => array(
                    'type' => 'text',
                    'caption' => 'Denumire furnizor',
                    'format' => '%%%s%%',
                    'header' => 'denumire_partener',
                ),
            ),
            'columns' => array(
                'nrtran' => array(
                    'title' => 'Nrtran'
                ),
                'dataint' => array(
                    'title' => 'Data intrării',
                    'default_sort' => 'DESC',
                    'style' => 'white-space: nowrap;'
                ),
                'codmat' => array(
                    'title' => 'Cod material',
                ),
                'grupa' => array(
                    'title' => 'Grupa',
                ),
                'pcont' => array(
                    'title' => 'Pcont',
                    'align' => 'right',
                    'format' => '%.2f',
                ),
                'stoci' => array(
                    'title' => 'Stoci',
                ),
                'denumire_material' => array(
                    'title' => 'Denumire material',
                ),
                'denumire_partener' => array(
                    'title' => 'Denumire furnizor',
                ),
            ),
        );
    }

}

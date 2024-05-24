<?php

namespace GlpiPlugin\Iservice\Views;

// Imported from iService2, needs refactoring. Original file: "Loturi_Intrare.php".
class InboundLots extends View
{

    public static $rightname = 'plugin_iservice_view_inbound_lots';

    public static $icon = 'ti ti-transfer-in';

    public static function getName(): string
    {
        return __('Inbound Lots', 'iservice');
    }

    protected function getSettings(): array
    {
        return [
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
            'filters' => [
                'start_date' => [
                    'type' => 'date',
                    'caption' => 'Data factură',
                    'class' => 'mx-1',
                    'format' => 'Y-m-d',
                    'empty_value' => '2000-01-01',
                    'pre_widget' => "{$this->getWidgets()[self::WIDGET_LAST_6_MONTH]} {$this->getWidgets()[self::WIDGET_LAST_MONTH]} {$this->getWidgets()[self::WIDGET_THIS_MONTH]} ",
                ],
                'end_date' => [
                    'type' => 'date',
                    'caption' => ' - ',
                    'format' => 'Y-m-d',
                    'empty_value' => date('Y-m-d'),
                ],
                'nrtran' => [
                    'type' => 'text',
                    'caption' => 'Nrtran',
                    'format' => '%%%s%%',
                    'header' => "nrtran",
                ],
                'codmat' => [
                    'type' => 'text',
                    'caption' => 'Cod material',
                    'format' => '%%%s%%',
                    'header' => 'codmat',
                ],
                'grupa' => [
                    'type' => 'text',
                    'caption' => 'Grupa',
                    'format' => '%%%s%%',
                    'header' => 'grupa',
                ],
                'denum_mat' => [
                    'type' => 'text',
                    'caption' => 'Denumire material',
                    'format' => '%%%s%%',
                    'header' => 'denumire_material',
                ],
                'denum_part' => [
                    'type' => 'text',
                    'caption' => 'Denumire furnizor',
                    'format' => '%%%s%%',
                    'header' => 'denumire_partener',
                ],
            ],
            'columns' => [
                'nrtran' => [
                    'title' => 'Nrtran'
                ],
                'dataint' => [
                    'title' => 'Data intrării',
                    'default_sort' => 'DESC',
                    'style' => 'white-space: nowrap;'
                ],
                'codmat' => [
                    'title' => 'Cod material',
                ],
                'grupa' => [
                    'title' => 'Grupa',
                ],
                'pcont' => [
                    'title' => 'Pcont',
                    'align' => 'right',
                    'format' => '%.2f',
                ],
                'stoci' => [
                    'title' => 'Stoci',
                ],
                'denumire_material' => [
                    'title' => 'Denumire material',
                ],
                'denumire_partener' => [
                    'title' => 'Denumire furnizor',
                ],
            ],
        ];
    }

}

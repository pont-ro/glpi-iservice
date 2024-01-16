<?php

namespace GlpiPlugin\Iservice\Views;

use GlpiPlugin\Iservice\Views\View;
use Session;

// Imported from iService2, needs refactoring. Original file: "Contracts.php".
class Contracts extends View
{
    public static $rightname = 'plugin_iservice_view_contracts';

    public static $icon = 'fa-fw ti ti-writing-sign';

    static function getDocumentCountDisplay($row_data)
    {
        global $CFG_GLPI;
        $title = "";
        foreach (explode(',', $row_data['documents']) as $row) {
            if (empty($row)) {
                continue;
            }

            $file_info = explode('|', $row);
            $title    .= "<a href=\"$CFG_GLPI[root_doc]/front/document.send.php?docid=$file_info[0]\" alt=\"$file_info[1]\" title=\"$file_info[1]\" target=\"_blank\">$file_info[2]</a><br>";
        }

        return "<span class='has-bootstrap-tooltip pointer' title='$title' data-new-title='Click pentru a vede Documentele' data-placement='right'>$row_data[document_count]</a>";
    }

    public static function getName(): string
    {
        return _n('Contract', 'Contracts', Session::getPluralNumber());
    }

    protected function getSettings(): array
    {
        global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
        return [
            'name' => self::getName(),
            'prefix' => Session::haveRight('plugin_iservice_contract', CREATE) ? ("<a class='vsubmit noprint' href='contract.form.php'>" . __('Add') . " " . __('Contract') . "</a>") : '',
            'query' => "
                        SELECT
                              c.id contract_id
                            , c.name contract_name
                            , ct.name contract_type
                            , c.num contract_number
                            , c.monthly_fee_field contract_monthly_tarif
                            , c.currency_field contract_rate
                            , c.included_copies_bk_field contract_included_bk
                            , c.included_copies_col_field contract_included_cl
                            , c.copy_price_bk_field contract_tarif_bk
                            , c.copy_price_col_field contract_tarif_cl
                            , c.copy_price_divider_field contract_divider_pu
                            , COUNT(DISTINCT ci.items_id) item_count
                            , GROUP_CONCAT(CONCAT(p.id, ' - ', p.name) SEPARATOR '\n') items
                            , COUNT(DISTINCT di.items_id) document_count
                            , GROUP_CONCAT(DISTINCT(CONCAT(d.id, '|', d.filepath, '|', d.name)) SEPARATOR ',') documents
                        FROM glpi_plugin_iservice_contracts c
                        LEFT JOIN glpi_contracttypes ct ON ct.id = c.contracttypes_id
                        LEFT JOIN glpi_contracts_items ci on ci.contracts_id = c.id and ci.itemtype = 'Printer'
                        LEFT JOIN glpi_plugin_iservice_printers p on p.id = ci.items_id
                        LEFT JOIN glpi_documents_items di on di.items_id = c.id and di.itemtype = 'Contract'
                        LEFT JOIN glpi_documents d on d.id = di.documents_id
                        WHERE c.is_deleted = 0 and coalesce(p.is_deleted, 0) = 0 and coalesce(d.is_deleted, 0) = 0
                            AND c.id LIKE '[contract_id]'
                            AND c.name LIKE '[contract_name]'
                            AND c.num LIKE '[contract_number]'
                            [contract_type]
                        GROUP BY c.id
                        ",
            'default_limit' => 30,
            'filters' => [
                'contract_id' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'ID',
                    'format' => '%%%s%%',
                    'header' => 'contract_id',
                ],
                'contract_name' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'Nume',
                    'format' => '%%%s%%',
                    'header' => 'contract_name',
                ],
                'contract_number' => [
                    'type' => self::FILTERTYPE_TEXT,
                    'caption' => 'Număr contract',
                    'format' => '%%%s%%',
                    'header' => 'contract_number',
                ],
                'contract_type' => [
                    'type' => self::FILTERTYPE_SELECT,
                    'glpi_class' => 'ContractType',
                    'caption' => 'Tip contract',
                    'format' => 'AND ct.id = %d',
                    'header' => 'contract_type',
                ],
            ],
            'columns' => [
                'contract_id' => [
                    'title' => 'ID',
                    'align' => 'center',
                    'default_sort' => 'DESC',
                    'link' => [
                        'href' => $CFG_GLPI['root_doc'] . '/front/contract.form.php?id=[contract_id]',
                        'target' => '_blank',
                    ],
                ],
                'contract_number' => [
                    'title' => 'Numar contract',
                ],
                'contract_name' => [
                    'title' => 'Nume contract',
                    'link' => [
                        'href' => $CFG_PLUGIN_ISERVICE['root_doc'] . '/front/contract.form.php?contract_id=[contract_id]',
                        'target' => '_blank',
                    ],
                ],
                'contract_type' => [
                    'title' => 'Tip contract',
                ],
                'contract_monthly_tarif' => [
                    'title' => 'Tarif lunar',
                    'align' => 'right',
                    'format' => '%.2f',
                ],
                'contract_rate' => [
                    'title' => 'Curs de calcul',
                    'align' => 'right',
                    'format' => '%.2f',
                ],
                'contract_included_bk' => [
                    'title' => 'Copii bk incluse',
                    'align' => 'right',
                ],
                'contract_included_cl' => [
                    'title' => 'Copii cl incluse',
                    'align' => 'right',
                ],
                'contract_tarif_bk' => [
                    'title' => 'Tarif copie bk',
                    'align' => 'right',
                    'format' => '%.4f',
                ],
                'contract_tarif_cl' => [
                    'title' => 'Tarif copie cl',
                    'align' => 'right',
                    'format' => '%.4f',
                ],
                'contract_divider_pu' => [
                    'title' => 'Divizor PU',
                    'align' => 'right',
                ],
                'item_count' => [
                    'title' => 'Copiatoare',
                    'align' => 'center',
                    'tooltip' => '[items]',
                    'link' => [
                        'href' => $CFG_PLUGIN_ISERVICE['root_doc'] . '/front/views.php?view=Printers&cid=[contract_id]&contract_name=[contract_name]',
                        'target' => '_blank',
                    ],
                ],
                'document_count' => [
                    'title' => 'Documente',
                    'align' => 'center',
                    'format' => 'function:default', // this will call PluginIserviceView_Contracts::getDocumentCountDisplay($row);
                ],
            ],
        ];
    }

}

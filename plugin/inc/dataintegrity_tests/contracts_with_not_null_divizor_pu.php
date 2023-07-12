<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        SELECT c.id, ccf.copy_price_bk_field, ccf.copy_price_col_field
        FROM glpi_contracts c
        LEFT JOIN glpi_plugin_fields_contractcontractcustomfields ccf ON ccf.items_id = c.id and ccf.itemtype = 'Contract'
        WHERE
              (
                  FLOOR(ccf.copy_price_bk_field * 100) != ccf.copy_price_bk_field * 100
                      OR
                  FLOOR(ccf.copy_price_col_field * 100) != ccf.copy_price_col_field * 100
              )
          AND COALESCE(ccf.copy_price_divider_field, 0) = 0
          AND c.is_deleted = 0
        ",
    'test' => [
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no contracts with wrong "Divizor PU copie"'
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} contracts with empty "Divizor PU copie" and "Tarif copie black/color" with more than two decimal places',
            'iteration_text' => "Contract <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/contract.form.php?contract_id=[id]' target='_blank'>[id]</a> has empty \"Divizor PU copie\" but \"Tarif copie black\" [copy_price_bk_field] and \"Tarif copie color\" [copy_price_col_field]",
        ],
    ],
];

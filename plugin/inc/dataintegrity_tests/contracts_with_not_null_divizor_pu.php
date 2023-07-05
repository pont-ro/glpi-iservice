<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        SELECT c.id, ccf.tarif_cop_bl, ccf.tarif_cop_col1
        FROM glpi_contracts c
        LEFT JOIN glpi_plugin_fields_contractcustomfields ccf ON ccf.items_id = c.id and ccf.itemtype = 'Contract'
        WHERE
              (
                  FLOOR(ccf.tarif_cop_bl * 100) != ccf.tarif_cop_bl * 100
                      OR
                  FLOOR(ccf.tarif_cop_col1 * 100) != ccf.tarif_cop_col1 * 100
              )
          AND COALESCE(ccf.divizor_pu, 0) = 0
          AND c.is_deleted = 0
        ",
    'test' => [
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no contracts with wrong "Divizor PU copie"'
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} contracts with empty "Divizor PU copie" and "Tarif copie black/color" with more than two decimal places',
            'iteration_text' => "Contract <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/contract.form.php?contract_id=[id]' target='_blank'>[id]</a> has empty \"Divizor PU copie\" but \"Tarif copie black\" [tarif_cop_bl] and \"Tarif copie color\" [tarif_cop_col1]",
        ],
    ],
];

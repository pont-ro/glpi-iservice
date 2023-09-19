<?php
global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        SELECT *
        FROM (
          SELECT
             fa.codbenef AS code
            , fi.initiale AS name
            , SUM(ROUND(fa.valinc-fa.valpla,2)) AS due
          FROM hmarfa_facturi fa
          JOIN hmarfa_firme fi ON fa.codbenef = fi.cod
          LEFT JOIN glpi_plugin_fields_suppliersuppliercustomfields sc ON sc.hmarfa_code_field = fi.cod AND sc.itemtype = 'Supplier'
          WHERE (fa.codl = 'F' OR fa.stare LIKE 'V%') AND fa.tip LIKE 'TF%'
            AND sc.id IS NULL
          GROUP BY fa.codbenef
        ) t
        WHERE t.due > 0
        ",
    'test' => [
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no partners with unpaid invoices that do not exist in iService',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} partners with unpaid invoices that do not exist in iService',
            'iteration_text' => "Partner <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=GlpiPlugin\Iservice\Views\UnpaidInvoices&unpaid_invoices0[nume_client]=[name]' target='_blank'>[name]</a> with hMarfa code [code] has unpaid invoices with a total of [due] RON.  <span id='fix-partner-[code]'><a href='javascript:void(0);' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/managePartner.php?name=[name]&code=[code]&operation=add\", \"\", function(message) {if (isNaN(message)) {alert(message);} else {\$(\"#fix-partner-[code]\").html(\"<a href=\\\"$CFG_GLPI[root_doc]/front/supplier.form.php?id=\" + message + \"\\\" target=\\\"_blank\\\">Edit new partner</a>\");}});'>»»» Add to iService «««</a></span>",
        ],
    ],
];

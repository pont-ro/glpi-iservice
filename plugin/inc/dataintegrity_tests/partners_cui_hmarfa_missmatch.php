<?php
global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
       select hmf.cod1 as cui_hmarfa, cfs.uic_field as cui_iservice, s.id, s.name from glpi_plugin_fields_suppliersuppliercustomfields cfs
        left join glpi_suppliers s on s.id = cfs.items_id and cfs.itemtype = 'Supplier'
        left join hmarfa_firme hmf on hmf.cod = cfs.hmarfa_code_field
        where hmf.cod1 <> cfs.uic_field and hmf.cod1
        ",
    'test' => [
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no partners with inconsistent CUI in hMarfa and iService',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} partners with inconsistent CUI in hMarfa and iService',
            'iteration_text' => "Partner <a href='$CFG_GLPI[root_doc]/front/supplier.form.php?id=[id]' target='_blank'>[name]</a> " .
                "has CUI: <b>[cui_hmarfa]</b> in hMarfa but CUI: <b style='color: darkred'>[cui_iservice]</b> in iService! " .
                "<span id='fix-partner-cui-[id]'>" .
                "<a href='javascript:void(0);' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageItem.php?itemtype=PluginIservicePartner&operation=update&id=[id]&uic_field=[cui_hmarfa]\", \"\", function(message) {if (isNaN(message)) {alert(message);} else {\$(\"#fix-partner-cui-[id]\").html(\"»»» CUI updated in iService with <b>[cui_hmarfa]</b>!\");}});'>" .
                "»»» Update CUI in iService with: <b>[cui_hmarfa]</b></b> from hMarfa «««" .
                "</a>" .
                "</span>",
        ],
    ],
];

<?php

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

global $CFG_PLUGIN_ISERVICE;

return [
    'query' => "
        select
              c.id cid
            , c.plugin_fields_typefielddropdowns_id c_type
            , ci.plugin_fields_typefielddropdowns_id ci_type
            , tfd.name ci_type_name
            , ci.supportedtypesfield 
        from glpi_cartridges c
        left join glpi_plugin_fields_cartridgeitemcartridgecustomfields ci on ci.items_id = c.cartridgeitems_id and ci.itemtype = 'CartridgeItem'
        left join glpi_plugin_fields_typefielddropdowns tfd on tfd.id = ci.plugin_fields_typefielddropdowns_id 
        where coalesce(c.plugin_fields_typefielddropdowns_id, 0) = 0 and c.date_use is not null
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges without type'
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridges without type',
            'iteration_text' => "Cartridge <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/view.php?view=cartridges&cartridges0%5Bid%5D=[cid]' target='_blank'>[cid]</a> has no type. <a id='fix-type-[cid]' href='javascript:void(0);' onclick='ajaxCall(\"" . GLPI_ROOT . "/plugins/iservice/ajax/manageCartridge.php?id=[cid]&type_id=[ci_type]&operation=force_type\", \"\", function(message) {if (message !== \"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {\$(\"#fix-type-[cid]\").remove();}});'>»»» FIX to [ci_type_name]«««</a>",
        ],
    ],
];

<?php

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

global $CFG_PLUGIN_ISERVICE;

return [
    'query' => "
        select cfc.items_id cartridge_id
        from glpi_plugin_fields_cartridgecartridgecustomfields cfc
        where cfc.suppliers_id_field = 0 and cfc.itemtype = 'Cartridge'
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges with no partner assigned',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridges with no partner assigned',
            'iteration_text' => "Cartridge <a href=\"$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Cartridges&filtering=1&cartridges0[id]=[cartridge_id]\" target='_blank'>[cartridge_id]</a> has no partner. <a id='delete-[cartridge_id]' href='javascript:void(0);' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageCartridge.php?operation=delete_cartridge&ids=[cartridge_id]\", \"\", function(message) {if (message !== \"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {\$(\"#delete-[cartridge_id]\").remove();}});'>»»» delete «««</a>",
        ],
    ],
];

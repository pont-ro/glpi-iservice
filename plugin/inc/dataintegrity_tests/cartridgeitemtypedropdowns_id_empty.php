<?php
global $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select cfc.items_id cartridge_id
        from glpi_plugin_fields_cartridgecartridgecustomfields cfc
        where cfc.plugin_fields_cartridgeitemtypedropdowns_id is null and cfc.itemtype = 'Cartridge'
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges with empty cartridge type',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridges with empty cartridge type',
            'iteration_text' => "Cartridge <a href=\"$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Cartridges&filtering=1&cartridges0[id]=[cartridge_id]\" target='_blank'>[cartridge_id]</a> has empty type.",
        ],
    ],
];

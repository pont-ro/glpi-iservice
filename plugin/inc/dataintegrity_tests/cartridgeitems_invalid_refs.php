<?php
global $CFG_PLUGIN_ISERVICE;

return [
    'query' => "
            select ci.id ciid, ci.name, ci.ref
            from glpi_cartridgeitems ci
            left join hmarfa_nommarfa hn on hn.cod = ci.ref
            where ci.is_deleted = 0 and hn.cod is null
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridge types with inexistent hMarfa code',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridge types with inexistent hMarfa code',
            'iteration_text' => "Cartridge type <b>[name]</b> has hMarfa code <b>[ref]</b>, that is inexistent in hMarfa. <span id='fix-cartridgetype-[ciid]'><a href='javascript:void(0);' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageItem.php?itemtype=PluginIserviceCartridgeItem&operation=GetRefSelector&id=[ciid]\", \"\", function(message) {\$(\"#fix-cartridgetype-[ciid]\").html(message);} );'>»»» Change code «««</a></span>",
        ],
    ],
];

<?php
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

global $CFG_PLUGIN_ISERVICE;

$hMarfaCodes = [];
$hMarfaCodesArray = PluginIserviceDB::getQueryResult("
    select nm.cod
    from hmarfa_nommarfa nm
    left join glpi_cartridgeitems ci on ci.ref = nm.cod
    where ci.ref is null
");
foreach ($hMarfaCodesArray as $hMarfaCode) {
    $hMarfaCodes[$hMarfaCode['cod']] = $hMarfaCode['cod'];
}
$hMarfaCodesDropdown = Dropdown::showFromArray(
    'choose_cartridgeitem_ref_-#ciid#-_',
    $hMarfaCodes,
    [
        'display' => false,
        'display_emptychoice' => true,
    ]
);

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
            'opening_param_separator' => '-#',
            'closing_param_separator' => '#-',
            'summary_text' => 'There are {count} cartridge types with inexistent hMarfa code',
            'iteration_text' => "Cartridge type <b>-#name#-</b> has hMarfa code <b>-#ref#-</b>, that is inexistent in hMarfa. <span id='fix-cartridgetype--#ciid#-'><a href='javascript:void(0);' onclick='(function(){var ref=\$(\"select[name=choose_cartridgeitem_ref_-#ciid#-_]\").val(); if(!ref || ref === 0 || ref === \"0\") {alert(\"Please select a code from the dropdown first.\"); return;} ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageItem.php?itemtype=PluginIserviceCartridgeItem&operation=Update&id=-#ciid#-&ref=\"+encodeURIComponent(ref), \"\", function(message) {if (isNaN(message)) {alert(message);} else {\$(\"#fix-cartridgetype--#ciid#-\").remove();}}); })();'>»»» Change code «««</a> with $hMarfaCodesDropdown from hMarfa</span>",
        ],
    ],
];

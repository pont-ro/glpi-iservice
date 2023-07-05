<?php
global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;
return [
    'query' => "
        select c.id cid, c.FK_location l1id, l1.completename l1_name, p.id pid, p.name pname, p.locations_id l2id, l2.completename l2_name
        from glpi_cartridges c
        left join glpi_locations l1 on l1.id = c.FK_location
        join glpi_printers p on p.id = c.printers_id
        left join glpi_locations l2 on l2.id = p.locations_id
        where c.date_use is not null and c.date_out is null
          and coalesce(case c.FK_location when -1 then 0 else c.FK_location end, 0) != coalesce(case p.locations_id when -1 then 0 else p.locations_id end, 0)
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges at different location as the printer they are installed on',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridges at different location as the printer they are installed on',
            'iteration_text' => "Cartridge <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/view.php?view=cartridges&cartridges0%5Bid%5D=[cid]' target='_blank'>[cid]</a> is at location '[l1_name]' while the printer <a href='$CFG_GLPI[root_doc]/front/printer.form.php?id=[pid]' target='_blank'>[pname]</a> on which it is installed is at location '[l2_name]'. <a id='fix-location-[cid]' href='javascript:void(0);' onclick='ajaxCall(\"$CFG_GLPI[root_doc]/plugins/iservice/ajax/manageCartridge.php?id=[cid]&location_id=[l2id]&operation=force_location\", \"\", function(message) {if (message !== \"" . PluginIserviceCommon::RESPONSE_OK . "\") {alert(message);} else {\$(\"#fix-location-[cid]\").remove();}});'>»»» FIX «««</a>",
        ],
    ],
];

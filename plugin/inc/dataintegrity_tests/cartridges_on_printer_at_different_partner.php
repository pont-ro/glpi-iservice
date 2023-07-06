<?php

global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

return [
    'query' => "
        select c.id, s1.name p1name, p.id pid, p.name pname, s2.id p2id, s2.name p2name
        from glpi_cartridges c
        join glpi_printers p on p.id = c.printers_id
        join glpi_infocoms ic on ic.items_id = c.printers_id and ic.itemtype = 'Printer'
        left join glpi_suppliers s1 on s1.id = c.FK_enterprise
        left join glpi_suppliers s2 on s2.id = ic.suppliers_id
        where c.date_use is not null and c.date_out is null
          and c.FK_enterprise != ic.suppliers_id
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges at different partner as the printer they are installed on',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridges at different partner as the printer they are installed on',
            'iteration_text' => "Cartridge <a href='$CFG_PLUGIN_ISERVICE[root_doc]/front/view.php?view=cartridges&cartridges0%5Bid%5D=[id]' target='_blank'>[id]</a> is at partner [p1name] while the printer [pname] on which it is installed is at partner [p2name]. <a id='fix-supplier-[id]' href='javascript:void(0);' onclick='ajaxCall(\"$CFG_GLPI[root_doc]/plugins/iservice/ajax/manageCartridge.php?id=[id]&supplier_id=[p2id]&operation=force_supplier\", \"\", function(message) {if (message !== \"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {\$(\"#fix-supplier-[id]\").remove();}});'>»»» FIX «««</a>",
        ],
    ],
];

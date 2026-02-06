<?php
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

global $CFG_PLUGIN_ISERVICE;
$expertline_supplier_id = IserviceToolBox::getExpertLineId();

return [
    'query' => "
        select cfc.id as cart_id, c.date_creation 
        from glpi_plugin_fields_cartridgecartridgecustomfields cfc
        left join glpi_cartridges c on c.id = cfc.items_id 
        where cfc.suppliers_id_field = $expertline_supplier_id 
          and cfc.tickets_id_use_field is null
          and c.date_creation < date_sub(now(), interval 7 day)
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridges older than 7 days at Expert Line.',
        ],
        'positive_result' => [
            'summary_text' => "There are {count} cartridges older than 7 days at Expert Line. <a id='delete-all-ex-cartridges' href='javascript:void(0);' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageCartridge.php?operation=delete_cartridge&ids={aggregated|id}\", \"\", function(message) {if (message !== \"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {\$(\"#delete-all-ex-cartridges\").remove();}});'>»»» delete all «««</a>",
            'iteration_text' => "[cart_id] - [date_creation] <a id='delete-[id]' href='javascript:void(0);' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageCartridge.php?operation=delete_cartridge&ids=[cart_id]\", \"\", function(message) {if (message !== \"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {\$(\"#delete-[id]\").remove();}});'>»»» delete «««</a>",
            'lin' => "<a href=\"$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=Cartridges&cartridges0[id]=[id]\" target=\"_blank\">[id]</a>",
        ],
    ],
    'schedule' => [
        'display_last_result' => true,
        'h:m'                 => ['6:30'],
        'ignore_text'         => [
            'hours' => "Checked only at 06:30.",
        ]
    ]
];

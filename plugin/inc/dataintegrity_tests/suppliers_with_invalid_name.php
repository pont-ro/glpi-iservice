<?php
use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;

return [
    'query' => "
        SELECT
          `glpi_suppliers`.`id` AS `sid`,
          `glpi_suppliers`.`name`,
          `glpi_suppliers`.`name` AS invalid_value,
          REGEXP_REPLACE(
            REGEXP_REPLACE(
              REGEXP_REPLACE(
                `glpi_suppliers`.`name`,
                '^ +', '<mark class=\"bg-warning\">\\0</mark>'
              ),
              ' +$', '<mark class=\"bg-warning\">\\0</mark>'
            ),
            ' {2,}', '<mark class=\"bg-warning\">\\0</mark>'
          ) AS invalid_value_marked,
          'name' AS invalid_field
        FROM `glpi_suppliers`
        WHERE `glpi_suppliers`.`name` REGEXP '(^ +| +$|  +)'
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no partners problematic invalid name (leading/trailing spaces or double spaces)',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} partners with problematic name (leading/trailing spaces or double spaces)',
            'iteration_text' => "Partner <a href='$CFG_GLPI[root_doc]/front/supplier.form.php?id=[sid]' target='_blank'>[name]</a> has invalid name: <span style='white-space:pre'>[invalid_value_marked]</span>
            <a id='fix-partner-name-[sid]' href='javascript:void(0);' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageItem.php?itemtype=PluginIservicePartner&operation=RemoveSpacesFromName&id=[sid]\", \"\", function(message) {if (message !== \"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {\$(\"#fix-partner-name-[sid]\").remove();}});'>»»» FIX «««</a>
            ",
        ],
    ],
];

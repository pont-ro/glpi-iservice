<?php

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;

return [
    'query' => "
        SELECT
          `glpi_printers`.`id` AS `pid`,
          `glpi_printers`.`name`,
          `glpi_printers`.`serial`,
          REGEXP_REPLACE(
    -- Mark trailing spaces
            REGEXP_REPLACE(
              -- Mark leading spaces
              REGEXP_REPLACE(
                CASE
                  WHEN name REGEXP '(^ +| +$|  +)' THEN name
                  ELSE serial
                END,
                '^ +', '<mark class=\"bg-warning\">\\0</mark>'
              ),
              ' +$', '<mark class=\"bg-warning\">\\0</mark>'
            ),
            ' {2,}', '<mark class=\"bg-warning\">\\0</mark>'
          ) AS invalid_value_marked,
          CASE
          WHEN `glpi_printers`.`name` REGEXP '(^ +| +$|  +)' THEN 'name'
          ELSE 'serial'
        END AS invalid_field
        FROM `glpi_printers`
        WHERE `glpi_printers`.`name` REGEXP '(^ +| +$|  +)'
           OR `glpi_printers`.`serial` REGEXP '(^ +| +$|  +)'
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no printers with problematic name or serial (leading/trailing spaces or double spaces)',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} printers with problematic name or serial (leading/trailing spaces or double spaces)',
            'iteration_text' => "Printer <a href='$CFG_GLPI[root_doc]/front/printer.form.php?id=[pid]' target='_blank'>[name]</a> has invalid [invalid_field]: <span style='white-space:pre'>[invalid_value_marked]</span>
                <a id='fix-printer-name-serial-[pid]' href='javascript:void(0);' onclick='ajaxCall(\"$CFG_PLUGIN_ISERVICE[root_doc]/ajax/manageItem.php?itemtype=PluginIservicePrinter&operation=RemoveSpacesFromNameAndSerial&id=[pid]\", \"\", function(message) {if (message !== \"" . IserviceToolBox::RESPONSE_OK . "\") {alert(message);} else {\$(\"#fix-printer-name-serial-[pid]\").remove();}});'>»»» FIX «««</a>
            ",
        ],
    ],
];

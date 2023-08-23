<?php
global $CFG_GLPI;
return [
    'query' => "
        select 
            c.name
          , cfc1.items_id id
          , cfc1.mercury_code_field mc1
          , cfc1.compatible_mercury_codes_field mcs1
          , group_concat(concat('<b><i>', cfc2.mercury_code_field, '</i></b> (by cartridgeitem <a href=\"$CFG_GLPI[root_doc]/front/cartridgeitem.form.php?id=', cfc2.items_id, '\" target=\"_blank\">', cfc2.items_id, '</a>)') separator ', ') `references`
        from glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cfc1
        join glpi_cartridgeitems c on c.id = cfc1.items_id and c.is_deleted = 0
        join glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cfc2 on cfc2.compatible_mercury_codes_field like concat(\"%'\", cfc1.mercury_code_field, \"'%\") and cfc1.items_id != cfc2.items_id
        where not cfc1.compatible_mercury_codes_field like concat(\"%'\", cfc2.mercury_code_field, \"'%\")
        group by cfc1.items_id
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridge types with invalid mercury code compatibility',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridge types with invalid mercury code compatibility',
            'iteration_text' => "<a href='$CFG_GLPI[root_doc]/front/cartridgeitem.form.php?id=[id]' target='_blank'>[name] ([id])</a> with mercury code <b><i>[mc1]</i></b> is set as compatible with the following mercury codes: [references], but not all of them are referenced in the compatibles field (<b>[mcs1]</b>).",
        ],
    ],
];

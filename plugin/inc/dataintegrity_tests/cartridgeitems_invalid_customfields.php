<?php
global $CFG_GLPI;
return [
    'query' => "
        select ci.id, ci.ref, ci.name, ci.atc_field, ci.supported_types_field, ci.life_coefficient_field, ci.compatible_mercury_codes_field
        from glpi_plugin_iservice_cartridge_items ci
        where ci.is_deleted = 0 and not ci.ref like 'CCAI%'
          and (   coalesce(ci.ref, '') = ''
               or coalesce(ci.mercury_code_field, '') = ''
               or coalesce(ci.compatible_mercury_codes_field, '') = ''
               or coalesce(ci.atc_field, 0) = 0 
               or coalesce(ci.life_coefficient_field, 0) = 0 
               or coalesce(ci.supported_types_field, '') = '')
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridge types with invaild customfields',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridge types with invalid customfield values',
            'iteration_text' => "<a href='$CFG_GLPI[root_doc]/front/cartridgeitem.form.php?id=[id]' target='_blank'>[name] ([id])</a> with <i>supported types</i> <b>[supported_types_field]</b> and <i>hMarfa code</i> <b>[ref]</b> has <i>average total counter</i> <b>[atc_field]</b>, <i>life coefficient</i> <b>[life_coefficient_field]</b> and <i>supported mercurycodes</i> <b>[compatible_mercury_codes_field]</b>",
        ],
    ],
];

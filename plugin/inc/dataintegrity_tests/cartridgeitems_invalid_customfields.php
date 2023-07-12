<?php
global $CFG_GLPI;
return [
    'query' => "
        select c.id, c.ref, c.name, cfc.atc_field, cfc.supported_types_field, cfc.life_coefficient_field, cfc.compatible_mercury_codes_field
        from glpi_cartridgeitems c
        left join glpi_plugin_fields_cartridgeitemcartridgeitemcustomfields cfc on cfc.items_id = c.id and cfc.itemtype = 'CartridgeItem'
        where c.is_deleted = 0 and not c.ref like 'CCAI%'
          and (   coalesce(c.ref, '') = ''
               or coalesce(cfc.mercury_code_field, '') = ''
               or coalesce(cfc.compatible_mercury_codes_field, '') = ''
               or coalesce(cfc.atc_field, 0) = 0 
               or coalesce(cfc.life_coefficient_field, 0) = 0 
               or coalesce(cfc.supported_types_field, 0) = 0)
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

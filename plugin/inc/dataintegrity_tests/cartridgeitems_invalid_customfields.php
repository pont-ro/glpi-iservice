<?php
global $CFG_GLPI;
return [
    'query' => "
        select c.id, c.ref, c.name, cfc.atcfield, cfc.supportedtypesfield, cfc.lcfield, cfc.mercurycodesfield
        from glpi_cartridgeitems c
        left join glpi_plugin_fields_cartridgeitemcartridgecustomfields cfc on cfc.items_id = c.id and cfc.itemtype = 'CartridgeItem'
        where c.is_deleted = 0 and not c.ref like 'CCAI%'
          and (   coalesce(c.ref, '') = ''
               or coalesce(cfc.mercurycodefield, '') = ''
               or coalesce(cfc.mercurycodesfield, '') = ''
               or coalesce(cfc.atcfield, 0) = 0 
               or coalesce(cfc.lcfield, 0) = 0 
               or coalesce(cfc.supportedtypesfield, 0) = 0)
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridge types with invaild customfields',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridge types with invalid customfield values',
            'iteration_text' => "<a href='$CFG_GLPI[root_doc]/front/cartridgeitem.form.php?id=[id]' target='_blank'>[name] ([id])</a> with <i>supported types</i> <b>[supportedtypesfield]</b> and <i>hMarfa code</i> <b>[ref]</b> has <i>average total counter</i> <b>[atcfield]</b>, <i>life coefficient</i> <b>[lcfield]</b> and <i>supported mercurycodes</i> <b>[mercurycodesfield]</b>",
        ],
    ],
];

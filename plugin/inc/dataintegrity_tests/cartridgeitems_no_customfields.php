<?php
return [
    'query' => "
        select c.id, c.ref, c.name
        from glpi_cartridgeitems c
        left join glpi_plugin_fields_cartridgeitemcartridgecustomfields cfc on cfc.items_id = c.id and cfc.itemtype = 'CartridgeItem'
        where c.is_deleted = 0 and cfc.id is null
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no cartridge types without customfields',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} cartridge types without customfields',
            'iteration_text' => '[name] with id [id] and hMarfa code [ref] has no custom fields',
        ],
    ],
];

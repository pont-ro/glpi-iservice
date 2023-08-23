<?php
return [
    'itemTypeClass'   => PluginFieldsCartridgeitemtypeDropdown::class,
    'oldTable'        => 'glpi_plugin_fields_typefielddropdowns',
    'identifierField' => 'name',
    'fieldMap'        => [
        [
            'name' => 'name',
        ],
        [
            'name' => 'completename',
        ],
        [
            'name' => 'comment',
        ],
        [
            'name' => 'level',
        ],
        [
            'name' => 'ancestors_cache',
        ],
        [
            'name' => 'sons_cache',
        ],
        [
            'name' => 'entities_id',
        ],
        [
            'name' => 'is_recursive',
        ],
        [
            'name'    => 'plugin_fields_cartridgeitemtypedropdowns_id',
            'oldName' => 'plugin_fields_typefielddropdowns_id',
        ]
    ],
    'selfReferences'  => [
        'plugin_fields_cartridgeitemtypedropdowns_id',
    ],
    'foreignKeys'     => [
        'plugin_fields_cartridgeitemtypedropdowns_id' => 'PluginFieldsCartridgeitemtypeDropdown',
    ],
];

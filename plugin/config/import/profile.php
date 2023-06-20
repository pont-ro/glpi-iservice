<?php
return [
    'itemTypeClass'   => Profile::class,
    'oldTable'        => 'glpi_profiles',
    'identifierField' => 'name',
    'checkValues'     => [
        'changetemplates_id'  => 0,
        'problemtemplates_id' => 0,
    ],
    'forceValues'     => [
        'tickettemplates_id' => 0,
    ],
];

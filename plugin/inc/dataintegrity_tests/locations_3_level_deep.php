<?php
global $CFG_GLPI;
return [
    'query' => "
        select l1.id, l1.name, l1.completename, l2.id parent_id, l2.name parent_name, l2.completename parent_complete_name, l3.id parent_parent_id, l3.name parent_parent_name
        from glpi_locations l1
        join glpi_locations l2 on l2.id = l1.locations_id
        join glpi_locations l3 on l3.id = l2.locations_id
        ",
    'test' => [
        'alert' => true,
        'type' => 'compare_query_count',
        'zero_result' => [
            'summary_text' => 'There are no locations that are 3 level deep',
        ],
        'positive_result' => [
            'summary_text' => 'There are {count} locations that are 3 level deep',
            'iteration_text' => "Location <a href='$CFG_GLPI[root_doc]/front/location.form.php?id=[id]'>[completename]</a> is 3 level deep: parent 1 is <a href='$CFG_GLPI[root_doc]/front/location.form.php?id=[parent_id]'>[parent_complete_name]</a>, parent 2 is <a href='$CFG_GLPI[root_doc]/front/location.form.php?id=[parent_parent_id]'>[parent_parent_name]</a>",
        ],
    ],
];

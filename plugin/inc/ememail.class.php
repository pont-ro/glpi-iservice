<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceEMEmail extends CommonDBTM
{

    function prepareInputForAdd($input)
    {
        $result = parent::prepareInputForAdd($input);
        foreach (['subject', 'suggested'] as $field_name) {
            if (!empty($result[$field_name])) {
                $result[$field_name] = str_replace("'", "\\'", $result[$field_name]);
            }
        }

        return $result;
    }

    function prepareInputForUpdate($input)
    {
        return $this->prepareInputForAdd(parent::prepareInputForUpdate($input));
    }

}

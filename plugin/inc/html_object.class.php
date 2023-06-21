<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceHtml_object
{

    public function __construct($properties = [])
    {
        $object_properties = $this->getProperties();
        foreach ($object_properties as $property_name => $property_default_value) {
            $this->$property_name = isset($properties[$property_name]) ? $properties[$property_name] : $property_default_value;
        }
    }

    public function getProperties()
    {
        return [
            'tag' => 'div',
            'text' => '',
            'class' => '',
            'style' => '',
        ];
    }

    public function __toString()
    {
        $class = PluginIserviceHtml::adjustAttribute('class', $this->class);
        $style = PluginIserviceHtml::adjustAttribute('style', $this->style, ';');
        return "<$this->tag$class$style>$this->text</$this->tag>";
    }

}

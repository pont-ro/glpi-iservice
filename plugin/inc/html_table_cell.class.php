<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceHtml_table_cell extends PluginIserviceHtml_object
{

    public function __construct($text = '', $class = '', $style = '', $colspan = 1, $rowspan = 1, $tag = 'td')
    {
        if (is_array($text)) {
            parent::__construct($text);
        } else {
            parent::__construct(
                [
                    'text' => $text,
                    'class' => $class,
                    'style' => $style,
                    'colspan' => $colspan,
                    'rowspan' => $rowspan,
                    'tag' => $tag
                ]
            );
        }
    }

    public function getProperties()
    {
        $object_properties            = parent::getProperties();
        $object_properties['tag']     = 'td';
        $object_properties['colspan'] = 1;
        $object_properties['rowspan'] = 1;
        return $object_properties;
    }

    public function __toString()
    {
        $class = PluginIserviceHtml::adjustAttribute('class', $this->class);
        $style = PluginIserviceHtml::adjustAttribute('style', $this->style, ';');
        if ($this->colspan > 1) {
            $colspan = " colspan='$this->colspan'";
        } else {
            $colspan = "";
        }

        if ($this->rowspan > 1) {
            $rowspan = " rowspan='$this->rowspan'";
        } else {
            $rowspan = "";
        }

        return "<$this->tag$colspan$rowspan$class$style>$this->text</$this->tag>";
    }

}

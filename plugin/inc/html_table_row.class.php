<?php

// Imported from iService2, needs refactoring.
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceHtml_table_row extends PluginIserviceHtml_object
{

    public function __construct($class = '', $cells = [], $style = '', $tag = 'tr')
    {
        if (is_array($class)) {
            parent::__construct($class);
        } else {
            parent::__construct(
                [
                    'class' => $class,
                    'style' => $style,
                    'tag' => $tag
                ]
            );
            $this->populateCells($cells, '', '', 'td');
        }
    }

    public function getProperties()
    {
        $object_properties = parent::getProperties();
        unset($object_properties['text']);
        $object_properties['tag']   = 'tr';
        $object_properties['cells'] = [];
        return $object_properties;
    }

    public function __toString()
    {
        $class = PluginIserviceHtml::adjustAttribute('class', $this->class);
        $style = PluginIserviceHtml::adjustAttribute('style', $this->style, ';');
        $html  = "<$this->tag$class$style>";
        foreach ($this->cells as $cell) {
            $html .= $cell;
        }

        $html .= "</$this->tag>";
        return $html;
    }

    public function populateCells($cells = [], $class = '', $style = '', $tag = 'td')
    {
        // validating $cells
        $this->cells = [];
        if (empty($cells)) {
            return;
        }

        if (!is_array($cells)) {
            $cells = [$cells];
        }

        $cell_count = count($cells);
        if ($cell_count < 1) {
            return;
        }

        // validating $class
        if (is_string($class) || !is_array($class)) {
            $default_class = $class;
            $class         = [];
            for ($i = 0; $i < $cell_count; $i++) {
                $class[$i] = $default_class;
            }
        } elseif (is_array($class)) {
            if (count($class) < $cell_count) {
                $class_count   = count($class);
                $default_class = $class[0];
                for ($i = $class_count; $i < $cell_count; $i++) {
                    $class[$i] = $default_class;
                }
            }
        }

        // validating $style
        if (is_string($style) || !is_array($style)) {
            $default_style = $style;
            $style         = [];
            for ($i = 0; $i < $cell_count; $i++) {
                $style[$i] = $default_style;
            }
        } elseif (is_array($style)) {
            if (count($style) < $cell_count) {
                $style_count   = count($style);
                $default_style = $style[0];
                for ($i = $style_count; $i < $cell_count; $i++) {
                    $style[$i] = $default_style;
                }
            }
        }

        $cell_keys = array_keys($cells);
        for ($i = 0; $i < $cell_count ; $i++) {
            if (isset($cells[$i]) && $cells[$i] instanceof PluginIserviceHtml_table_cell) {
                $this->cells[] = $cells[$i];
            } else {
                $this->cells[] = new PluginIserviceHtml_table_cell($cells[$cell_keys[$i]], $class[$i], $style[$i], 1, 1, $tag);
            }
        }
    }

}

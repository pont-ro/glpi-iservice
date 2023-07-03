<?php

// Imported from iService2, needs refactoring. Original file: "html_table.class.php".
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginIserviceHtml_table extends PluginIserviceHtml_object
{

    public function __construct($class = '', $head = [], $body = [], $style = '', $tag = 'table')
    {
        if (is_array($class)) {
            parent::__construct($class);
        } else {
            parent::__construct(
                [
                    'head' => $head,
                    'body' => $body,
                    'class' => $class,
                    'style' => $style,
                    'tag' => $tag
                ]
            );
        }
    }

    public function getProperties()
    {
        $object_properties = parent::getProperties();
        unset($object_properties['text']);
        $object_properties['tag']  = 'table';
        $object_properties['head'] = [];
        $object_properties['body'] = [];
        return $object_properties;
    }

    public function __toString()
    {
        $class = PluginIserviceHtml::adjustAttribute('class', $this->class);
        $style = PluginIserviceHtml::adjustAttribute('style', $this->style, ';');
        $html  = "<$this->tag$class$style>";
        if (!empty($this->head)) {
            $html .= "<thead>";
            if (is_array($this->head)) {
                foreach ($this->head as $row) {
                    $html .= $row;
                }
            } else {
                $html .= $this->head;
            }

            $html .= "</thead>";
        }

        if (!empty($this->body)) {
            $html .= "<tbody>";
            if (is_array($this->body)) {
                foreach ($this->body as $row) {
                    $html .= $row;
                }
            } else {
                $html .= $this->body;
            }

            $html .= "</tbody>";
        }

        $html .= "</$this->tag>";
        return $html;
    }

}

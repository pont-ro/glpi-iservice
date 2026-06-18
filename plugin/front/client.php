<?php
// Logic moved to GlpiPlugin\Iservice\Controller\ClientController via plugin/config/routes.php

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

$magic_link = IserviceToolBox::getInputVariable('id');
$cui = IserviceToolBox::getInputVariable('cui');
$view = IserviceToolBox::getInputVariable('view');

$url = "/plugins/iservice/client?id=$magic_link";
if (!empty($cui)) {
    $url .= "&cui=$cui";
}
if (!empty($view)) {
    $url .= "&view=$view";
}

Html::redirect($url);

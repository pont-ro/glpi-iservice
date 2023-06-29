<?php

// Imported from iService2, needs refactoring.
define('GLPI_ROOT', __DIR__ . '/../../..');
include_once (GLPI_ROOT . "/inc/includes.php");

$task = PluginIserviceCommon::getInputVariable('task');
if (empty($task)) {
    $args = getopt('', ['task::']);
    if (isset($args['task'])) {
        $task = $args['task'];
    }
}

if ($task !== 'DataIntegrityTest') {
    Session::checkRight("plugin_iservice_admintask_$task", READ);
}

$task_class = "PluginIserviceTask_$task";
$task_object = new $task_class();

PluginIserviceHtml::header($task_object->getTitle(), filter_input(INPUT_SERVER, 'PHP_SELF'));

$task_object->execute();

PluginIserviceHtml::footer();

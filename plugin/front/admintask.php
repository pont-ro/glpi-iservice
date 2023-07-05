<?php

// Imported from iService2, needs refactoring.
require "../inc/includes.php";

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

$task = IserviceToolBox::getInputVariable('task');
if (empty($task)) {
    $args = getopt('', ['task::']);
    if (isset($args['task'])) {
        $task = $args['task'];
    }
}

if ($task !== 'DataIntegrityTest') {
    Session::checkRight("plugin_iservice_admintask_$task", READ);
}

$task_class  = "PluginIserviceTask_$task";
$task_object = new $task_class();

Html::header(
    __("iService", "iservice"),
    $_SERVER['PHP_SELF'],
    "plugin_iservice_views",
    'admin_task',
    "admin_task"
);

$task_object->execute();

Html::footer();

<?php

if (($_GET['kcsrft'] ?? null) || ($_POST['kcsrft'] ?? null)) {
    define('GLPI_KEEP_CSRF_TOKEN', true);
}

if (strpos(realpath(getcwd()), 'iService\plugin') > 0) {
    // This is a hack to make the plugin work in a development environment.
    global $CFG_GLPI;
    $CFG_GLPI['root_doc'] = '';
    include "../../glpi/inc/includes.php";
} else {
    include "../../../inc/includes.php";
}

<?php

/* Array keys must use dot notation, e.g. 'config_override.core.palette' => 'auror'. */

return [
    'version' => '0.0.1',
    'enabled_crons.hMarfaImport' => true,
    'hmarfa.import.script_file' => '/hMarfaImport.sql',
    'hmarfa.import.errors' => PLUGIN_ISERVICE_CACHE_DIR . "/hMarfaImportErrors",
];

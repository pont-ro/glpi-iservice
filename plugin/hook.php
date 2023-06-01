<?php

/**
 * Install all necessary elements for the plugin
 *
 * @param array $args Arguments passed from CLI
 *
 * @return boolean
 */
function plugin_iservice_install(array $args = []): bool
{
    require_once(__DIR__ . '/install/install.php');
    $install = new PluginIserviceInstall();

    return $install->install();
}

function plugin_iservice_uninstall(): void
{
    require_once(__DIR__ . '/install/install.php');
    $install = new PluginIserviceInstall();
    $install->uninstall();
}

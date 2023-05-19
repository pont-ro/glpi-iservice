<?php
/**
 * Install all necessary elements for the plugin
 * @param array $args Arguments passed from CLI
 *
 * @return boolean
 */
function plugin_iservice_install(array $args = []): bool
{
//    $migration = new Migration(plugin_version_iservice()['version']);
    require_once(__DIR__ . '/install/install.php');
    $install = new PluginIserviceInstall();
//    if (!$install->isPluginInstalled() || ($args['force-install'] ?? false) === true) {
        return $install->install();
//    }

//    return $install->upgrade($migration, $args);
}

function plugin_iservice_uninstall(): void
{
    require_once(__DIR__ . '/install/install.php');
    $install = new PluginIserviceInstall();
    $install->uninstall();
}

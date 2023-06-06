<?php

use GlpiPlugin\Iservice\Utils\ForceHttps;

define('ISERVICE_VERSION', '0.0.1');

if (!defined("PLUGIN_ISERVICE_DIR")) {
    define("PLUGIN_ISERVICE_DIR", GLPI_ROOT . "/plugins/iservice");
}

if (!defined("GLPI_PLUGIN_DOC_DIR")) {
    define("GLPI_PLUGIN_DOC_DIR", GLPI_ROOT . "/files/_plugins");
}

if (!defined("PLUGIN_ISERVICE_DOC_DIR")) {
    define("PLUGIN_ISERVICE_DOC_DIR", GLPI_PLUGIN_DOC_DIR . "/iservice");
}

/**
 * Init the hooks of the plugins - Needed
 *
 * @return void
 */
function plugin_init_iservice(): void
{
    global $PLUGIN_HOOKS;

    // Required!
    $PLUGIN_HOOKS['csrf_compliant']['iservice'] = true;

    // Force https!
    ForceHttps::do();

    if (Session::getLoginUserID() && Plugin::isPluginActive('iservice')) {
        // Add link in plugin page.
        $PLUGIN_HOOKS['config_page']['iservice'] = 'front/config.php';

        // Add entry to configuration menu.
        $PLUGIN_HOOKS["menu_toadd"]['iservice'] = ['config' => 'PluginIserviceMenu'];

        $PLUGIN_HOOKS['add_css']['iservice'][] = "css/iservice.css";

        $PLUGIN_HOOKS['add_javascript']['iservice'] = "js/import.js";
    }
}

/**
 * Get the name and the version of the plugin - Needed
 *
 * @return array
 */
function plugin_version_iservice(): array
{
    return [
        'name'           => 'iService',
        'version'        => ISERVICE_VERSION,
        'author'         => 'hupu',
        'license'        => 'GLPv3',
        'homepage'       => '',
        'requirements'   => [
            'glpi'   => [
                'min' => '10.0',
                'max' => '10.1',
                'plugins' => ['fields', 'formcreator'],
            ],
        ],
    ];
}

/**
 * Optional : check prerequisites before install : may print errors or add to message after redirect
 *
 * @return boolean
 */
function plugin_iservice_check_prerequisites(): bool
{
    // Do what checks you want.
    return true;
}

/**
 * Check configuration process for plugin : need to return true if succeeded
 * Can display a message only if failure and $verbose is true
 *
 * @param boolean $verbose Enable verbosity. Default to false
 *
 * @return boolean
 */
function plugin_iservice_check_config(bool $verbose = false): bool
{
    if (file_exists(PLUGIN_ISERVICE_DIR . '/install/install.php')) {
        return true;
    }

    if ($verbose) {
        echo "Installed, but not configured";
    }

    return false;
}

/**
 * Optional: defines plugin options.
 *
 * @return array
 */
function plugin_iservice_options(): array
{
    return [
        Plugin::OPTION_AUTOINSTALL_DISABLED => true,
    ];
}

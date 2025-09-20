<?php

use Glpi\Application\View\TemplateRenderer;
use Glpi\Plugin\Hooks;
use GlpiPlugin\Iservice\Utils\HtaccessChecker;
use GlpiPlugin\Iservice\Utils\ViewsMenu;
use GlpiPlugin\Iservice\Utils\SpecialViewsMenu;
use GlpiPlugin\Iservice\Utils\IserviceMenu;

define('ISERVICE_VERSION', '3.0.18');

if (!defined("PLUGIN_ISERVICE_DIR")) {
    define("PLUGIN_ISERVICE_DIR", GLPI_ROOT . "/plugins/iservice");
}

if (!defined("GLPI_PLUGIN_DOC_DIR")) {
    define("GLPI_PLUGIN_DOC_DIR", GLPI_ROOT . "/files/_plugins");
}

if (!defined("PLUGIN_ISERVICE_DOC_DIR")) {
    define("PLUGIN_ISERVICE_DOC_DIR", GLPI_PLUGIN_DOC_DIR . "/iservice");
}

if (!file_exists(PLUGIN_ISERVICE_DOC_DIR)) {
    mkdir(PLUGIN_ISERVICE_DOC_DIR);
}

if (!defined("PLUGIN_ISERVICE_CACHE_DIR")) {
    define("PLUGIN_ISERVICE_CACHE_DIR", PLUGIN_ISERVICE_DOC_DIR . "/cache");
}

if (!file_exists(PLUGIN_ISERVICE_CACHE_DIR)) {
    mkdir(PLUGIN_ISERVICE_CACHE_DIR);
}

if (!defined("PLUGIN_ISERVICE_LOG_DIR")) {
    define("PLUGIN_ISERVICE_LOG_DIR", PLUGIN_ISERVICE_DOC_DIR . "/logs");
}

if (!file_exists(PLUGIN_ISERVICE_LOG_DIR)) {
    mkdir(PLUGIN_ISERVICE_LOG_DIR);
}

if (isset($_GET['iServiceCompressedInputData'])) {
    global $_UGET;
    $_UGET = $_GET = plugin_iservice_json_decode_input_data($_GET['iServiceCompressedInputData'] ?? '');
}

if (isset($_POST['iServiceCompressedInputData'])) {
    global $_UPOST;
    $_UPOST = $_POST = plugin_iservice_json_decode_input_data($_POST['iServiceCompressedInputData'] ?? '');
}

/**
 * Init the hooks of the plugins - Needed
 *
 * @return void
 */
function plugin_init_iservice(): void
{
    global $CFG_GLPI, $PLUGIN_HOOKS;
    global $CFG_PLUGIN_ISERVICE;
    global $DEBUG_SQL, $TIMER_DEBUG;

    // IMPORTANT! Without this restriction, user can navigate out from the QR form, and that is not allowed because user is automatically logged in on that page.
    PluginIserviceQr::restrictQrUserToQrForm();

    PluginIserviceConfig::handleConfigValues();
    TemplateRenderer::getInstance()->getEnvironment()->addExtension(new PluginIserviceTranslationExtension());

    $DEBUG_SQL['debug_times'][$TIMER_DEBUG->getTime()] = 'Init iService';

    $CFG_PLUGIN_ISERVICE = [
        'root_doc' => "$CFG_GLPI[root_doc]/plugins/iservice",
        'data_integrity_tests_date_from' => PluginIserviceConfig::getConfigValue('data_integrity_tests_date_from'),
    ];

    // Required!
    $PLUGIN_HOOKS['csrf_compliant']['iservice'] = true;

    HtaccessChecker::check();

    if (!Plugin::isPluginActive('iservice')) {
        return;
    }

    $PLUGIN_HOOKS['change_profile']['iservice'] = ['PluginIserviceProfile', 'changeprofile'];

    if (!Session::getLoginUserID()) {
        return;
    }

    $PLUGIN_HOOKS[Hooks::DEBUG_TABS]['iservice'] = [
        'iservice' => [
            'title' => 'iService',
            'display_callable' => 'plugin_iservice_debug_tab',
        ]
    ];

    // Must override the formcreator hook, as it has bug.
    $PLUGIN_HOOKS[Hooks::ITEM_UPDATE]['formcreator'][Profile::class] = 'plugin_iservice_hook_formcreator_update_profile';

    // Add link in plugin page.
    $PLUGIN_HOOKS['config_page']['iservice'] = 'front/config.form.php';

    // Add entry to configuration menu.
    $PLUGIN_HOOKS["menu_toadd"]['iservice'] = [
        'config'       => 'PluginIserviceMenu',
        'iService'     => IserviceMenu::getClasses(),
        'views'        => ViewsMenu::getClasses(),
        'specialViews' => SpecialViewsMenu::getClasses(),
    ];

    $PLUGIN_HOOKS['add_css']['iservice'][] = "css/iservice.css";

    $PLUGIN_HOOKS['add_javascript']['iservice'] = [
        "js/import.js",
        "js/iservice.js",
    ];

    $activeProfileName = $_SESSION['glpiactiveprofile']['name'] ?? null;
    if ($activeProfileName === 'admin' || $activeProfileName === 'tehnician') {
        $PLUGIN_HOOKS['add_javascript']['iservice'][] = "js/admin-menu-modifications.js";
    } elseif ($activeProfileName === 'client') {
        $PLUGIN_HOOKS['add_javascript']['iservice'][] = "js/client-menu-modifications.js";
    }

    $PLUGIN_HOOKS['add_javascript']['iservice'][] = "js/general-menu-modifications.js";

    $PLUGIN_HOOKS['redefine_menus']['iservice'] = 'plugin_iservice_redefine_menus';

    $PLUGIN_HOOKS['pre_item_add']['iservice']['Ticket']                                            = 'plugin_iservice_pre_Ticket_add';
    $PLUGIN_HOOKS['pre_item_add']['iservice']['PluginFieldsPrinterprintercustomfield']             = 'plugin_iservice_pre_PluginFieldsPrintercustomfield_add';
    $PLUGIN_HOOKS['pre_item_add']['iservice']['PluginFieldsSuppliersuppliercustomfield']           = 'plugin_iservice_pre_PluginFieldsSuppliercustomfield_add';
    $PLUGIN_HOOKS['pre_item_add']['iservice']['PluginFieldsCartridgeitemcartridgeitemcustomfield'] = 'plugin_iservice_pre_PluginFieldsCartridgeitemcustomfield_add';

    $PLUGIN_HOOKS['post_prepareadd']['iservice']['Ticket']               = 'plugin_iservice_post_Ticket_prepareadd';
    $PLUGIN_HOOKS['post_prepareadd']['iservice']['PluginIserviceTicket'] = 'plugin_iservice_post_Ticket_prepareadd';

    $PLUGIN_HOOKS['pre_item_update']['iservice']['Ticket']                                            = 'plugin_iservice_pre_Ticket_update';
    $PLUGIN_HOOKS['pre_item_update']['iservice']['PluginFieldsPrinterprintercustomfield']             = 'plugin_iservice_pre_PluginFieldsPrintercustomfield_update';
    $PLUGIN_HOOKS['pre_item_update']['iservice']['PluginFieldsSuppliersuppliercustomfield']           = 'plugin_iservice_pre_PluginFieldsSuppliercustomfield_update';
    $PLUGIN_HOOKS['pre_item_update']['iservice']['PluginFieldsCartridgeitemcartridgeitemcustomfield'] = 'plugin_iservice_pre_PluginFieldsCartridgeitemcustomfield_update';

    $PLUGIN_HOOKS['item_update']['iservice']['Ticket']                              = 'plugin_iservice_Ticket_update';
    $PLUGIN_HOOKS['item_update']['iservice']['PluginFieldsTicketticketcustomfield'] = 'plugin_iservice_PluginFieldsTicketticketcustomfield_update';
    $PLUGIN_HOOKS['item_update']['iservice']['Printer']                             = 'plugin_iservice_Printer_update';
    $PLUGIN_HOOKS['item_update']['iservice']['Infocom']                             = 'plugin_iservice_Infocom_update';

    $PLUGIN_HOOKS['pre_item_delete']['iservice']['Ticket'] = 'plugin_iservice_pre_Ticket_delete';
    $PLUGIN_HOOKS['pre_item_purge']['iservice']['Ticket']  = 'plugin_iservice_pre_Ticket_delete';

    $PLUGIN_HOOKS['display_central']['iservice'] = 'redirect_from_central';

    Plugin::registerClass(
        'PluginIserviceExtraTabs',
        [
            'addtabon' => ['CartridgeItem', 'PrinterModel'],
        ]
    );
}

function plugin_iservice_debug_tab($with_session, $ajax, $rand): void
{
    global $DEBUG_SQL, $TIMER_DEBUG;

    $DEBUG_SQL['debug_times'][$TIMER_DEBUG->getTime()] = 'Displaying iService debug tab';

    echo "<pre>";
    print_r($DEBUG_SQL['debug_times']);
    echo "</pre>";
}

/**
 * Get the name and the version of the plugin - Needed
 *
 * @return array
 */
function plugin_version_iservice(): array
{
    return [
        'name'         => 'iService',
        'version'      => ISERVICE_VERSION,
        'author'       => 'hupu',
        'license'      => 'GLPv3',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min'     => '10.0',
                'max'     => '10.1',
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

function plugin_iservice_check_status(): void
{
    global $CFG_GLPI;
    if (!Plugin::isPluginLoaded('iservice')) {
        if (Session::haveRight('config', UPDATE)) {
            Session::addMessageAfterRedirect('Please activate or upgrade iService plugin!', true, WARNING);
            Html::redirect($CFG_GLPI['root_doc'] . '/front/plugin.php');
        } else {
            Session::addMessageAfterRedirect('iService plugin must be activated or upgraded, please contact the administrator!', true, ERROR);
            Html::redirect($CFG_GLPI['root_doc'] . '/front/central.php');
        }
    }
}

function _t(string $string): string
{
    if (!function_exists('__')) {
        return $string;
    }

    return __($string, 'iservice');
}

function _tn(string $string, string $plural, int $nb): string
{
    if (!function_exists('_n')) {
        return $nb > 1 ? $plural : $string;
    }

    return _n($string, $plural, $nb, 'iservice');
}

function plugin_iservice_json_decode_input_data(string $string): array
{
    $result = [];

    foreach (json_decode($string, true) as $key => $value) {
        $keys          = explode('[', $key);
        $current_array =& $result;

        foreach ($keys as $i => $subkey) {
            if ($subkey !== '') {
                $subkey = trim($subkey, ']');
                if (!isset($current_array[$subkey])) {
                    $current_array[$subkey] = [];
                }

                if ($i < count($keys) - 1) {
                    $current_array =& $current_array[$subkey];
                } else {
                    $current_array[$subkey] = $value;
                }
            }
        }
    }

    return $result;
}

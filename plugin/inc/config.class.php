<?php

use Glpi\Application\View\TemplateRenderer;

class PluginIserviceConfig extends CommonDBTM
{
    private const LOCAL_CONFIG_FILE   = PLUGIN_ISERVICE_DIR . '/config/local.php';
    private const DEFAULT_CONFIG_FILE = PLUGIN_ISERVICE_DIR . '/config/config.php';

    public static $rightname = 'config';

    protected $displaylist = false;

    public $auto_message_on_action = false;
    public $showdebug              = true;

    public function defineTabs($options = []): array
    {
        $ong = [];
        $this->addStandardTab(__CLASS__, $ong, $options);
        return $ong;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): array
    {
        switch ($item->getType()) {
        case __CLASS__:
            return [
                1 => __('General setup'),
                2 => __('Import'),
            ];
        default:
            break;
        }

        return [];
    }

    public function showFormGeneral(CommonGLPI $item): bool
    {
        echo "General";

        return true;
    }

    public function showFormImport(CommonGLPI $item): bool
    {
        global $CFG_PLUGIN_ISERVICE;

        echo TemplateRenderer::getInstance()->render(
            '@iservice/pages/admin/import.html.twig',
            [
                'url_base' => $CFG_PLUGIN_ISERVICE['root_doc'],
                'import_groups' => include __DIR__ . '/import.config.php',
            ]
        );

        return true;
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        switch ($tabnum) {
        case 1:
            $item->showFormGeneral($item);
            break;
        case 2:
            $item->showFormImport($item);
            break;
        default:
            break;
        }

        return true;
    }

    public static function getConfigValue(string $name)
    {
        $value = self::getValueFromSession($name);

        if ($value !== null) {
            return $value;
        }

        $value = self::getValueFromDatabase($name);

        if ($value !== null && $value != 'N/A') {
            self::setValueToSession($name, $value);
            return $value;
        }

        $value = self::getValueFromConfigFile($name, self::LOCAL_CONFIG_FILE);

        if ($value !== null) {
            self::setValueToSession($name, $value);
            return $value;
        }

        $value = self::getValueFromConfigFile($name, self::DEFAULT_CONFIG_FILE);

        if ($value !== null) {
            self::setValueToSession($name, $value);
            return $value;
        }

        return null;
    }

    public static function getValueFromSession(string $name)
    {
        return $_SESSION['plugin']['iservice']['config'][$name] ?? null;
    }

    public static function setValueToSession(string $name, $value)
    {
        $_SESSION['plugin']['iservice']['config'][$name] = $value;
    }

    public static function getValueFromDatabase(string $name)
    {
        $config = new self();
        $config->getFromDBByCrit(
            [
                'name' => $name,
            ]
        );

        if ($config->getField('value') !== null) {
            return $config->getField('value');
        }
    }

    public static function getValueFromConfigFile(string $name, string $file)
    {
        $localConfig = include $file;

        return $localConfig[$name] ?? null;
    }

    public static function handleConfigValues(): void
    {
        if (isset($_SESSION['plugin']['iservice']['config']) && count($_SESSION['plugin']['iservice']['config']) > 0) {
            return;
        }

        $valuesFromDB = (new self)->find();
        $valuesFromDB = array_combine(array_column($valuesFromDB, 'name'), array_column($valuesFromDB, 'value')) ?? [];

        $valuesFromLocalConfig = include self::LOCAL_CONFIG_FILE ?? [];
        $valuesFromMainConfig  = include self::DEFAULT_CONFIG_FILE ?? [];

        $configArray = array_merge($valuesFromMainConfig, $valuesFromLocalConfig, $valuesFromDB);

        $_SESSION['plugin']['iservice']['config'] = $configArray;
        self::overrideConfig($configArray);
    }

    public static function overrideConfig($configArray): void
    {
        if (empty($configArray)) {
            return;
        }

        $configValuesToOverride = array_filter(
            array_keys($configArray), function ($key) {
                return str_starts_with($key, 'config_override');
            }
        );

        if (!empty($configValuesToOverride)) {
            foreach ($configValuesToOverride as $configValueToOverride) {
                list($prefix, $context, $configName) = explode('.', $configValueToOverride, 3);

                $config = new Config();
                $config->getFromDBByCrit(
                    [
                        'context' => $context,
                        'name' => $configName,
                    ]
                );
                $config->update(
                    [
                        $config->getIndexName() => $config->getID(),
                        'context' => $context,
                        'value' => $configArray[$configValueToOverride],
                    ]
                );
            }
        }
    }

}

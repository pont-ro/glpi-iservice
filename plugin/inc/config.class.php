<?php

use Glpi\Application\View\TemplateRenderer;

class PluginIserviceConfig extends CommonDBTM
{
    private const LOCAL_CONFIG_FILE     = PLUGIN_ISERVICE_DIR . '/config/local.php';
    private const DEFAULT_CONFIG_FILE   = PLUGIN_ISERVICE_DIR . '/config/config.php';
    private static array $localConfig   = [];
    private static array $defaultConfig = [];

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

    public static function getConfigValue(string $name): ?string
    {
        $value = self::getValueFromSession($name);

        if ($value !== null) {
            return $value;
        }

        $value = self::getValueFromDatabase($name);

        if ($value !== null && $value != 'N/A') {
            self::setValueInSession($name, $value);
            return $value;
        }

        $value = self::getValueFromArray($name, self::getLocalConfig());

        if ($value !== null) {
            self::setValueInSession($name, $value);
            return $value;
        }

        $value = self::getValueFromArray($name, self::getDefaultConfig());

        if ($value !== null) {
            self::setValueInSession($name, $value);
            return $value;
        }

        return null;
    }

    public static function getValueFromSession(string $name): ?string
    {
        return $_SESSION['plugin']['iservice']['config'][$name] ?? null;
    }

    public static function setValueInSession(string $name, $value): void
    {
        $_SESSION['plugin']['iservice']['config'][$name] = $value;
    }

    public static function getValueFromDatabase(string $name): ?string
    {
        $config = new self();
        $config->getFromDBByCrit(
            [
                'name' => $name,
            ]
        );

        return $config?->getField('value');
    }

    public static function getValueFromArray(string $name, array $fileConfig): ?string
    {
        return $fileConfig[$name] ?? null;
    }

    public static function handleConfigValues(): void
    {
        if (isset($_SESSION['plugin']['iservice']['config']) && count($_SESSION['plugin']['iservice']['config']) > 0) {
            return;
        }

        $valuesFromDB = (new self)->find();
        $valuesFromDB = array_combine(array_column($valuesFromDB, 'name'), array_column($valuesFromDB, 'value')) ?? [];

        $valuesFromLocalConfig   = self::getLocalConfig();
        $valuesFromDefaultConfig = self::getDefaultConfig();

        $configArray = array_merge($valuesFromDefaultConfig, $valuesFromLocalConfig, $valuesFromDB);

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

                if (empty($config->fields)) {
                    continue;
                }

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

    public static function getLocalConfig(): array
    {
        if (empty(self::$localConfig) && file_exists(self::LOCAL_CONFIG_FILE)) {
            $content = include self::LOCAL_CONFIG_FILE;
            self::$localConfig = is_array($content) ? $content : [];
        }

        return self::$localConfig;
    }

    public static function getDefaultConfig(): array
    {
        if (empty(self::$defaultConfig) && file_exists(self::DEFAULT_CONFIG_FILE)) {
            $content = include self::DEFAULT_CONFIG_FILE;
            self::$defaultConfig = is_array($content) ? $content : [];
        }

        return self::$defaultConfig;
    }

}

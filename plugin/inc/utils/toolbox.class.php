<?php

// Renamed from PluginIserviceCommon.
namespace GlpiPlugin\Iservice\Utils;

use DateTime;
use Dropdown;
use PluginFieldsCartridgeitemtypeDropdown;
use PluginFieldsContainer;
use PluginFieldsField;
use PluginIserviceDB;
use PluginIservicePrinter;
use TValuta;

if (!defined('INPUT_REQUEST')) {
    define('INPUT_REQUEST', 99);
}

class ToolBox
{
    const RESPONSE_OK    = 'OK';
    const RESPONSE_ERROR = 'ERROR';

    public const CARTRIDGE_COLOR_ID_BLACK            = 1;
    public const CARTRIDGE_COLOR_ID_CYAN             = 2;
    public const CARTRIDGE_COLOR_ID_MAGENTA          = 3;
    public const CARTRIDGE_COLOR_ID_YELLOW           = 4;
    public const CARTRIDGE_COLOR_ID_MAT_BLACK        = 5;
    public const CARTRIDGE_COLOR_ID_FOTO_BLACK       = 6;
    public const CARTRIDGE_COLOR_ID_UNKNOWN          = 7;
    public const CARTRIDGE_COLOR_ID_REPLACEMENT_PART = 8;
    public const CARTRIDGE_COLOR_ID_COLOR            = 9;

    public static array $usersByProfile = [];

    protected static $codmatValues        = [];
    protected static $exchangeRateService = null;

    public static $lastExchangeRateServiceError = null;

    public static $expert_line_id = null;

    public static function getArrayInputVariable($variable_name, $default_value = null, $input_type = INPUT_REQUEST): ?array
    {
        if (!is_array($default_value) && $default_value !== null) {
            return null;
        }

        $get_result  = filter_input(INPUT_GET, 'iServiceCompressedInputData') ? ($_GET[$variable_name] ?? null) : filter_input(INPUT_GET, $variable_name, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        $post_result = filter_input(INPUT_POST, 'iServiceCompressedInputData') ? ($_POST[$variable_name] ?? null) : filter_input(INPUT_POST, $variable_name, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

        return match ($input_type) {
            INPUT_GET => $get_result ?? $default_value,
            INPUT_POST => $post_result ?? $default_value,
            INPUT_REQUEST => ($get_result === null && $post_result === null) ? $default_value : ((array) $get_result + (array) $post_result),
            default => null,
        };
    }

    public static function getInputVariables(array $variables, $input_type = INPUT_REQUEST): array
    {
        $result = [];

        foreach ($variables as $variableName => $variableDefaultValue) {
            if (is_numeric($variableName)) {
                $variableName         = $variableDefaultValue;
                $variableDefaultValue = null;
            }

            $result[$variableName] = self::getInputVariable($variableName, $variableDefaultValue, $input_type);
        }

        return $result;
    }

    public static function getInputVariable($variable_name, $default_value = null, $input_type = INPUT_REQUEST): mixed
    {
        $get_result  = filter_input(INPUT_GET, 'iServiceCompressedInputData') ? ($_GET[$variable_name] ?? null) : filter_input(INPUT_GET, $variable_name);
        $post_result = filter_input(INPUT_POST, 'iServiceCompressedInputData') ? ($_POST[$variable_name] ?? null) : filter_input(INPUT_POST, $variable_name);

        return match ($input_type) {
            INPUT_GET => $get_result ?? $default_value,
            INPUT_POST => $post_result ?? $default_value,
            INPUT_REQUEST => $get_result ?? $post_result ?? $default_value,
            default => null,
        };
    }

    public static function getHtmlSanitizedValue($value): string
    {
        return preg_replace('((?![\w\-]).)', '-', trim($value));
    }

    public static function addKeysToArray(array $keys, array $array): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = null;
        }

        return array_merge($result, $array);
    }

    public static function inProfileArray($profiles): bool
    {
        if (empty($profiles)) {
            return false;
        }

        if (!is_array($profiles)) {
            if (func_num_args() > 1) {
                $profiles = func_get_args();
            } else {
                $profiles = [$profiles];
            }
        }

        return in_array($_SESSION["glpiactiveprofile"]["name"] ?? [], $profiles);
    }

    public static function getExchangeRate($currency = 'Euro'): ?float
    {
        if (self::$exchangeRateService === null) {
            if (!class_exists('TValuta')) {
                include_once __DIR__ . '/../TValuta.php';
            }

            self::$exchangeRateService = new TValuta();
        }

        try {
            $propertyName = $currency . 'CaNumar';
            return self::$exchangeRateService->$propertyName ?? null;
        } catch (\Exception $ex) {
            self::$lastExchangeRateServiceError = $ex->getMessage();
        }

        return null;
    }

    public static function numberFormat(float $number, int $decimals = 2, ?string $decimal_separator = '.', ?string $thousands_separator = ''): string
    {
        return number_format($number, $decimals, $decimal_separator, $thousands_separator);
    }

    public static function getValueFromInput($variable_name, $input, $empty_value = '', $return_index = 0)
    {
        if (!isset($input[$variable_name])) {
            return $empty_value;
        }

        return is_array($input[$variable_name]) ? ($input[$variable_name][$return_index] ?? $empty_value) : $input[$variable_name];
    }

    public static function getItemsIdFromInput($input, $itemtype, $empty_value = '', $return_index = 0): int|string
    {
        if (!isset($input['items_id'])) {
            return $empty_value;
        }

        if (is_array($input['items_id'])) {
            return is_array($input['items_id'][$itemtype] ?? null) ? ($input['items_id'][$itemtype][$return_index] ?? $empty_value) : $empty_value;
        } else {
            return $input['items_id'];
        }
    }

    public static function getSumOfUnpaidInvoicesLink($supplier_id, $supplier_code)
    {
        global $CFG_PLUGIN_ISERVICE;

        $sum   = 0;
        $query = "
					SELECT sum(f.valinc-f.valpla) as total_facturi, count(*) as nr_facturi, min(f.datafac) datafac_min, max(f.datafac) datafac_max
					FROM glpi_plugin_iservice_suppliers s
					JOIN hmarfa_facturi f on f.codbenef = s.hmarfa_code_field AND f.tip like 'TF%' AND f.valinc-f.valpla > 0
					WHERE s.id = $supplier_id AND f.valinc > f.valpla
					GROUP BY s.id
					";

        $queryResult = PluginIserviceDB::getQueryResult($query);

        if (!is_iterable($queryResult)) {
            return $sum;
        }

        foreach ($queryResult as $row) {
            $sum = self::numberFormat($row['total_facturi']) . ' RON';
            if ($row['total_facturi'] > 0) {
                $date_now  = new DateTime();
                $date_diff = $date_now->diff(new DateTime($row['datafac_min']))->format('%a');
                $style     = $date_diff > 29 ? " style='color:red;'" : "";
                if ($row['nr_facturi'] == 1) {
                    $sum = sprintf("%s (1 factură din <span%s>%s</span>)", $sum, $style, $row['datafac_min']);
                } else {
                    $sum = sprintf("%s (%d facturi intre <span%s>%s și %s</span>)", $sum, $row['nr_facturi'], $style, $row['datafac_min'], $row['datafac_max']);
                }
            }
        }

        return "<a target='_blank' href='$CFG_PLUGIN_ISERVICE[root_doc]/front/views.php?view=UnpaidInvoices&unpaidinvoices0[cod]=$supplier_code'>$sum</a>";
    }

    public static function clearAfterRedirectMessages($type = null)
    {
        if ($type === null) {
            $_SESSION["MESSAGE_AFTER_REDIRECT"] = [];
        } elseif (isset($_SESSION["MESSAGE_AFTER_REDIRECT"][$type])) {
            $_SESSION["MESSAGE_AFTER_REDIRECT"][$type] = [];
        }
    }

    public static function writeCsvFile($fileName, $data, $append = false): ?string
    {
        if (!is_array($data)) {
            return 'Input data must be a 2 dimensional array';
        }

        if (false === ($file = fopen($fileName, $append ? 'a' : 'w'))) {
            return print_r(error_get_last(), true);
        }

        foreach ($data as $fields) {
            if (!is_array($fields)) {
                continue;
            }

            fputcsv($file, $fields);
        }

        fclose($file);
        return null;
    }

    public static function getCsvFile(string $filename, string $separator = ',', string $enclosure = '"', string $escape = '\\'): array
    {
        $file = fopen($filename, 'r');

        $result = [];

        while (!feof($file) && false !== ($line = fgetcsv($file, null, $separator, $enclosure, $escape))) {
            $result[] = $line;
        }

        return $result;
    }

    public static function addMonthToDate($date_in_string, $number_of_months): ?string
    {
        if (empty($date_in_string)) {
            return null;
        }

        $date = new DateTime($date_in_string);

        $oldDay = $date->format("d");
        $date->add(new \DateInterval("P" . $number_of_months . "M"));
        $newDay = $date->format("d");

        if ($oldDay != $newDay) {
            $date->sub(new \DateInterval("P" . $newDay . "D"));
        }

        return $date->format("Y-m-d");
    }

    public static function isDateEmpty($dateValue)
    {
        return empty($dateValue) || trim($dateValue) === '0000-00-00' || trim($dateValue) === '0000-00-00 00:00:00';
    }

    public static function isValidDateTime($dateValue): bool
    {
        if (self::isDateEmpty($dateValue)) {
            return false;
        }

        $date = DateTime::createFromFormat('Y-m-d H:i:s', $dateValue);
        return $date && $date->format('Y-m-d H:i:s') === $dateValue;
    }

    public static function clearNotAllowedTags(string $string, array $allowedTags = null): string
    {
        return PluginIserviceDB::escapeString(strip_tags($string, $allowedTags ?? ['<strong>', '<b>', '<i>', '<em>', '<u>', '<br>', '<p>', '<ul>', '<li>', '<ol>', '<a>']));
    }

    public static function unlinkRecursively($filePath): void
    {

        if (is_file($filePath)) {
            unlink($filePath);
        }

        if (is_dir($filePath)) {
            $objects = scandir($filePath);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    self::unlinkRecursively($filePath . '/' . $object);
                }
            }

            rmdir($filePath);
        }
    }

    public static function getMenuConfig(): array
    {
        $menuConfig = $_SESSION['plugin']['iservice']['menuConfig'] ?? null;

        if (empty($menuConfig)) {
            $menuConfig = $_SESSION['plugin']['iservice']['menuConfig'] = self::getMenuConfigFromConfigFile();
        }

        return $menuConfig;
    }

    public static function getMenuConfigFromConfigFile(): ?array
    {
        $configFile = GLPI_ROOT . "/plugins/iservice/config/menu.php";

        if (!file_exists($configFile)) {
            return null;
        }

        return include_once GLPI_ROOT . "/plugins/iservice/config/menu.php" ?: [];

    }

    public static function getCodmatValue(string $codmat): string
    {
        if (!isset(self::$codmatValues[$codmat])) {
            self::$codmatValues[$codmat] = PluginIserviceDB::getQueryResult("SELECT * FROM hmarfa_nommarfa WHERE cod = '$codmat'", 'codmat')[0] ?? [];
        }

        return self::$codmatValues[$codmat]['denum'] ?? '';
    }

    public static function getIdentifierByAttribute(string $modelName, string $attributeValue, string $attributeToSearchFor = null, string $attributeToReturn = 'id'): mixed
    {
        if (!($table = getTableForItemType($modelName))) {
            return null;
        }

        if (empty($attributeToSearchFor)) {
            $attributeToSearchFor = $modelName::getNameField();
        }

        $model = new $modelName();

        if (!$model->getFromDBByCrit([$attributeToSearchFor => $attributeValue])) {
            return null;
        }

        return $model->fields[$attributeToReturn] ?? null;
    }

    public static function setUsersByProfileFromDb(string $profileName): void
    {
        global $DB;

        $criteria = [
            'SELECT'         => ['glpi_users.id', 'glpi_users.realname', 'glpi_users.firstname', 'glpi_users.name'],
            'FROM'            => 'glpi_users',
            'LEFT JOIN'       => [
                'glpi_profiles_users'   => [
                    'ON' => [
                        'glpi_profiles_users'   => 'users_id',
                        'glpi_users'            => 'id'
                    ]
                ],
                'glpi_profiles' => [
                    'ON' => [
                        'glpi_profiles_users'   => 'profiles_id',
                        'glpi_profiles'         => 'id'
                    ]
                ],
            ],
            'WHERE'           => [
                'glpi_users.is_deleted' => 0,
                'glpi_profiles.name' => $profileName
            ],
            'ORDER'           => ['glpi_users.realname', 'glpi_users.firstname']
        ];

        $result = $DB->request($criteria);

        foreach ($result as $user) {
            self::$usersByProfile[$profileName][$user['id']] = (!empty($user['realname']) || !empty($user['firstname'])) ? $user['realname'] . ' ' . $user['firstname'] : $user['name'];
        }
    }

    public static function getUserIdByName($name)
    {
        $user = new \User();
        $user->getFromDBbyName($name);
        $id = $user->getID();

         return $id > -1 ? $id : 0;
    }

    public static function getUsersByProfiles(array $profileNames): array
    {
        $users = [
            0 => Dropdown::EMPTY_VALUE
        ];

        foreach ($profileNames as $profileName) {
            if (empty(self::$usersByProfile[$profileName])) {
                self::setUsersByProfileFromDb($profileName);
            }

            if (empty(self::$usersByProfile[$profileName])) {
                continue;
            }

            $users += self::$usersByProfile[$profileName];
        }

        natcasesort($users);

        return $users;
    }

    public static function br2nl($input)
    {
        return preg_replace('/<br\s?\/?>/ius', "\n", str_replace("\n", "", str_replace("\r", "", htmlspecialchars_decode($input))));
    }

    public static function getExpertLineId(): int|null
    {
        if (empty(self::$expert_line_id)) {
            self::$expert_line_id = self::getIdentifierByAttribute('Supplier', 'Expert Line srl');
        }

        return self::$expert_line_id;
    }

    public static function preprocessInputValuesForCustomFields($className, &$input): void
    {
        $containerId = PluginFieldsContainer::findContainer($className);

        $fields = [];

        if ($containerId) {
            $field_obj = new PluginFieldsField();
            $fields    = $field_obj->find(
                [
                    'plugin_fields_containers_id' => $containerId
                ]
            );
        }

        foreach ($fields as $field) {
            if (!$field['is_active'] || $field['type'] == "yesno" || $field['type'] == "header") {
                continue;
            }

            $value = $input[$field['name']] ?? null;
            if ($field['type'] === 'number' && !empty($value) && strtoupper($value) !== 'NULL' && !is_numeric($value)) {
                unset($input[$field['name']]);
            }
        }
    }

    public static function isPrinterColorOrPlotter(mixed $item): bool
    {
        if ($item instanceof \Printer) {
            $printer = $item;
        } elseif (is_numeric($item)) {
            $printer = new \Printer();
            if (!$printer->getFromDB($item)) {
                return false;
            }
        } else {
            return false;
        }

        return in_array($printer->fields['printertypes_id'], [PluginIservicePrinter::ID_COLOR_TYPE, PluginIservicePrinter::ID_PLOTTER_TYPE]);
    }

    public static function filterVarArray(int $inputType): array
    {
        if ($inputType === INPUT_POST) {
            global $_UPOST;
            return filter_var_array($_UPOST);
        } elseif ($inputType === INPUT_GET) {
            global $_UGET;
            return filter_var_array($_UGET);
        }

        return [];
    }

    public static function getCartridgeNameByColorId(int $colorId): string
    {
        if (!isset($_SESSION['catridgesColorsNamesById'])) {
            $_SESSION['catridgesColorsNamesById'] = [];
        }

        if (!isset($_SESSION['catridgesColorsNamesById'][$colorId])) {
            $_SESSION['catridgesColorsNamesById'][$colorId] = self::getCartridgeNameByColorIdFromDb($colorId);
        }

        return $_SESSION['catridgesColorsNamesById'][$colorId];

    }

    public static function getCartridgeNameByColorIdFromDb(int $colorId): string
    {
        return (new PluginFieldsCartridgeitemtypeDropdown())->getById($colorId)->fields['completename'] ?? '';
    }

    public static function getSerialCopyButton($serial, $url = 'https://www.srv.ygles.com/ccb/Login'): string
    {
        if (empty($serial)) {
            return '';
        }

        $js = "
        navigator.clipboard.writeText(\"" . preg_replace('/\s+/', '', $serial) . "\");
        window.open('$url', '_blank');
        ";

        return "<a 
            href='javascript:void(0);' 
            onclick=\"" . htmlspecialchars($js, ENT_QUOTES) . "\"
            title='" . _t(' Copy') . "' 
            class='noprint'>
                <i class='ti ti-copy'></i>
            </a>";
    }

    public static function insertArrayValuesAndKeysAfterKey(string $key, array $originalArray, array $arrayToInsert): array
    {
        $newColumns = [];

        foreach ($originalArray as $originalKey => $value) {
            $newColumns[$originalKey] = $value;

            if ($originalKey === $key) {
                foreach ($arrayToInsert as $extraKey => $extraValue) {
                    $newColumns[$extraKey] = $extraValue;
                }
            }
        }

        return $newColumns;

    }

    public static function cleanHtml(string $html): string
    {
        $text = preg_replace('/<p[^>]*>(.*?)<\/p>/is', "$1\n", $html);
        $text = preg_replace('/<br[^>]*>/i', "\n", $text);

        $text = strip_tags($text);

        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

}

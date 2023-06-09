<?php

namespace GlpiPlugin\Iservice\Utils;

if (!defined('INPUT_REQUEST')) {
    define('INPUT_REQUEST', 99);
}

class ToolBox
{
    const RESPONSE_OK = 'OK';

    public static function getArrayInputVariable($variable_name, $default_value = null, $input_type = INPUT_REQUEST): ?array
    {
        if (!is_array($default_value) && $default_value !== null) {
            return null;
        }

        $get_result  = filter_input(INPUT_GET, $variable_name, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        $post_result = filter_input(INPUT_POST, $variable_name, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

        return match ($input_type) {
            INPUT_GET => $get_result ?? $default_value,
            INPUT_POST => $post_result ?? $default_value,
            INPUT_REQUEST => ($get_result === null && $post_result === null) ? $default_value : array_merge((array) $get_result, (array) $post_result),
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
        $get_result  = filter_input(INPUT_GET, $variable_name);
        $post_result = filter_input(INPUT_POST, $variable_name);

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

        return in_array($_SESSION["glpiactiveprofile"]["name"], $profiles);
    }

}

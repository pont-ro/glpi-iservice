<?php

namespace GlpiPlugin\Iservice\Utils;

class HtaccessChecker
{

    public static function check(): void
    {
        self::forceHttps();
    }

    public static function forceHttps(): void
    {
        $rule = <<<EOT
            RewriteEngine On
            RewriteCond %{HTTPS} !=on
            RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
            EOT;

        $currentDir = basename(getcwd());
        if ($currentDir == 'glpi') {
            $htaccessFile = '.htaccess';
        } else {
            $htaccessFile = '../.htaccess';
        }

        if (file_exists($htaccessFile)) {
            $fileContent = file_get_contents($htaccessFile);
        } else {
            $fileContent = '';
        }

        if ($fileContent !== false && strpos($fileContent, $rule) === false) {
            // Append the rule to the file.
            $fileContent .= PHP_EOL . PHP_EOL . $rule;

            file_put_contents($htaccessFile, $fileContent);

            // Reload the page.
            header('Location: ' . $_SERVER['REQUEST_URI']);
        }

    }

}

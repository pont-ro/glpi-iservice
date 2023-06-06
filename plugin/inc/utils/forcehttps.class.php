<?php

namespace GlpiPlugin\Iservice\Utils;

class ForceHttps
{

    public static function do(): void
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
            $fileContent .= PHP_EOL . '# iService required lines start';
            $fileContent .= PHP_EOL . $rule;
            $fileContent .= PHP_EOL . '# iService required lines end' . PHP_EOL;

            file_put_contents($htaccessFile, $fileContent);
        } else {
            echo "Failed to read the .htaccess file.";
        }

    }

}

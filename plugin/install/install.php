<?php
/**
 * ---------------------------------------------------------------------
 * Formcreator is a plugin which allows creation of custom forms of
 * easy access.
 * ---------------------------------------------------------------------
 * LICENSE
 *
 * This file is part of Formcreator.
 *
 * Formcreator is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Formcreator is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Formcreator. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2011 - 2021 Teclib'
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/pluginsGLPI/formcreator/
 * @link      https://pluginsglpi.github.io/formcreator/
 * @link      http://plugins.glpi-project.org/#/plugin/formcreator
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}
class PluginIserviceInstall
{
    const ASSETS_TO_CHANGE = [
        'pics/favicon.ico',
        'pics/logos/logo-G-100-black.png',
        'pics/logos/logo-G-100-grey.png',
        'pics/logos/logo-G-100-white.png',
        'pics/logos/logo-GLPI-100-black.png',
        'pics/logos/logo-GLPI-100-grey.png',
        'pics/logos/logo-GLPI-100-white.png',
        'pics/logos/logo-GLPI-250-black.png',
        'pics/logos/logo-GLPI-250-grey.png',
        'pics/logos/logo-GLPI-250-white.png',
    ];

    public function install(): bool
    {
        $this->overwriteAssets();

        return true;
    }

    public function uninstall(): void
    {
        $this->revertAssets();
    }

    public function isPluginInstalled(): bool
    {
        return true;
    }

    private function overwriteAssets(): void
    {
        foreach (self::ASSETS_TO_CHANGE as $fileName) {
            if (!file_exists(GLPI_ROOT . "/$fileName.iSb")) {
                rename(GLPI_ROOT . "/$fileName", GLPI_ROOT . "/$fileName.iSb");
                copy(PLUGIN_ISERVICE_DIR . "/assets/$fileName", GLPI_ROOT . "/$fileName");
            }
        }
    }

    private function revertAssets(): void
    {
        foreach (self::ASSETS_TO_CHANGE as $fileName) {
            if (file_exists(GLPI_ROOT . "/$fileName.iSb")) {
                unlink(GLPI_ROOT . "/$fileName");
                rename(GLPI_ROOT . "/$fileName.iSb", GLPI_ROOT . "/$fileName");
            }
        }
    }

}

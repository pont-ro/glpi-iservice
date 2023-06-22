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
 *
 * @copyright Copyright © 2011 - 2021 Teclib'
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/pluginsGLPI/formcreator/
 * @link      https://pluginsglpi.github.io/formcreator/
 * @link      http://plugins.glpi-project.org/#/plugin/formcreator
 * ---------------------------------------------------------------------
 */
namespace GlpiPlugin\iService;

use GlpiPlugin\iService\InstallSteps\AddCustomFieldsInstallStep;
use GlpiPlugin\iService\InstallSteps\CreateTablesInstallStep;
use GlpiPlugin\iService\InstallSteps\OverwriteAssetsInstallStep;
use GlpiPlugin\iService\InstallSteps\HandleProfileRightsInstallStep;
use GlpiPlugin\iService\InstallSteps\CronTasksInstallStep;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIserviceInstall
{

    public function install(): bool
    {
        $result = OverwriteAssetsInstallStep::do();
        $result = $result && CreateTablesInstallStep::do();
        $result = $result && AddCustomFieldsInstallStep::do();
        $result = $result && HandleProfileRightsInstallStep::do();
        return $result && CronTasksInstallStep::do();
    }

    public function uninstall(): void
    {
        AddCustomFieldsInstallStep::undo();
        CreateTablesInstallStep::undo();
        OverwriteAssetsInstallStep::undo();
        HandleProfileRightsInstallStep::undo();
    }

    public function isPluginInstalled(): bool
    {
        return true;
    }

}

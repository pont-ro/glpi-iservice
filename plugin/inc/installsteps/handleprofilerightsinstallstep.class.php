<?php

namespace GlpiPlugin\Iservice\InstallSteps;

class HandleProfileRightsInstallStep
{
    const CLEANUP_ON_UNINSTALL = true;

    public static function do(): bool
    {
        global $DB;

        $table = 'glpi_profilerights';
        $superAdminProfileId = \Profile::getSuperAdminProfilesId();
        $rightsValues = include __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'rights.config.php';
        $result = false;

        if (!self::backupTable($table)) {
            return false;
        }

        foreach ($rightsValues as $rightName => $rightValue) {
            $result = $DB->update(
                $table,
                [
                    'rights' => $rightValue,
                ],
                [
                    'WHERE'  => [
                        'name' => $rightName,
                        'profiles_id' => ['!=', $superAdminProfileId],
                    ],
                ]
            );

            if ($result === false) {
                return false;
            }
        }

        return $result;
    }

    public static function undo(): void
    {
        if (!self::CLEANUP_ON_UNINSTALL) {
            return;
        }

        global $DB;

        $DB->runFile(PLUGIN_ISERVICE_DIR . '/install/sql/glpi_profilerights_original.sql');
    }

    public static function backupTable($tableName): bool
    {
        global $DB;
        $iterator =  $DB->request($tableName);

        if (!$iterator) {
            return false;
        }

        $sql = "TRUNCATE TABLE " . $tableName . ";\n";
        foreach ($iterator as $row) {
            $columns = implode(', ', array_keys($row));
            $values = implode(', ', array_map(function ($value) use ($DB) {
                return $DB->quote($value);
            }, $row));
            $sql .= "INSERT INTO $tableName ($columns) VALUES ($values);\n";
        }

        $outputFile = PLUGIN_ISERVICE_DIR . '/install/sql/' . $tableName . '_original.sql';
        if (file_put_contents($outputFile, $sql) !== false) {
            return true;
        }

        return false;
    }

}

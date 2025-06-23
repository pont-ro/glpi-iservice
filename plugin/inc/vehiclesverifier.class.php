<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Iservice\Utils\ToolBox as IserviceToolBox;

class PluginIserviceVehiclesVerifier extends CommonDBTM
{

    public static function getTable($className = null): string
    {
        if (empty($className)) {
            $className = 'VehiclesVerifier';
        }

        return parent::getTable($className);
    }

    public static function getTypeName($nb = 0): string
    {
        return _t('Vehicles Expirables Verifier');
    }

    public static function cronInfo($name): ?array
    {
        switch ($name) {
        case 'mailVehiclesVerify':
            return [
                'description' => _t('Verifies expiring vehicle documents and sends email'),
                'parameter'   => _t('Days threshold (will check expirables expiring within this many days)')
            ];
        }

        return null;
    }

    /**
     * Cron action on mailVehiclesVerify
     *
     * @param $task
     *
     * @return -1 : done but not finish 1 : done with success
     * */
    public static function cronmailVehiclesVerify($task): int
    {
        global $CFG_GLPI, $CFG_PLUGIN_ISERVICE;

        $commentParts = explode("\n", $task->fields['comment'], 2);
        $targetEmail  = $commentParts[0];

        if (empty($targetEmail)) {
            $task->log("Invalid email address: $targetEmail\n");
            return -1;
        }

        $daysThreshold = !empty($task->fields['param']) ? (int) $task->fields['param'] : PluginIserviceVehicleExpirable::EXPIRATION_SOON_DAYS;

        $task->log("Verifying vehicle expirables that will expire within $daysThreshold days\n");

        $futureDate = date('Y-m-d H:i:s', strtotime("+$daysThreshold days"));

        // Query all expirables that are expired or will expire within the threshold.
        $query = "SELECT 
                ve.id,
                ve.vehicle_id,
                ve.name,
                ve.description,
                ve.expiration_date,
                TIMESTAMPDIFF(DAY, NOW(), ve.expiration_date) as days_until_expiration,
                v.name as vehicle_name,
                v.license_plate
            FROM glpi_plugin_iservice_vehicle_expirables ve
            LEFT JOIN glpi_plugin_iservice_vehicles v ON v.id = ve.vehicle_id
            WHERE ve.expiration_date <= '$futureDate'
            ORDER BY ve.expiration_date ASC";

        $expirables = PluginIserviceDB::getQueryResult($query);

        if (empty($expirables)) {
            $task->log("No expiring vehicle documents found.\n");
            return 0; // Nothing to do.
        }

        $expirableTypes = PluginIserviceVehicleExpirable::getExpirableTypes();

        $emailMessage  = "<b>" . _t('The following vehicle documents are expired or about to expire') . "</b>:<br>";
        $emailMessage .= "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        $emailMessage .= "<thead>
            <tr>
                <th>" . _t('Vehicle') . "</th>
                <th>" . _t('License Plate') . "</th>
                <th>" . _t('Document Type') . "</th>
                <th>" . _t('Expiration Date') . "</th>
                <th>" . _t('Status') . "</th>
                <th>" . _t('Days') . "</th>
                <th>" . _t('Description') . "</th>
                <th>" . _t('Actions') . "</th>
            </tr>
        </thead>";
        $emailMessage .= "<tbody>";

        $count = 0;
        foreach ($expirables as $expirable) {
            $siteUrl     = PluginIserviceConfig::getConfigValue('site_url');
            $vehicleLink = sprintf(
                "<a href='%s%s/front/vehicle.form.php?id=%s' target='_blank'>%s</a>",
                $siteUrl,
                $CFG_PLUGIN_ISERVICE['root_doc'],
                $expirable['vehicle_id'],
                $expirable['vehicle_name']
            );

            $expirableLink = sprintf(
                "<a href='%s%s/front/vehicleexpirable.form.php?id=%s' target='_blank'>%s</a>",
                $siteUrl,
                $CFG_PLUGIN_ISERVICE['root_doc'],
                $expirable['id'],
                _t('Details')
            );

            $documentType = isset($expirableTypes[$expirable['name']])
                ? $expirableTypes[$expirable['name']]
                : $expirable['name'];

            // Determine status and CSS class.
            $days = (int) $expirable['days_until_expiration'];
            if ($days < 0) {
                $status    = "<span style='color: red; font-weight: bold;'>" . _t('Expired') . "</span>";
                $daysText = "<span style='color: red; font-weight: bold;'>" . abs($days) . " " . _t('days ago') . "</span>";
            } else if ($days <= 7) {
                $status    = "<span style='color: orange; font-weight: bold;'>" . _t('Expiring Soon') . "</span>";
                $daysText = "<span style='color: orange; font-weight: bold;'>" . $days . " " . _t('days left') . "</span>";
            } else {
                $status    = "<span style='color: green;'>" . _t('Valid') . "</span>";
                $daysText = "<span style='color: green;'>" . $days . " " . _t('days left') . "</span>";
            }

            $emailMessage .= sprintf(
                "<tr>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                </tr>",
                $vehicleLink,
                $expirable['license_plate'],
                $documentType,
                $expirable['expiration_date'],
                $status,
                $daysText,
                $expirable['description'] ?: '-',
                $expirableLink
            );

            $count++;
        }

        $emailMessage .= "</tbody></table>";
        $emailMessage .= "<br><p>" . _t('This is an automated message from the system. Please do not reply to this email.') . "</p>";

        $task->addVolume($count);
        $task->log("$count expiring vehicle documents detected.\n");

        $mmail = new GLPIMailer();
        $mmail->AddCustomHeader("Auto-Submitted: auto-generated");
        $mmail->AddCustomHeader("X-Auto-Response-Suppress: OOF, DR, NDR, RN, NRN");
        $mmail->SetFrom($CFG_GLPI["admin_email"], $CFG_GLPI["admin_email_name"], false);
        foreach (preg_split("/(,|;)/", $targetEmail) as $toAddress) {
            $mmail->AddAddress($toAddress);
        }

        $mmail->Subject = _t('Vehicle documents expiration notification');
        $mmail->msgHTML($emailMessage);
        if ($mmail->send()) {
            $task->log("Email sent to $targetEmail.\n");
        } else {
            $task->log("Error sending email to $targetEmail.\n");
        }

        return 1; // Done.
    }

}

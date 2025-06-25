<?php

namespace GlpiPlugin\Iservice\InstallSteps;

use \CronTask;
use \PluginIserviceConfig;

class CronTasksInstallStep
{

    private static function getCornTasksToInstall(): array
    {

        return [
            [
                'itemtype'  => 'PluginIserviceHMarfaImporter',
                'name'      => 'hMarfaImport',
                'mode'      => CronTask::MODE_EXTERNAL,
                'frequency' => 600,
                'param'     => 0,
                'state'     => 0,
                'hourmin'   => 0,
                'hourmax'   => 24,
                'comment'   => '/HAMOR/EXPERTLINE/hDATA/A_EXL08',
            ], [
                'itemtype'  => 'PluginIserviceEmaintenance',
                'name'      => 'em_mailgate',
                'mode'      => CronTask::MODE_EXTERNAL,
                'frequency' => 600,
                'param'     => 50,
                'state'     => 1,
                'hourmin'   => 0,
                'hourmax'   => 24,
                'comment'   => PluginIserviceConfig::getConfigValue('emaintenance.default_email'),
            ], [
                'itemtype'  => 'PluginIserviceTask_DataIntegrityTest',
                'name'      => 'DataIntegrityTest',
                'mode'      => CronTask::MODE_EXTERNAL,
                'frequency' => 300,
                'param'     => 0,
                'state'     => 1,
                'hourmin'   => 0,
                'hourmax'   => 24,
                'comment'   => '',
            ], [
                'itemtype'  => 'PluginIserviceBackupCleaner',
                'name'      => 'backupClean',
                'mode'      => CronTask::MODE_EXTERNAL,
                'frequency' => 86400,
                'param'     => 0,
                'state'     => 0,
                'hourmin'   => 5,
                'hourmax'   => 8,
                'comment'   => '',
            ], [
                'itemtype'  => 'PluginIservicePrinterDailyAverageCalculator',
                'name'      => 'printerDailyAverageCalculator',
                'mode'      => CronTask::MODE_EXTERNAL,
                'frequency' => 86400,
                'param'     => 0,
                'state'     => 1,
                'hourmin'   => 4,
                'hourmax'   => 7,
                'comment'   => 'emaintenance@expertline.ro',
            ], [
                'itemtype'  => 'PluginIserviceStockVerifier',
                'name'      => 'mailStockVerify',
                'mode'      => CronTask::MODE_EXTERNAL,
                'frequency' => 86400,
                'param'     => 0,
                'state'     => 1,
                'hourmin'   => 5,
                'hourmax'   => 8,
                'comment'   => 'zoltan.szegedi@expertline.ro',
            ], [
                'itemtype'  => 'PluginIserviceCartridgeVerifier',
                'name'      => 'mailCartridgeVerify',
                'mode'      => CronTask::MODE_EXTERNAL,
                'frequency' => 86400,
                'param'     => 0,
                'state'     => 1,
                'hourmin'   => 5,
                'hourmax'   => 8,
                'comment'   => 'service@expertline.ro',
            ], [
                'itemtype'  => 'PluginIserviceInboundLotPriceDeviationVerifier',
                'name'      => 'mailInboundLotPriceDeviationVerify',
                'mode'      => CronTask::MODE_EXTERNAL,
                'frequency' => 86400,
                'param'     => 5, // Default value for deviation percentage.
                'state'     => 1,
                'hourmin'   => 5,
                'hourmax'   => 8,
                'comment'   => 'zoltan.szegedi@expertline.ro',
            ], [
                'itemtype'  => 'PluginIserviceVehiclesVerifier',
                'name'      => 'mailVehiclesVerify',
                'mode'      => CronTask::MODE_EXTERNAL,
                'frequency' => 86400,
                'param'     => \PluginIserviceVehicleExpirable::EXPIRATION_SOON_DAYS,
                'state'     => 1,
                'hourmin'   => 5,
                'hourmax'   => 8,
                'comment'   => 'financiar@expertline.ro',
            ],
        ];

    }

    public static function do(): bool
    {
        $crontask = new CronTask();

        try {
            foreach (self::getCornTasksToInstall() as $ct) {
                if (!$crontask->getFromDBByRequest(
                    [
                        "WHERE" => [
                            'itemtype' => $ct['itemtype'],
                            'name' => $ct['name'],
                        ]
                    ]
                )
                ) {
                    $crontask->add(
                        [
                            'itemtype'  => $ct['itemtype'],
                            'name'      => $ct['name'],
                            'mode'      => $ct['mode'],
                            'frequency' => $ct['frequency'],
                            'param'     => $ct['param'],
                            'state'     => $ct['state'],
                            'hourmin'   => $ct['hourmin'],
                            'hourmax'   => $ct['hourmax'],
                            'comment'   => $ct['comment']
                        ]
                    );
                }
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
            return false;
        }

        return true;

    }

}

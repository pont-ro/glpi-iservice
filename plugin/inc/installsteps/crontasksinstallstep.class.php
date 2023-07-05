<?php

namespace GlpiPlugin\Iservice\InstallSteps;

use \CronTask;
class CronTasksInstallStep
{

    const CRON_TASKS = [
        [
            'itemtype'  => 'PluginIserviceHMarfaImporter',
            'name'      => 'hMarfaImport',
            'mode'      => CronTask::MODE_EXTERNAL,
            'frequency' => 600,
            'param'     => 0,
            'hourmin'   => 0,
            'hourmax'   => 24,
            'comment'   => '/HAMOR/EXPERTLINE/hDATA/A_EXL08',
        ], [
            'itemtype' => 'PluginIserviceEmaintenance',
            'name' => 'em_mailgate',
            'mode' => CronTask::MODE_EXTERNAL,
            'frequency' => 600,
            'param' => 50,
            'hourmin' => 0,
            'hourmax' => 24,
            'comment' => 'emaintenance@expertline.ro',
        ], [
            'itemtype' => 'PluginIserviceTask_DataIntegrityTest',
            'name' => 'DataIntegrityTest',
            'mode' => CronTask::MODE_EXTERNAL,
            'frequency' => 300,
            'param' => 0,
            'hourmin' => 0,
            'hourmax' => 24,
            'comment' => '',
        ],
    ];

    public static function do(): bool
    {
        $crontask = new CronTask();

        try {
            foreach (self::CRON_TASKS as $ct) {
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

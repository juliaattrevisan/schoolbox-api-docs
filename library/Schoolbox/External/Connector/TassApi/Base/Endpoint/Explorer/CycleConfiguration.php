<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Explorer;

use Schoolbox\External\Connector\TassApi\Base\Endpoint;

/**
 * Class CycleConfiguration
 *
 * A test class for exploring TASS contents without having to do a sync
 * to see how I derived values
 *

<?php
require_once __DIR__ . '/../conf/core/require.php';

$kernel = new \Schoolbox\Application\WebRequest\Kernel();
$config = new \Schoolbox\Config\Loader();

$host = $config->get('schooldata_import_endpoint_host');
$dsn = $config->get('schooldata_import_endpoint_key');

use Schoolbox\External\Connector\TassApi\Base\Endpoint;

$cycleConfiguration = new Endpoint\Explorer\CycleConfiguration([
Endpoint\Codec\DSN::MODULE_LMS_INTEGRATION => new Endpoint\Api($host, $dsn, Endpoint\Codec\DSN::MODULE_LMS_INTEGRATION),
Endpoint\Codec\DSN::MODULE_STUDENT_DETAILS => new Endpoint\Api($host, $dsn, Endpoint\Codec\DSN::MODULE_STUDENT_DETAILS),
Endpoint\Codec\DSN::MODULE_IDENTITY_MANAGEMENT => new Endpoint\Api($host, $dsn, Endpoint\Codec\DSN::MODULE_IDENTITY_MANAGEMENT)
]);

$cycleConfiguration->explore([1, 2]);

die();

 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Explorer
 */
class CycleConfiguration
{

    /**
     * @var array
     */
    private array $api;


    /**
     * CycleConfiguration constructor.
     *
     * @param array $api
     */
    public function __construct(array $api)
    {
        $this->api = $api;
    }


    /**
     * @param array $tassTT_IDs
     * @throws \DebugBar\DebugBarException
     */
    public function explore(array $tassTT_IDs)
    {
        $currentDate = date('Y-m-d');
        $payload = [];

        /* Intermediary step
         */
        $stack = [];

        /* Manufactured test payload
         */
        $cycles = array_fill_keys($tassTT_IDs, []);

        foreach ($cycles as $ttid => $cycle) {
            $timetableCalendar = $this->api[Endpoint\Codec\DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getTimetableCalendar', [
                'tt_id' => $ttid
            ]);

            /* If tass hasn't got any data for $ttid, exit early
             */
            if (!$timetableCalendar['tt_calendar'] || !\count($timetableCalendar['tt_calendar'])) {
                new \Alaress_Probe(sprintf(
                    'Skipping: no getTimetableCalendar data for ttid %s when preloading cycle configuration',
                    $ttid
                ));

                continue;
            }

            /* Otherwise continue processing its contents
             *
             * Index the $timetableCalendar data by (ttid, value) where VALUE <= today's date
             * ensuring we only remember meaningful records, discarding ones that aren't applicable
             */
            foreach ($timetableCalendar['tt_calendar'] as $current) {
                if ($current['VALUE'] <= $currentDate) {
                    $stack[$ttid][$current['VALUE']][] = $current;
                }
            }

            /* Sort them so in chronological order, then pop the last value
             * That's the date (and record) closest to today, but not in the future
             */
            uksort($stack[$ttid], static function ($a, $b) {
                return $a > $b;
            });

            $record = array_pop($stack[$ttid])[0];

            /* Mine it for all its worth
             */
            $payload[$ttid] = [

                /* Cycle start date
                 */
                'cycleStart' => $record['VALUE'],

                /* Starting Day number
                 */
                'dayStart' => $record['DAY_CODE']
            ];

            /* Return (ttid, startDate, dayNumber) tuples for later
             */
            new \Alaress_Probe('TASS data', 'Steve data');
            new \Alaress_Probe($record, $payload);
            new \Alaress_Probe('<hr />');
        }
    }
}

<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Timetable;

use Alaress_Date_Format;
use Schoolbox\External\Connector\Compass\Base\Endpoint\IApi;

/**
 * Data generator for structures from Compass.
 *
 * Structures are Compass' closest analog to a Schoolbox timetable structure:
 * they contain the number of days and periods which a timetable cycle contains.
 * They don't contain when the cycle starts or ends.
 */
class Structures
{
    /**
     * API for communicating with Compass.
     */
    private IApi $api;

    /**
     * Constructor
     *
     * @param IApi $api
     */
    public function __construct(IApi $api)
    {
        $this->api = $api;
    }

    /**
     * Generate and return structures.
     *
     * @return array
     */
    public function generate(): array
    {
        $stack      = [];
        $structures = $this->api->retrieveDocument('schedule/v1/GetStructures', []);
        foreach ($structures['d'] as $structure) {
            // Parse structure's settings
            // Period start and finish time will be in ISO8601,
            // need to parse it as whatever Schoolbox's current locale is.
            foreach ($structure['days'] as $i => $day) {
                foreach ($day['periods'] as $j => $period) {
                    // Alaress_Date_Format isn't very timezone-aware, so:
                    // * convert the UTC-marked start/finish times to stamps
                    // * create ADF objects from the stamps
                    $structure['days'][$i]['periods'][$j]['periodNumber'] = $j + 1;
                    $structure['days'][$i]['periods'][$j]['start'] = Alaress_Date_Format::newFromTimestamp(
                        strtotime($period['startTime']),
                    );
                    $structure['days'][$i]['periods'][$j]['finish'] = Alaress_Date_Format::newFromTimestamp(
                        strtotime($period['finishTime']),
                    );
                }
                // Days in Compass have no name, or even ordering:
                // I can only assume they're fetched in order
                $structure['days'][$i]['dayNumber']   = ($i + 1);
//                $structure['days'][$i]['dayName']     = 'Day ' . ($i + 1);
                $structure['days'][$i]['periodCount'] = count($day['periods']);
            }

            // For convenience, store a day and period count
            $structure['dayCount']    = count($structure['days']);
            $structure['periodCount'] = max(array_column($structure['days'], 'periodCount'));
            $stack[] = $structure;
        }

        // Figure out the max number of periods

        return $stack;
    }
}

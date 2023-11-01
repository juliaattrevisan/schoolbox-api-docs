<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint;

use PhpXmlRpc\Value;
use Psr\Log\LoggerInterface;

/**
 * Class Helper
 *
 * Some additional lower-level functionality that makes the liss data
 * a bit easier to work with
 */
class Helper
{
    /**
     * Some schools are creating class codes >64 chars long
     * e.g. "Addams Family Lead Vocal Rehearsals 12/10/2022 1.00pm to 1.30pm @ Drama Studio/ RH21A - A Clarke - #2688"
     */
    private const MAX_CLASS_CODE_LENGTH = 64;


    /**
     * Logs the things that happened, in a text file
     *
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * Constructor.
     *
     * @param LoggerInterface $log
     */
    public function __construct(LoggerInterface $log)
    {
        $this->log = $log;
    }


    /**
     * Preprocess all this stuff
     * Make it easier to understand because their data structure is blasphemously terrible
     *
     * @param array $allData
     * @return array
     * @throws \InvalidArgumentException
     */
    public function reformatAllEvents(array $allData): array
    {
        $allEvents = [];

        foreach ($allData as $currentEvent) {
            $allEvents[] = $this->reformatEvent($currentEvent);
        }

        return $allEvents;
    }


    /**
     * Schools are creating data with >64 char class codes
     * so that snaps our schema
     *
     * This method forcibly truncates anything beyond the maximum limit
     *
     * @param string $classCode
     * @return string
     */
    private function generateClassCode(string $classCode): string
    {
        return mb_substr($classCode, 0, self::MAX_CLASS_CODE_LENGTH, 'utf-8');
    }


    /**
     * Preprocess all this stuff
     * Make it easier to understand because their data structure is blasphemously terrible
     *
     * @param Value $currentEvent
     * @return array
     * @throws \InvalidArgumentException
     */
    private function reformatEvent(Value $currentEvent): array
    {
        $date = date('Y-m-d', strtotime($currentEvent->me['struct']['Date']->me['dateTime.iso8601']));
        $startDateTime = "{$date} {$currentEvent->me['struct']['StartTime']->me['string']}:00";
        $endDateTime = "{$date} {$currentEvent->me['struct']['EndTime']->me['string']}:00";

        /* Recompile everything
         */
        $restructured = [
            'guid' => trim((string)($currentEvent->me['struct']['Guid']->me['string'] ?? null)),

            /* Timing
             */
            'period' => trim((string)($currentEvent->me['struct']['Period']->me['string'] ?? null)),
            'startDateTime' => new \Alaress_Date_Format($startDateTime, \Alaress_Date_Format::DATABASE_FORMAT),
            'endDateTime' => new \Alaress_Date_Format($endDateTime, \Alaress_Date_Format::DATABASE_FORMAT),

            /* Detail
             */
            'code' => $this->generateClassCode(trim((string)($currentEvent->me['struct']['ClassCode']->me['string'] ?? null))),
            'name' => trim((string)($currentEvent->me['struct']['ClassName']->me['string'] ?? null)),
            'rooms' => trim((string)($currentEvent->me['struct']['Rooms']->me['string'] ?? null)),
            'type' => trim((string)($currentEvent->me['struct']['Type']->me['string'] ?? null)),
            'detail' => '',

            /* Involvements
             */
            'students' => [],

            /* Assemble numeric Teacher Ids and alphanumeric Teacher Codes
             * No idea why this has to be a csv, but students is an array...wevs
             */
            'teacherCodes' => array_filter(explode(',', (string)($currentEvent->me['struct']['TeacherCodes']->me['string'] ?? null))),
            'teacherIds' => array_filter(explode(',', (string)($currentEvent->me['struct']['TeacherIds']->me['string'] ?? null))),
        ];

        /* Assemble Students
         */
        if (isset($currentEvent->me['struct']['Students'])) {
            if (isset($currentEvent->me['struct']['Students']->me['string'])) {
                /* Sometimes they're a csv...
                 */
                $restructured['students'] = explode(',', $currentEvent->me['struct']['Students']->me['string']);
            } elseif (isset($currentEvent->me['struct']['Students']->me['array'])) {
                /* Sometimes they're an array...
                 */
                foreach ($currentEvent->me['struct']['Students']->me['array'] as $currentStudent) {
                    $restructured['students'][] = (string)$currentStudent->me['string'];
                }
            }
        }

        /* Dump this to a log for later inspection
         * Since this requires schools to push data to us, shanking it
         * into a log allows us to inspect independently of the school
         */
//        $watchlist = ['Duty CantInd'];
//
//        if ((new Watchlist())->isOnWatchlist($restructured['code'], $watchlist)) {
//            $this->log->debug(json_encode($currentEvent));
//            $this->log->debug(json_encode($restructured));
//        }

        return $restructured;
    }
}

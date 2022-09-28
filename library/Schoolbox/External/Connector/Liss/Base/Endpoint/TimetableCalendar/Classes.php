<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar;

use PhpXmlRpc\Request;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Liss\Base\Endpoint\Helper;

/**
 * Class Classes
 *
 * Imports liss events into suds timetable, skipping what we can,
 * applying the defaults as specified in config
 *
 * This is intended to be sandwiched between Prepare and Cleanup
 * strats as per suds tradition. We take a $message as input since
 * the school pushes data to us; we don't retrieve data ourselves
 */
class Classes
{
    /**
     * This const corresponds to the enum for the config var external_liss_default_event_type
     * This is used to decide if we use numeric teacher ids, or alphanumeric teacher codes
     * If you're not using teacherId, you're presumed to be using teacherCode instead
     */
    private const TEACHER_IDENTIFIER_ID = 'teacherId';


    /**
     * Import into suds timetable
     *
     * @param LoggerInterface $log
     * @param Request         $message
     * @param string          $defaultType
     * @param array           $exclusions
     * @return void
     * @throws \InvalidArgumentException
     */
    public function import(
        LoggerInterface $log,
        Request $message,
        string $defaultType,
        array $exclusions,
        string $teacherIdentifier
    ): void {
        /* Make all the data easier to manipulate by rearranging their guts
         */
        $helper = new Helper();
        $allDailyData = $helper->reformatAllEvents($message->getParam(3)->me['array']);

        /* Keep track of all the problems we encountered
         */
        $skipCount = [
            'noClassCode' => 0,
            'noType' => 0,
            'invalidType' => 0,
            'unknownUser' => 0,
            'unknownUserIds' => [],
            'total' => 0,
        ];

        /* Let's go, trying to skip as many as we can before we try
         * to persist data to the db
         */
        foreach ($allDailyData as $currentEvent) {
            /* Event doesn't have a type; see if we specified one in config
             * If we did, give it that event type instead, then carry onwards
             */
            if ('' === $currentEvent['type']) {
                /* Skip events that don't have a type, because we didn't define
                 * a default event type in config
                 */
                if ('' === $defaultType) {
                    $log->warning(sprintf(
                        'Skipping "%s" with code "%s" (%s): no type',
                        $currentEvent['name'],
                        $currentEvent['code'],
                        $currentEvent['guid']
                    ));

                    /* Update counts
                     */
                    $skipCount['noType']++;
                    $skipCount['total']++;

                    /* Skip processing this event entirely
                     */
                    continue;
                }

                /* Assign a default one and carry on as if this event
                 * always had this type to start with
                 */
                $currentEvent['type'] = $defaultType;

                $log->debug(sprintf(
                    'Assigning default event type "%s" to "%s" with code "%s" (%s)',
                    $defaultType,
                    $currentEvent['name'],
                    $currentEvent['code'],
                    $currentEvent['guid']
                ));
            }

            /* Skip events that have a particular type
             * This also applies if you exclude the default one you chose in config
             */
            if (\in_array($currentEvent['type'], $exclusions, false)) {
                $log->warning(sprintf(
                    'Skipping "%s" with code "%s" (%s): has excluded type "%s"',
                    $currentEvent['name'],
                    $currentEvent['code'],
                    $currentEvent['guid'],
                    $currentEvent['type']
                ));

                /* Update counts
                 */
                $skipCount['invalidType']++;
                $skipCount['total']++;

                /* Skip processing this event entirely
                 */
                continue;
            }

            /* Skip events that don't have any classCode value
             * This supposedly never happens, but I don't trust
             */
            if ('' === $currentEvent['code']) {
                $log->warning(sprintf(
                    'Skipping "%s" with code "%s" (%s): no class codes provided',
                    $currentEvent['name'],
                    $currentEvent['code'],
                    $currentEvent['guid'],
                ));

                /* Update counts
                 */
                $skipCount['noClassCode']++;
                $skipCount['total']++;

                /* Skip processing this event entirely
                 */
                continue;
            }

            /* TODO Process events here
             */
            $this->processDailyData($currentEvent);
        }

        /* Log diagnostics
         */
        $total = \count($allDailyData);
        $processed = $total - $skipCount['total'];

        $log->info('');
        $log->info('----------------------------------------------');
        $log->info("Total Processed: {$processed}/{$total}");
        $log->info("Total Skipped: {$skipCount['total']}/{$total}");
        $log->info("Skipped (no class code): {$skipCount['noClassCode']}/{$skipCount['total']}");
        $log->info("Skipped (no type): {$skipCount['noType']}/{$skipCount['total']}");
        $log->info("Skipped (invalid type): {$skipCount['invalidType']}/{$skipCount['total']}");
        $log->info("Unknown user assignations: {$skipCount['unknownUser']}");

        /* A list of all the unknown user ids
         */
        $unknownUserIds = array_filter($skipCount['unknownUserIds']);

        if (count($unknownUserIds)) {
            asort($unknownUserIds);

            $unknownUserIds = implode(', ', $unknownUserIds);

            $log->info("Unknown user ids: {$unknownUserIds}");
        }

        $log->info('');
    }


    /**
     * Put this single $currentEvent into the SUDS Timetable
     *
     * @param array $currentEvent
     * @return void
     */
    private function processDailyData(array $currentEvent): void
    {
    }


    /**
     * Decide if we're going to use teacherIds as the externalId for staff
     * or whether we use teacherCodes instead
     *
     * @param string $teacherIdentifier
     * @return bool
     */
    private function isTeacherIds(string $teacherIdentifier): bool
    {
        return self::TEACHER_IDENTIFIER_ID === $teacherIdentifier;
    }
}
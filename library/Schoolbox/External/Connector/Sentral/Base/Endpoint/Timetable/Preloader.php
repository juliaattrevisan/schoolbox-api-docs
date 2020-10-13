<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Timetable;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Api;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Registry;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Class Preloader
 *
 * When we imported Students we remembered their ids so we can fetch their
 * individual timetables here
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint\Timetable
 */
class Preloader implements IStrategy
{
    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private $perform;


    /**
     * @var Api
     */
    private $api;


    /**
     * @var Registry
     */
    private $registry;


    /**
     * Preloader constructor.
     *
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param Api             $api
     * @param Registry        $registry
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Api $api,
        Registry $registry
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->registry = $registry;
    }


    /**
     * For every student we encountered, get their timetable
     * for the current week, Mon-Fri inclusive
     */
    public function import(): void
    {

        /* Use timetable-lessons endpoint with appropriate dates for the Monday of this week
         * to the Friday of this week inclusive
         *
         * May or may not be buggy
         * https://stackoverflow.com/questions/1431631/get-date-for-monday-and-friday-for-the-current-week-php
         */
        $monday = date('Y-m-d', strtotime('monday this week'));
        $friday = date('Y-m-d', strtotime('friday this week'));

        /* Iterate through all the people we saw previously, in profile import
         */
        foreach ($this->registry->recallAll() as $id => $data) {
            $name = trim("{$data['student']['firstname']} {$data['student']['lastname']}");

            $endpoint = "timetables/student/{$id}/timetable-lessons?fromDate={$monday}&toDate={$friday}";

            $this->log->debug(sprintf(
                'Accessing endpoint %s',
                $endpoint
            ));

            /* Some endpoint accesses result in an endpoint saying you didn't provide a thing
             * called a "timeview", i.e. "Missing timeview student information for the given student"
             *
             * This means they don't have any timetable data, but it results in a endpoint error
             * from sentral and an exception being thrown by us. There's no api docs about it,
             * I had to figure it out the hard way
             */
            try {
                $payload = $this->api->retrieveDocument($endpoint);

                $numRecords = \count($payload['data']);

                if ($numRecords) {
                    $this->log->debug(sprintf(
                        'Found %s timetableLesson instances for %s (sentral id %s, externalId %s)',
                        $numRecords,
                        $name,
                        $id,
                        $data['student']['externalId']
                    ));
                }
            } catch(\InvalidArgumentException $e) {

                /* We can't process any timetables in those cases, so log it and continue
                 */
                $this->log->warning(sprintf(
                    'Could not obtain timetable for %s (sentral id %s, externalId %s); they might not have a timetable in sentral.',
                    $name,
                    $id,
                    $data['student']['externalId']
                ));

                $this->log->warning($e->getMessage());

                continue;
            }

            /* Map a flat stack of timetableLesson instances into a hierarchical data struct
             * Then remember (and use) this structure when creating Definition
             *
             * $studentId => [
             *      dayName => [
             *          periodNumber => [
             *              details about the subject in this period for this person
             *          ]
             *      ]
             * ]
             */
            $timetable = [];

            foreach ($payload['data'] as $timetableLesson) {
                $timetable[$timetableLesson['attributes']['dayName']][$timetableLesson['attributes']['periodOrder']] = [
                    'day'      => $timetableLesson['attributes']['dayName'],
                    'name'     => "Period {$timetableLesson['attributes']['periodName']}",
                    'start'    => $timetableLesson['attributes']['startTime'],
                    'finish'   => $timetableLesson['attributes']['endTime'],
                    'sequence' => $timetableLesson['attributes']['periodOrder'],
                    'subject'  => [
                        'name'     => $timetableLesson['attributes']['subject'],
                        'code'     => $timetableLesson['attributes']['className'],
                        'room'     => $timetableLesson['attributes']['roomName'],
                        'teachers' => [
                            'externalId' => null,  // TODO find
                            'name' => $timetableLesson['attributes']['teacherName'],
                            'id'   => $timetableLesson['attributes']['teacherIds'][0] ?? null
                        ]
                    ]
                ];
            }

            /* Remember it in the Registry instance because we left ourselves
             * a nice spot for it to fit
             */
            $this->registry->remember((string)$id, [
                'student' => $data['student'],
                'timetable' => $timetable
            ]);
        }
    }
}

<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Timetable;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Api;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile\Profile;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Registry;

/**
 * Class Preloader
 *
 * When we imported Students we remembered their ids so we can fetch their
 * individual timetables here. Each student timetable needs some more info
 * compiled into it, such as teacher names etc. so we can do it here and
 * save ourselves some hassle elsewhere
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint\Timetable
 */
class Preloader
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
     * A date string in the format Y-m-d e.g. 2020-10-12
     *
     * @var string
     */
    private $fromDate;


    /**
     * A date string in the format Y-m-d e.g. 2020-10-12
     *
     * @var string
     */
    private $toDate;


    /**
     * Preloader constructor.
     *
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param Api             $api
     * @param Registry        $registry
     * @param string          $fromDate
     * @param string          $toDate
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Api $api,
        Registry $registry,
        string $fromDate,
        string $toDate
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->registry = $registry;

        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }


    /**
     * For every student we encountered, get their timetable
     * for the current week, Mon-Fri inclusive
     *
     * @param string $externalId
     * @return array|null
     */
    public function compile(string $externalId): ?array
    {

        /* Use timetable-lessons endpoint with appropriate dates for the Monday of this week
         * to the Friday of this week inclusive
         *
         * May or may not be buggy
         * https://stackoverflow.com/questions/1431631/get-date-for-monday-and-friday-for-the-current-week-php
         */

        /* Iterate through all the people we saw previously, in profile import
         */
        $data = $this->registry->recall($externalId);

        $name = trim("{$data['person']['firstname']} {$data['person']['lastname']}");

        /* Decide which endpoint to consume
         *
         * Students get their timetables from a specific place, but also get teacherIds as part
         * of their return payload; we can infer partial staff timetables using this
         *
         * Staff timetables are also arbitrarily retrieved and superimposed on top of whatever
         * was previously derived from student timetables, to get full non-contradictory data
         * (ie. if you're teaching a subject, it's going to be in your timetable - you can't
         * be teaching a subject and having an empty period at the same time). This superposition
         * should theoretically obtain staff classes that don't involve students...
         */
        if (Profile::TYPE_STUDENT === $data['person']['type']) {
            $endpoint = "timetables/student/{$data['person']['id']}/timetable-lessons?fromDate={$this->fromDate}&toDate={$this->toDate}";
        } elseif (Profile::TYPE_STAFF === $data['person']['type']) {
            $endpoint = "timetables/staff/{$data['person']['id']}/timetable-lessons?fromDate={$this->fromDate}&toDate={$this->toDate}";
        } else {
            $this->log->warning(sprintf(
                'Unknown profile type for %s; skipping timetable preload.',
                $name
            ));

            return null;
        }

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
                    'Found %s timetableLesson instances for %s %s (sentral id %s, externalId %s)',
                    $numRecords,
                    $data['person']['type'],
                    $name,
                    $data['person']['id'],
                    $data['person']['externalId']
                ));
            }
        } catch (\InvalidArgumentException $e) {
            /* We can't process any timetables in those cases, so log it and continue
             */
            $this->log->warning(sprintf(
                'Could not obtain timetable for %s %s (sentral id %s, externalId %s); they might not have a timetable in sentral.',
                $data['person']['type'],
                $name,
                $data['person']['id'],
                $data['person']['externalId']
            ));

            $this->log->warning($e->getMessage());

            return null;
        }

        /* Map a flat stack of timetableLesson instances into a hierarchical data struct
         *
         *      dayName => [
         *          periodNumber => [
         *              period attributes
         *              (details about the subject in this period for this person)
         *          ]
         *      ]
         */
        $timetable = [];

        /* A temporary cache for teacher id endpoints
         * because sentral is so damn slow
         *
         * Trade space for time
         */
        $teacherCache = [];

        foreach ($payload['data'] as $timetableLesson) {
            $teacher = [
                'externalId' => '',
                'firstname' => '',
                'surname' => '',
                'id' => '',
            ];

            /* Go digging in another endpoint for names and identifier
             */
            if (isset($timetableLesson['attributes']['teacherIds'][0]) && '' !== (string)$timetableLesson['attributes']['teacherIds'][0]) {
                $endpoint = "enrolments/person/{$timetableLesson['attributes']['teacherIds'][0]}?limit=200";

                /* Load it from the cache, if we have it;
                 * Otherwise just hit that api
                 */
                if (isset($teacherCache[$endpoint])) {
                    $teacher = $teacherCache[$endpoint];

                    $this->log->debug(sprintf(
                        'Reusing teacher endpoint payload for %s %s (externalId: %s, sentralId: %s)',
                        $teacher['firstname'],
                        $teacher['surname'],
                        $teacher['externalId'],
                        $teacher['id']
                    ));
                } else {
                    try {
                        $teacherPayload = $this->api->retrieveDocument($endpoint);

                        $teacher = [
                            'externalId' => $teacherPayload['data']['attributes']['externalId'],
                            'firstname' => $teacherPayload['data']['attributes']['firstName'],
                            'surname' => $teacherPayload['data']['attributes']['lastName'],
                            'id' => $timetableLesson['attributes']['teacherIds'][0],
                        ];
                    } catch (\InvalidArgumentException $e) {
                        $this->log->warning(sprintf(
                            'Unknown teacher id %s (%s) in sentral; using empty identifier for now.',
                            $timetableLesson['attributes']['teacherIds'][0],
                            $timetableLesson['attributes']['teacherName']
                        ));

                        $teacher = [
                            'externalId' => '',
                            'firstname' => '',
                            'surname' => '',
                            'id' => $timetableLesson['attributes']['teacherIds'][0],
                        ];
                    }

                    /* Remember this for later
                     */
                    $teacherCache[$endpoint] = $teacher;
                }
            } else {
                $this->log->warning(sprintf(
                    'No teachers set for class %s %s; using empty identifier for now.',
                    $timetableLesson['attributes']['subject'],
                    $timetableLesson['attributes']['className']
                ));
            }

            $timetable[$timetableLesson['attributes']['dayName']][$timetableLesson['attributes']['periodOrder']] = [
                'day'      => $timetableLesson['attributes']['dayName'],
                'name'     => $timetableLesson['attributes']['periodName'],
                'start'    => $timetableLesson['attributes']['startTime'],
                'finish'   => $timetableLesson['attributes']['endTime'],
                'sequence' => $timetableLesson['attributes']['periodOrder'],
                'subject'  => [
                    'name'     => $timetableLesson['attributes']['subject'],
                    'code'     => $timetableLesson['attributes']['className'],
                    'room'     => $timetableLesson['attributes']['roomName'],
                    'teachers' => $teacher
                ]
            ];
        }

        return $timetable;
    }
}

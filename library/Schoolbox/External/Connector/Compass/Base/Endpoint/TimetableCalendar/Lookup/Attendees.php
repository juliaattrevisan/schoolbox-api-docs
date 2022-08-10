<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar\Lookup;

use Psr\Log\LoggerInterface;

/**
 * A lookup for Attendees of a non-academic compass class
 *
 * It's the converse of Enrolments which are the guys going to an Academic class
 */
class Attendees
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * @var array
     */
    private array $staffLookup;


    /**
     * @var array
     */
    private array $studentLookup;


    /**
     * Constructor
     *
     * @param LoggerInterface $log
     * @param array           $staffLookup
     * @param array           $studentLookup
     */
    public function __construct(LoggerInterface $log, array $staffLookup, array $studentLookup)
    {
        $this->log = $log;
        $this->staffLookup = $staffLookup;
        $this->studentLookup = $studentLookup;
    }


    /**
     * Generate a list of compassPersonIds and whoever they really are
     *
     * @param array $activity
     * @return array
     */
    public function generate(array $activity): array
    {
        /* Fetch a set of externalIds out of the Attendees stack
         */
        $enrolments = [
            'students' => [],
            'teachers' => [],
            'sessions' => [],
        ];

        /* Attendees List per Instance
         * These are always teachers, I think
         */
        foreach ($activity['instances'] as $activityInstance) {
            foreach ($activityInstance['attendees'] as $attendee) {
                $compassPersonId = $attendee['compassPersonId'];

                /* These person is the main person teaching this activity
                 */
                if (isset($this->staffLookup[$compassPersonId]['externalId'])) {
                    $enrolments['teachers'][] = $this->staffLookup[$compassPersonId]['externalId'];

                    $this->log->debug(sprintf(
                        'Activity %s starting on %s has attendee compassPersonId %s, %s %s',
                        $activity['name'],
                        $activity['start'],
                        $compassPersonId,
                        $this->staffLookup[$compassPersonId]['firstname'],
                        $this->staffLookup[$compassPersonId]['lastname']
                    ));
                } else {
                    $this->log->debug(sprintf(
                        'Activity %s starting on %s has unknown attendee compassPersonId %s',
                        $activity['name'],
                        $activity['start'],
                        $compassPersonId
                    ));
                }
            }
        }

        /* Attendees List per Activity
         *
         * Also find random guys in the attendees list,
         * which is separate to the attendees list *per instance*
         *
         * This is a list of all the guys going on an activity
         * and can be a mix of staff and students
         */
        foreach ($activity['attendees'] as $attendee) {
            $compassPersonId = $attendee['compassPersonId'];

            if (isset($this->staffLookup[$compassPersonId]['externalId'])) {
                /* Staff can attend events
                 */
                $enrolments['teachers'][] = $this->staffLookup[$compassPersonId]['externalId'];

                $this->log->debug(sprintf(
                    'Activity %s starting on %s has attendee compassPersonId %s, %s %s',
                    $activity['name'],
                    $activity['start'],
                    $compassPersonId,
                    $this->staffLookup[$compassPersonId]['firstname'],
                    $this->staffLookup[$compassPersonId]['lastname']
                ));
            } elseif (isset($this->studentLookup[$compassPersonId]['externalId'])) {
                /* Students can attend events
                 */
                $enrolments['students'][] = $this->studentLookup[$compassPersonId]['externalId'];

                $this->log->debug(sprintf(
                    'Activity %s starting on %s has attendee compassPersonId %s, %s %s',
                    $activity['name'],
                    $activity['start'],
                    $compassPersonId,
                    $this->studentLookup[$compassPersonId]['firstname'],
                    $this->studentLookup[$compassPersonId]['lastname']
                ));
            } else {
                $this->log->debug(sprintf(
                    'Activity %s starting on %s has unknown attendee compassPersonId %s',
                    $activity['name'],
                    $activity['start'],
                    $compassPersonId
                ));
            }
        }

        return $enrolments;
    }
}

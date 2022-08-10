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
     * Constructor
     *
     * @param LoggerInterface $log
     * @param array           $staffLookup
     */
    public function __construct(LoggerInterface $log, array $staffLookup)
    {
        $this->log = $log;
        $this->staffLookup = $staffLookup;
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

        foreach ($activity['instances'] as $activityInstance) {
            foreach ($activityInstance['attendees'] as $attendee) {
                $compassPersonId = $attendee['compassPersonId'];

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

        return $enrolments;
    }
}

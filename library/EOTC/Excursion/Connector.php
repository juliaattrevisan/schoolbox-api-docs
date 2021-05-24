<?php

/**
 * Connector
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace EOTC\Excursion;

use Alaress\Database\Connector\IConnector;
use EOTC\Event as EOTCEvent;
use Schoolbox\User;

class Connector
{
    /**
     * One of the values in dbo.luEventStatus
     * This is the "Status" dropdown in Synweb
     */
    private const DEFAULT_STATUS = 'PLANNING';

    /**
     * One of the options in lu.AttendeeType
     * This distinguishes staff from students in Synweb
     */
    private const ATTENDEE_STAFF = 'STAFF';
    private const ATTENDEE_STUDENT = 'ATTENDEE';

    /**
     * Connectors which can synchronise EOTC events to external db
     *
     * @var array
     */
    private $availableConnectors = ['synergetic'];

    /**
     * An instance of MSSQL
     *
     * @var IConnector
     */
    private $db;

    /**
     * A value that exists in dbo.spsEventTypes
     * This is the "Type" dropdown in Synweb
     * @var string
     */
    private $eventType;

    /**
     * Constructor
     *
     * @param string $databaseType
     * @param IConnector $db
     * @param string $eventType
     */
    public function __construct(string $databaseType, IConnector $db, string $eventType)
    {
        /* Only set $db when external db supports what we're doing
         */
        if (\in_array($databaseType, $this->availableConnectors, false)) {
            $this->db = $db;
        }
        $this->eventType = $eventType;
    }


    /**
     * Facade method
     *
     * Ram $event data into synergetic in some way
     *
     * @param EOTCEvent $event
     * @throws \DomainException
     */
    public function sync(EOTCEvent $event): void
    {
        /* If the external db isn't supported, do nothing
         */
        if ($this->db) {
            /* Set up base excursion details
             */
            $eventsSeq = $this->insertExcursion($event);
            $this->updateDetails($event, $eventsSeq);

            /* Attendees
             */
            $this->insertAttendees($event);
        }
    }

    /**
     * Generate an EventCode (a unique guid in synergetic)
     *
     * @param int $eventId
     * @return string
     */
    private function generateIdentifier($eventId): string
    {
        return "EOTC_{$eventId}";
    }

    /**
     * Insert an event into Synergetic
     *
     * @param EOTCEvent $event
     * @return int
     * @throws \DomainException
     */
    private function insertExcursion(EOTCEvent $event): int
    {
        $eventsSeq = null;
        $result = null;

        $inputs = [

            /* This is a unique identifier. If it's not unique in dbo.Events, you'll
             * end up with a foreign key error and your $eventsSeq will be null
             *
             * Generate a unique identifier per event (probably use the primary key
             * of the eotc_event table)
             */
            'EventCode' => $this->generateIdentifier($event->getId()),

            /* EOTC Event description goes here
             */
            'Description' => $event->getDetail(),

            /* A value that exists in dbo.spsEventTypes
             */
            'EventTypeCode' => $this->eventType,

            /* I guess this is a synergetic id?
             */
            'OrganiserID' => $event->getTeacherInCharge()->getExternalId(),
        ];

        $outputs = [
            'EventsSeq' => [
                'value' => &$eventsSeq,
                'type' => \PDO::PARAM_INT,
                'length' => 4
            ]
        ];

        /* $statement->errorCode() and $statement->errorInfo() may reveal hints about
         * why a stored proc might fail. I haven't seen it fail except due to my own
         * ineptitude during development, though I foresee inspection of these fields
         * to diagnose various failures that I haven't encountered yet. Therefore
         * these $statement vars are placeholders for now, while we figure out what
         * kind of failures may occur
         */
        $statement = $this->db->executeStoredProcedure('spiExcursions', $inputs, $outputs);

        if (!is_numeric($eventsSeq)) {
            throw new \DomainException('Could not save Excursion: maybe need a unique EventCode identifier?');
        }

        return (int)$eventsSeq;
    }

    /**
     * Update an excursion with metadata-ish stuff such as description, times, etc.
     *
     * @param EOTCEvent $event
     * @param int $eventsSeq
     */
    private function updateDetails(EOTCEvent $event, $eventsSeq): void
    {
        $inputs = [
            'EventSeq' => $eventsSeq,

            /* Description: The event's title/name (non-html, synweb doesn't support html)
             * Objective: A seemingly arbitrary string, but I'm going to use it for Event Description
             */
            'Description' => $event->getTitle(),
            'Objective' => html_entity_decode(strip_tags($event->getDetail()), ENT_QUOTES),

            /* Event dates
             */
            'EventFrom' => $event->getStartDateTime()->getDatabase(),
            'EventTo' => $event->getEndDateTime()->getDatabase(),
            'InviteDate' => $event->getStartDateTime()->getDatabase(),
            'RSVPDueBy' => $event->getStartDateTime()->getDatabase(),

            /* One of the values in dbo.luAttire
             */
            'Attire' => 'UNIFORM',

            /* A seemingly arbitrary string, but make it one of the
             * dbo.spsEventTypes options
             */
            'Type' => $this->eventType,

            /* One of the values in dbo.luEventStatus
             */
            'Status' => self::DEFAULT_STATUS,

            /* Limits
             */
            'MaxAttendance' => 0,
            'MaxTickets' => 0,

            /* Other
             */
            'Comments' => '',
        ];

        $statement = $this->db->executeStoredProcedure('spuExcursionDetails', $inputs, $outputs);
    }

    /**
     * Insert attendees
     *
     * @param EOTCEvent $event
     */
    public function insertAttendees(EOTCEvent $event)
    {
        foreach ($event->getMetadata()->getStaffAttendees() as $currentAttendee) {
            $this->insertAttendee($event, $currentAttendee, self::ATTENDEE_STAFF);
        }

        foreach ($event->getMetadata()->getStudentAttendees() as $currentAttendee) {
            $this->insertAttendee($event, $currentAttendee, self::ATTENDEE_STUDENT);
        }
    }

    /**
     * Insert an attendee of $event
     *
     * @param EOTCEvent $event
     * @param User $currentAttendee
     * @param string $attendeeType
     */
    private function insertAttendee(EOTCEvent $event, User $currentAttendee, $attendeeType): void
    {
        $newEventCurrentSeq = null;
        $message = null;

        $inputs = [
            'ID' => (int)$currentAttendee->getExternalId(),
            'EventCode' => $this->generateIdentifier($event->getId()),

            /* Provides dialog that asks you to confirm uniqueness of attendees
             * but changing this option does nothing to the outcome
             */
            'VerifyOnlyFlag' => 0,

            /* One of the options in lu.AttendeeType
             */
            'AttendeeType' => $attendeeType
        ];

        $outputs = [
            'NewEventCurrentSeq' => [
                'value' => &$newEventCurrentSeq,
                'type' => \PDO::PARAM_INT,
                'length' => 8
            ],
            'Message' => [
                'value' => &$message,
                'type' => \PDO::PARAM_STR,
                'length' => 200
            ]
        ];

        $statement = $this->db->executeStoredProcedure('spiCommunityEventsCurrent', $inputs, $outputs);
    }

//    /**
//     * Blow away an Excursion
//     * Use this because the Synweb ui is wrist-slashingly bad
//     *
//     * This isn't intended for production, but to lubricate one's journey
//     * through the horrors of Synweb's excursion deletion process during dev
//     * It's easier to use this stored proc implementation than the ui.
//     *
//     * Preserved for the duration of development in EOTC
//     * TODO: Remove upon completion
//     *
//     * @param array $eventIds an array of eotc_event primary key ints that correspond to stuff in synergetic
//     */
//    private function purge($eventIds)
//    {
//        $message = null;
//
//        foreach ($eventIds as $currentEventId) {
//            $inputs = [
//                'EventCode' => $this->generateIdentifier($currentEventId),
//
//                /* Provides dialog that asks you to confirm uniqueness of attendees
//                 * but changing this option does nothing to the outcome
//                 */
//                'VerifyOnlyFlag' => 0,
//
//                /* One of the options in lu.AttendeeType
//                 */
//                'Type' => self::DEFAULT_EVENT_TYPE_CODE,
//            ];
//
//            $outputs = [
//                'Message' => [
//                    'value' => &$message,
//                    'type' => \PDO::PARAM_STR,
//                    'length' => 200
//                ]
//            ];
//
//            $statement = $this->getDb()->executeStoredProcedure('spdEvents', $inputs, $outputs);
//        }
//    }
}

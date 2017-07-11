<?php

/**
 * Connector
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace EOTC\Excursion;
use Alaress\Database\Connector\IConnector;
use EOTC\Event as EOTCEvent;
use Schoolbox\User;

class Connector
{
    /**
     * A value that exists in dbo.spsEventTypes
     * This is the "Type" dropdown in Synweb
     */
    const DEFAULT_EVENT_TYPE_CODE = 'TESTTYPE';

    /**
     * One of the values in dbo.luEventStatus
     * This is the "Status" dropdown in Synweb
     */
    const DEFAULT_STATUS = 'PLANNING';

    /**
     * One of the options in lu.AttendeeType
     * This distinguishes staff from students in Synweb
     */
    const ATTENDEE_STAFF = 'STAFF';
    const ATTENDEE_STUDENT = 'ATTENDEE';

    /**
     * An instance of MSSQL
     *
     * @var IConnector
     */
    private $db;

    /**
     * Constructor
     *
     * @param IConnector $db
     */
    public function __construct(IConnector $db)
    {
        $this->setDb($db);
    }

    /**
     * Facade method
     *
     * Ram $event data into synergetic in some way
     *
     * @param EOTCEvent $event
     */
    public function sync(EOTCEvent $event)
    {
        /* Set up base excursion details
         */
        $eventsSeq = $this->insertExcursion($event);
        $this->updateDetails($event, $eventsSeq);

        /* Attendees
         */
        $this->insertAttendees($event);
    }

    /**
     * Generate an EventCode (a unique guid in synergetic)
     *
     * @param int $eventId
     * @return string
     */
    private function generateIdentifier($eventId)
    {
        return "EOTC_SYNC_{$eventId}";
    }

    /**
     * Insert an event into Synergetic
     *
     * @param EOTCEvent $event
     * @return int
     * @throws \DomainException
     */
    private function insertExcursion(EOTCEvent $event)
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
            'EventTypeCode' => self::DEFAULT_EVENT_TYPE_CODE,

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
        $statement = $this->getDb()->executeStoredProcedure('spiExcursions', $inputs, $outputs);

        if (!is_numeric($eventsSeq)) {
            throw new \DomainException('Could not save Excursion: maybe need a unique EventCode identifier?');
        }

        return $eventsSeq;
    }

    /**
     * Update an excursion with metadata-ish stuff such as description, times, etc.
     *
     * @param EOTCEvent $event
     * @param int $eventsSeq
     */
    private function updateDetails(EOTCEvent $event, $eventsSeq)
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
            'Type' => self::DEFAULT_EVENT_TYPE_CODE,

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

        $statement = $this->getDb()->executeStoredProcedure('spuExcursionDetails', $inputs, $outputs);
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
    private function insertAttendee(EOTCEvent $event, User $currentAttendee, $attendeeType)
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

        $statement = $this->getDb()->executeStoredProcedure('spiCommunityEventsCurrent', $inputs, $outputs);
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
//     * @param $eventIds
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

    /* Getters/Setters
     */

    /**
     * @return IConnector
     */
    private function getDb()
    {
        return $this->db;
    }

    /**
     * @param IConnector $db
     * @return $this
     */
    private function setDb(IConnector $db)
    {
        $this->db = $db;
        return $this;
    }
}

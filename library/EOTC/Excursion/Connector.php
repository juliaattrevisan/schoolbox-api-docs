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

class Connector
{

    /**
     * An instance of MSSQL
     *
     * @var IConnector
     */
    private $db;

    /**
     * Constructor
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
     * @param $eventId
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
        new \Alaress_Probe('<h1>Excursion Insertion</h1>');

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
            'EventTypeCode' => 'TESTTYPE',

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

        $statement = $this->getDb()->executeStoredProcedure('spiExcursions', $inputs, $outputs);

        if (!is_numeric($eventsSeq)) {
            throw new \DomainException('Could not save Excursion: maybe need a unique EventCode identifier?');
        }

        new \Alaress_Probe($inputs, $outputs, $eventsSeq);

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
        new \Alaress_Probe('<h1>Excursion Update</h1>');

        $inputs = [
            'EventSeq' => $eventsSeq,

            /* Description: The event's title/name
             * Objective: A seemingly arbitrary string, but I'm going to use it for Event Description
             */
            'Description' => $event['title'],
            'Objective' => $event['description'],

            /* Event dates
             */
            'EventFrom' => $event['fromDatetime'],
            'EventTo' => $event['toDatetime'],
            'InviteDate' => $event['fromDatetime'],
            'RSVPDueBy' => $event['fromDatetime'],

            /* One of the values in dbo.luAttire
             */
            'Attire' => 'UNIFORM',

            /* A seemingly arbitrary string, but make it one of the
             * dbo.spsEventTypes options
             */
            'Type' => $event['eventTypeCode'],

            /* One of the values in dbo.luEventStatus
             */
            'Status' => $event['status'],

            /* Limits
             */
            'MaxAttendance' => 0,
            'MaxTickets' => 0,

            /* Other
             */
            'Comments' => '',
        ];
        $statement = $this->getDb()->executeStoredProcedure('spuExcursionDetails', $inputs, $outputs);
        $statement->nextRowset();

        new \Alaress_Probe($inputs, $outputs, $eventsSeq);
    }

    /**
     * Insert attendees
     *
     * @param EOTCEvent $event
     */
    public function insertAttendees(EOTCEvent $event)
    {
        new \Alaress_Probe('<h1>Attendees</h1>');

        foreach ($event['attendees'] as $currentAttendee) {
            $newEventCurrentSeq = null;
            $message = null;

            $inputs = [
                'ID' => (int)$currentAttendee['id'],
                'EventCode' => $this->generateIdentifier($event['id']),

                /* Provides dialog that asks you to confirm uniqueness of attendees
                 * but changing this option does nothing to the outcome
                 */
                'VerifyOnlyFlag' => 0,

                /* One of the options in lu.AttendeeType
                 */
                'AttendeeType' => $currentAttendee['attendeeType']
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

            new \Alaress_Probe($currentAttendee, $inputs, $outputs, $newEventCurrentSeq, $message);
        }
    }

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
    private function setDb($db)
    {
        $this->db = $db;
        return $this;
    }
}

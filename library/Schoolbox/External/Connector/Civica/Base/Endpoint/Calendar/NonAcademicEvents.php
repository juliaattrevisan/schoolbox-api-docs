<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint\Calendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Civica\Base\Endpoint\IApi;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Event\Model;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\Mapper\Exception;

/**
 * Import all the non-academic events, saving them as sis_event records
 * with the 'event' classification
 */
class NonAcademicEvents implements IStrategy
{
    /**
     * Constructor.
     *
     * @param LoggerInterface $log
     * @param bool $perform
     * @param IApi $api
     * @param Mapper $eventMapper
     * @param \Schoolbox\External\Event\User\Mapper $eventUserMapper
     * @param \Alaress_Date_Format $fromDate
     * @param \Alaress_Date_Format $toDate
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private IApi $api,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private \Schoolbox\External\Event\User\Mapper $eventUserMapper,
        private \Alaress_Date_Format $fromDate,
        private \Alaress_Date_Format $toDate
    ) {
    }


    /**
     * @return void
     * @throws Exception
     * @throws \JsonException
     */
    public function import(): void
    {
        $pages = $this->api->retrieveForJurisdiction('GET', 'EventGroups/Jurisdiction');

        foreach ($pages as $page) {
            foreach ($page['values'] as $data) {
                $this->processEvent($data);
            }
        }
    }


    /**
     * For each event, process the event data and apply student/staff involvements
     * Ignore events that occur outside the [$from, $to] window
     *
     * @param array $data
     * @return void
     * @throws Exception
     */
    private function processEvent(array $data): void
    {
        /* Save sis_event record
         */
        $event = $this->createEvent($data);

        /* An event might be skipped based on the data evaluated inside createEvent()
         * where it already throws log messages at you
         */
        if (null === $event) {
            return;
        }

        /* Save the involvements
         */
        $this->createInvolvements($data, $event);
    }


    /**
     * Create a new School Data Event with all the event and attendee "involvements"
     *
     * @param array $data
     * @return Model|null
     * @throws Exception
     */
    private function createEvent(array $data): ?\Schoolbox\External\Event\Model
    {
        $title = trim((string)$data['eventName']);

        /* Fill in these vars as usual
         */
        $classCode = trim((string)$data['subjectShortName']);
        $day = null;
        $detail = trim((string)$data['description']);
        $period = null;
        $location = trim((string)$data['venue']);
        $allDay = (bool)$data['wholeDay'];

        /* Arbitrarily concoct a superkey to represent this activity instance
         */
        $guid = trim((string)$data['eventOccurrenceId']);

        /* Datetimes for when this event occurs
         * All day events don't have times.
         */
        if ($allDay) {
            $startVal = sprintf(
                '%s 00:00:00',
                $data['eventDate']
            );

            $endVal = sprintf(
                '%s 23:59:59',
                $data['eventDate']
            );
        } else {
            $startVal = sprintf(
                '%s %s',
                $data['eventDate'],
                sprintf(
                    '%s:00',
                    $data['startTime'],
                )
            );

            $endVal = sprintf(
                '%s %s',
                $data['eventEndDate'],
                sprintf(
                    '%s:00',
                    $data['endTime'],
                )
            );
        }

        $startAt = new \Alaress_Date_Format($startVal, \Alaress_Date_Format::DATABASE_FORMAT);
        $finishAt = new \Alaress_Date_Format($endVal, \Alaress_Date_Format::DATABASE_FORMAT);

        /* Check that the event starts in the time window
         */
        if (!$startAt->between($this->fromDate, $this->toDate)) {
            $this->log->debug(sprintf(
                'This event is outside the time window %s to %s - skipping.',
                $this->fromDate->getDatabase(),
                $this->toDate->getDatabase()
            ));

            return null;
        }

        /* This is pretty optional - this is the sis' equivalent of our luEventType
         * Sometimes it's provided, sometimes it's not - we can record it here if
         * possible. If you can't get it, dw about it
         *
         *  Might be either of classification, eventCategoryName, eventClosureCategoryName
         *  or its description counterparts.
         */
        $type = trim((string)$data['eventCategoryName']);

        /* Try to recycle based on guid
         */
        try {
            $event = $this->eventMapper->findOneByColumn('guid', $guid);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $event = $this->eventMapper->createModel();
        }

        /* Cram in everything else
         */
        $event
            ->setTitle($title)
            ->setDetail($detail)
            ->setLocation($location)
            ->setCode($classCode)
            ->setDay($day)
            ->setPeriod($period)
            ->setStartAt($startAt)
            ->setFinishAt($finishAt)
            ->setAllday($allDay)
            ->setType($type)
            ->setClassification(\Schoolbox\External\Event\Model::CLASSIFICATION_EVENT)
            ->setGuid($guid)
            ->setSource(\Schoolbox\External\Connector\Civica\Base\Endpoint\Factory::SOURCE_NAME);

        /* Save it and remember what we saw in case we need to reuse this later
         */
        $this->log->debug(sprintf(
            'Creating new event %s (%s) on day %s, period %s, %s-%s',
            $event->getTitle(),
            $event->getCode(),
            $event->getDay(),
            $event->getPeriod(),
            $event->getStartAt()->getDatabase(),
            $event->getFinishAt()->getDatabase()
        ));

        if ($this->perform) {
            $this->eventMapper->save($event);
        }

        return $event;
    }


    /**
     * Save all the involvements for a given $event
     *
     * @param array $data
     * @param Model $event
     * @return void
     * @throws Exception
     */
    private function createInvolvements(
        array $data,
        \Schoolbox\External\Event\Model $event
    ): void {
        $title = $event->getTitle();

        /* Alternatively, this might also have additional participating teachers
         * Everyone in it is going
         */
        if (!\count($data['staff'])) {
            $this->log->debug(sprintf(
                '%s: no additional teachers involved',
                $title
            ));
        } else {
            foreach ($data['staff'] as $staff) {
                $this->saveStaffInvolvement($event, [
                    'externalId' => (trim((string)$staff['personId']))
                ]);
            }
        }

        /* Save sis_event_user involvements
         * This is the student(s) and they're associated with all instances
         */
        if (!\count($data['students'])) {
            $this->log->debug(sprintf(
                '%s: no students involved',
                $title
            ));
        } else {
            foreach ($data['students'] as $student) {
                $this->saveStudentInvolvement($event, [
                    'externalId' => trim((string)$student['personId'])
                ]);
            }
        }
    }


    /**
     * Creates a relationship between a user and an event instance,
     * akin to "Personal" events just like in  the main calendar
     *
     * @param Model $event
     * @param array $teacher
     * @return void
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    private function saveStaffInvolvement(
        \Schoolbox\External\Event\Model $event,
        array $teacher
    ): void {
        try {
            $this->eventUserMapper->findOneByColumnList([
                'event_id' => $event->getId(),
                'external_id' => $teacher['externalId']
            ]);

            /* Relationship already exists; don't need to add another.
             */
            return;
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop: create new relationship
        }

        $involvement = $this->eventUserMapper->createModel()
            ->setEventId($event->getId())
            ->setExternalId($teacher['externalId']);

        $this->log->debug(sprintf(
            'Creating new event involvement for %s in %s - %s, %s, %s-%s',
            $teacher['externalId'],
            $event->getTitle(),
            $event->getDay(),
            $event->getPeriod(),
            $event->getStartAt()->getDatabase(),
            $event->getFinishAt()->getDatabase()
        ));

        if ($this->perform) {
            $this->eventUserMapper->save($involvement);
        }
    }


    /**
     * Save an involvement for user $externalId, whose name is $name,
     * for the sis_event $event
     *
     * @param Model $event
     * @param array $student
     * @return void
     * @throws Exception
     */
    private function saveStudentInvolvement(
        \Schoolbox\External\Event\Model $event,
        array $student
    ): void {
        try {
            $this->eventUserMapper->findOneByColumnList([
                'event_id' => $event->getId(),
                'external_id' => $student['externalId']
            ]);

            /* Relationship already exists; don't need to add another.
             */
            return;
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop: create new relationship
        }

        $involvement = $this->eventUserMapper->createModel()
            ->setEventId($event->getId())
            ->setExternalId($student['externalId']);

        $this->log->debug(sprintf(
            'Creating new event involvement for %s in %s - %s-%s',
            $student['externalId'],
            $event->getTitle(),
            $event->getStartAt()->getDatabase(),
            $event->getFinishAt()->getDatabase()
        ));

        if ($this->perform) {
            $this->eventUserMapper->save($involvement);
        }
    }
}

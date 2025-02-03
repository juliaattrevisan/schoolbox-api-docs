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
 * with the 'timetable' classification
 */
class NonAcademicEvents implements IStrategy
{
    /**
     * Constructor.
     *
     * @param LoggerInterface                       $log
     * @param bool                                  $perform
     * @param IApi                                  $api
     * @param Mapper                                $eventMapper
     * @param \Schoolbox\External\Event\User\Mapper $eventUserMapper
     * @param string                                $fromDate
     * @param string                                $toDate
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private IApi $api,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private \Schoolbox\External\Event\User\Mapper $eventUserMapper,
        private string $fromDate,
        private string $toDate
    ) {
    }


    /**
     * @return void
     * @throws \JsonException
     */
    public function import(): void
    {
        $pages = $this->api->retrieveForJurisdiction('GET', 'EventGroups/Jurisdiction');

        foreach ($pages as $page) {
            print_r($page);
        }
    }


    /**
     * For each class, process the lessons and apply student/staff involvements
     * Ignore lessons that occur outside the [$from, $to] window
     *
     * @param array $activity
     * @param array $activityInstance
     * @param array $enrolments
     * @param array $staffLookup
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function processClass(
        array $activity,
        array $activityInstance,
        array $enrolments,
        array $staffLookup
    ): void {
        /* Save sis_event record
         */
        $event = $this->createEvent($activity, $activityInstance);

        /* Save the involvements
         */
        $this->createInvolvements($activity, $activityInstance, $enrolments, $staffLookup, $event);
    }


    /**
     * Create a new SUDS Timetable Event with all the class and lesson pieces
     *
     * @param array $activity
     * @param array $activityInstance
     * @return \Schoolbox\External\Event\Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function createEvent(
        array $activity,
        array $activityInstance
    ): \Schoolbox\External\Event\Model {
        /* Name is the same as the Code, in compass
         * TODO Fill in when we get access
         */
        $activityName = '';

        /* Fill in these vars as usual
         * TODO Fill in when we get access
         */
        $classCode = '';
        $day = null;
        $detail = null;

        /* Arbitrarily concoct a superkey to represent this activity instance
         * TODO Fill in when we get access
         */
        $guid = '';

        $period = null;
        $room = '';
        $title = '';

        /* Datetimes for when this class occurs
         * TODO Fill in when we get access
         */
        $startAt = \Alaress_Date_Format::now();
        $finishAt = \Alaress_Date_Format::now();

        /* This is pretty optional - this is the sis' equivalent of our luEventType
         * Sometimes it's provided, sometimes it's not - we can record it here if
         * possible. If you can't get it, dw about it
         */
        $type = '';

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
            ->setLocation($room)
            ->setCode($classCode)
            ->setDay($day)
            ->setPeriod($period)
            ->setStartAt($startAt)
            ->setFinishAt($finishAt)
            ->setType($type)
            ->setClassification(\Schoolbox\External\Event\Model::CLASSIFICATION_EVENT)
            ->setGuid($guid)
            ->setSource(\Schoolbox\External\Connector\Compass\Base\Endpoint\Factory::SOURCE_NAME);

        /* Save it and remember what we saw in case we need to reuse this later
         */
        $this->log->debug(sprintf(
            'Creating new timetable event %s (%s) on day %s, period %s, %s-%s',
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
     * Save all the involvements for a given $class
     *
     * @param array                           $activity
     * @param array                           $activityInstance
     * @param array                           $enrolments
     * @param array                           $staffLookup
     * @param \Schoolbox\External\Event\Model $event
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function createInvolvements(
        array $activity,
        array $activityInstance,
        array $enrolments,
        array $staffLookup,
        \Schoolbox\External\Event\Model $event
    ): void {
        /* Name is the same as the Code, in compass
         */
        $activityName = trim((string)$activity['name']);

        /* Alternatively, this might also have additional participating teachers
         * Everyone in it is going
         */
        if (!\count($enrolments['teachers'])) {
            $this->log->debug(sprintf(
                '%s: no additional teachers involved',
                $activityName
            ));
        } else {
            foreach ($enrolments['teachers'] as $externalId) {
                $this->saveStaffInvolvement($event, [
                    'externalId' => $externalId
                ]);
            }
        }

        /* Save sis_class_user involvements
         * This is the student(s) and they're associated with all instances
         */
        if (!\count($enrolments['students'])) {
            $this->log->debug(sprintf(
                '%s: no students involved',
                $activityName
            ));
        } else {
            foreach ($enrolments['students'] as $externalId) {
                $this->saveStudentInvolvement($event, $externalId);
            }
        }
    }


    /**
     * Save staff involvement as an override, which allows each class
     * to have different teachers depending on their associated sis_event
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
        /* We use the Overrides system to specify a particular person's involvement
         * in a particular sis_event instance, rather than being associated with all
         */
        $involvement = $this->eventUserMapper->createModel()
            ->setEventId($event->getId())
            ->setExternalId($teacher['externalId']);

        $this->log->debug(sprintf(
            'Creating new override for %s in timetable event %s - %s, %s, %s-%s',
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
     * @param \Schoolbox\External\Event\Model $event
     * @param string                          $externalId
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function saveStudentInvolvement(
        \Schoolbox\External\Event\Model $event,
        string $externalId
    ): void {
        /* We use the Overrides system to specify a particular person's involvement
         * in a particular sis_event instance, rather than being associated with all
         */
        $involvement = $this->eventUserMapper->createModel()
            ->setEventId($event->getId())
            ->setExternalId($externalId);

        $this->log->debug(sprintf(
            'Creating new override for %s in timetable event %s - %s-%s',
            $externalId,
            $event->getTitle(),
            $event->getStartAt()->getDatabase(),
            $event->getFinishAt()->getDatabase()
        ));

        if ($this->perform) {
            $this->eventUserMapper->save($involvement);
        }
    }
}

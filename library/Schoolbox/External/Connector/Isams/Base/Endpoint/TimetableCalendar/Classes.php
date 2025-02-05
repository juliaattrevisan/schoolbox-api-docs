<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Connector\ClassCode;
use Schoolbox\External\Connector\Watchlist;
use Schoolbox\External\Event\Model;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\Mapper\Exception;

/**
 * Convert isams cyclic grid into schedule format and shank in
 * all the humans involved over the given time interval
 */
class Classes implements IStrategy
{
    /**
     * A list of classEntries that we saw previously
     *
     * @var array
     */
    private array $classEntries = [];


    /**
     * A list of classInvolvements that we saw previously
     *
     * @var array
     */
    private array $classInvolvements = [];


    /**
     * Constructor.
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private array $api,
        private array $watchlist,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
        private ClassEntryMapper $classEntryMapper,
        private ClassInvolvementMapper $classInvolvementMapper
    ) {
    }


    /**
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        /* Unroll the cyclic grid into a flat calendar format
         * (this part is pretty quick)
         */
        $timetableDefinitions = (new CycleUnroller($this->api))->unroll();

        /* Used to shank info about classes and their humans iteratively
         * because we don't need to load *everything* at the start, just
         * as we go
         */
        $scheduleCompiler = new ScheduleCompiler($this->log, $this->api, $timetableDefinitions);

        /* Shank information about the periods occurring during $timetableDefinition
         */
        $schedules = $scheduleCompiler->findSchedules();

        /* Proceed through each week, day and period;
         * obtain more detailed info as you iteratively proceed
         * (this part so slow we do it separately)
         */
        foreach ($timetableDefinitions as $divisionId => $division) {
            foreach ($division as $weekId => $weekData) {
                foreach ($weekData['days'] as $dayData) {
                    foreach ($dayData['periods'] as $periodId => $periodData) {
                        /* guid prefix is serialized with these pieces:
                         *     isams week id
                         *     isams day id
                         *     isams period id
                         *
                         * Do not use isams division id as part of this tuple as that will
                         * guarantee dupe events spawning. We only use the division id to
                         * identify *which students* are associated with a class code that's
                         * shared between Divisions.
                         */
                        $guidPrefix = sprintf(
                            '%s.%s.%s',
                            $weekId,
                            $dayData['isamsId'],
                            $periodId
                        );

                        /* Some periods don't have anything on; that arg is set
                         * to null in those circumstances
                         */
                        $this->processClass($scheduleCompiler, $guidPrefix, (string)$divisionId, $dayData, $periodData, $schedules[$periodId] ?? null);
                    }
                }
            }
        }
    }


    /**
     * Process all the classes that occur on period $periodId
     *
     * @param ScheduleCompiler $scheduleCompiler
     * @param string $guidPrefix
     * @param string $divisionId
     * @param array $dayData
     * @param array $periodData
     * @param array|null $classes
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function processClass(
        ScheduleCompiler $scheduleCompiler,
        string $guidPrefix,
        string $divisionId,
        array $dayData,
        array $periodData,
        ?array $classes
    ): void {
        /* Nothing happens on this period; exit early
         */
        if (null === $classes) {
            return;
        }

        /* Process detailed data procedurally, saving on a mega-preload
         * of the timetable struct in its most detailed format
         *
         * * create classEntry
         * * create regular staff involvement
         * * create overrides for subby teachers
         * * create regular student involvements
         * * shank it into suds town
         */
        foreach ($classes['schedule'] as $scheduleId => $classData) {
            $subject = $scheduleCompiler->findSubject($classData['code'], $divisionId);

            /* Only import data for externalIds in $watchlist, if provided
             */
            if (!(new Watchlist())->isAnyOnWatchlist($this->findInvolvedExternalIds($subject, $classData), $this->watchlist)) {
                continue;
            }

            /* Save sis_event record
             */
            $event = $this->createEvent($guidPrefix, $divisionId, $dayData, $periodData, $classData, $subject);

            /* Save sis_class record
             */
            $classEntry = $this->createClassEntry($subject, $event);

            /* Save the involvements
             */
            $this->createInvolvements($dayData, $periodData, $classData, $subject, $event, $classEntry);
        }
    }


    /**
     * Find out who's in this class for Watchlist support
     * In iSAMS we build a timetable and find out who's in it,
     * rather than pick a guy and build their timetable
     */
    private function findInvolvedExternalIds(array $subject, array $classData): array
    {
        $substituteStaff = [];

        if ($classData['substituteStaff']['externalId'] ?? false) {
            $substituteStaff = [$classData['substituteStaff']['externalId']];
        }

        return array_merge(
            array_column($subject['regularStaff'], 'externalId'),
            array_column($subject['students'], 'externalId'),
            $substituteStaff
        );
    }


    /**
     * Create a new SUDS Timetable Event with all the class and lesson pieces
     *
     * @param string $guidPrefix
     * @param string $divisionId
     * @param array $dayData
     * @param array $periodData
     * @param array $classData
     * @param array $subject
     * @return Model
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createEvent(
        string $guidPrefix,
        string $divisionId,
        array $dayData,
        array $periodData,
        array $classData,
        array $subject
    ): \Schoolbox\External\Event\Model {
        /* Fill in these vars as usual
         */
        $classCode = (new ClassCode($this->log))->generate(trim((string)$classData['code']));

        /* This seems to always be an int, so prefix it wtih a hardcoded "Day"
         */
        $day = 'Day ' . trim((string)$dayData['dayNumber']);

        /* No descriptions available.
         */
        $detail = null;

        /* Arbitrarily concoct a superkey to represent this instance
         *
         * Overall guid is serialized with these pieces:
         *     isams timetable id
         *     isams day id
         *     isams period id
         *     isams subject id
         *     isams class code
         */
        $guid = sprintf(
            '%s.%s.%s',
            $guidPrefix,
            $subject['subjectId'],
            $classCode,
        );

        $period = trim((string)$periodData['name']);
        $room = trim((string)$classData['room']['name']);
        $title = $subject['name'];

        /* Datetimes for when this class occurs
         */
        $startAt = \Alaress_Date_Format::newFromTimestamp(strtotime($periodData['startAt']));
        $finishAt = \Alaress_Date_Format::newFromTimestamp(strtotime($periodData['finishAt']));

        /* This is pretty optional - this is the sis' equivalent of our luEventType
         * Sometimes it's provided, sometimes it's not - we can record it here if
         * possible. If you can't get it, dw about it
         */
        $type = '';

        /* Try to recycle based on guid
         */
        try {
            $event = $this->eventMapper->findOneByColumn('guid', $guid);
            $logPrefix = 'Reusing existing';
        } catch (\Schoolbox\Mapper\Exception $e) {
            $event = $this->eventMapper->createModel();
            $logPrefix = 'Creating new';
        }

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
            ->setClassification(\Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE)
            ->setGuid($guid)
            ->setSource(\Schoolbox\External\Connector\Isams\Base\Endpoint\Factory::SOURCE_NAME);

        /* Save it and remember what we saw in case we need to reuse this later
         */
        $this->log->info(sprintf(
            '%s timetable event %s (%s) in division %s, on day %s, period %s, %s-%s',
            $logPrefix,
            $event->getTitle(),
            $event->getCode(),
            $divisionId,
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
     * Create (or reuse) a ClassEntry corresponding to $event
     *
     * @param array $subject
     * @param Model $event
     * @return ClassEntry
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createClassEntry(array $subject, \Schoolbox\External\Event\Model $event): ClassEntry
    {
        /* The name is the same as the code in isams for some reason
         * I presume the school knows what they mean
         */
        $name = $event->getTitle();
        $code = $event->getCode();

        /* Constraints in the schema are case insensitive, unlike
         * these array keys - class codes sometimes appear like
         * "Speech Therapy" and "Speech therapy" so inserting them
         * twice will cause it to pack a sad. The classEntries array
         * treats these as unique whereas the db didn't
         */
        $existingKey = strtolower($event->getCode());

        if (isset($this->classEntries[$existingKey])) {
            $classEntry = $this->classEntries[$existingKey];
        } else {
            $classEntry = $this->classEntryMapper->createModel()
                ->setName($name)
                ->setCode($code);

            if ($this->perform) {
                $this->classEntryMapper->save($classEntry);
            }

            $this->classEntries[strtolower($event->getCode())] = $classEntry;
        }

        return $classEntry;
    }


    /**
     * Save all the involvements for a given $class
     *
     * @param array      $dayData
     * @param array      $periodData
     * @param array      $classData
     * @param array      $subject
     * @param Model      $event
     * @param ClassEntry $classEntry
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createInvolvements(
        array $dayData,
        array $periodData,
        array $classData,
        array $subject,
        \Schoolbox\External\Event\Model $event,
        ClassEntry $classEntry
    ): void {
        /* Save whoever's teaching this thing on this particular occasion
         */
        $staff = $classData['substituteStaff'];

        $this->log->debug(sprintf(
            'Creating new Override record for subject %s (%s) on %s %s at %s for %s',
            $subject['name'],
            $subject['code'],
            $dayData['dayNumber'],
            $periodData['name'],
            $periodData['startAt'],
            $staff['externalId'],
        ));

        $this->saveStaffInvolvement($event, $staff['externalId']);

        /* Also save a staff member's regular enrolment
         */
        foreach ($subject['regularStaff'] as $regularStaff) {
            $this->log->debug(sprintf(
                'Creating new regular teaching enrolment record for subject %s (%s) on %s %s at %s for %s',
                $subject['name'],
                $subject['code'],
                $dayData['dayNumber'],
                $periodData['name'],
                $periodData['startAt'],
                $regularStaff['externalId'],
            ));

            $this->saveRegularInvolvement($event, $classEntry, $regularStaff['externalId']);
        }

        /* Save sis_class_user involvements
         * This is the student(s) and they're associated with all instances
         */
        foreach ($subject['students'] as $student) {
            $this->saveRegularInvolvement($event, $classEntry, $student['externalId']);
        }
    }


    /**
     * Save staff involvement as an override, which allows each class
     * to have different teachers depending on their associated sis_event
     *
     * @param Model      $event
     * @param string     $externalId
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function saveStaffInvolvement(
        \Schoolbox\External\Event\Model $event,
        string $externalId
    ): void {
        /* We use the Overrides system to specify a particular person's involvement
         * in a particular sis_event instance, rather than being associated with all
         */
        $this->log->debug(sprintf(
            'Creating new override involvements for %s in timetable event %s - %s, %s, %s-%s',
            $externalId,
            $event->getTitle(),
            $event->getDay(),
            $event->getPeriod(),
            $event->getStartAt()->getDatabase(),
            $event->getFinishAt()->getDatabase()
        ));

        /* For specific teaching, execute CLEAR_STAFF to disregard the usual guy
         * and add the guy teaching this particular instance (even though they
         * might end up being the same person sometimes)
         */
        $clearOverride = $this->overridesMapper->createModel()
            ->setEventId($event->getId())
            ->setExternalId(null)
            ->setAction(\Schoolbox\External\Event\Overrides\Model::CLEAR_STAFF)
            ->setSequence(1);

        $involvementOverride = $this->overridesMapper->createModel()
            ->setEventId($event->getId())
            ->setExternalId($externalId)
            ->setAction(\Schoolbox\External\Event\Overrides\Model::ADD)
            ->setSequence(2);

        if ($this->perform) {
            $this->overridesMapper->save($clearOverride);
            $this->overridesMapper->save($involvementOverride);
        }
    }


    /**
     * Save an involvement for user $externalId, whose name is $name,
     * for the sis_event $event
     *
     * @param Model      $event
     * @param ClassEntry $classEntry
     * @param string     $externalId
     * @return void
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    private function saveRegularInvolvement(
        \Schoolbox\External\Event\Model $event,
        ClassEntry $classEntry,
        string $externalId
    ): void {
        if (isset($this->classInvolvements[$classEntry->getId()][$externalId])) {
            $this->log->debug(sprintf(
                'Reusing previous regular class involvement for %s in timetable event %s - %s, %s, %s-%s',
                $externalId,
                $event->getTitle(),
                $event->getDay(),
                $event->getPeriod(),
                $event->getStartAt()->getDatabase(),
                $event->getFinishAt()->getDatabase()
            ));
        } else {
            $classInvolvement = $this->classInvolvementMapper->createModel()
                ->setClass($classEntry)
                ->setExternalId($externalId);

            if ($this->perform) {
                $this->classInvolvementMapper->save($classInvolvement);
            }

            $this->classInvolvements[$classEntry->getId()][$externalId] = true;

            $this->log->debug(sprintf(
                'Creating new regular class involvement for %s in timetable event %s - %s, %s, %s-%s',
                $externalId,
                $event->getTitle(),
                $event->getDay(),
                $event->getPeriod(),
                $event->getStartAt()->getDatabase(),
                $event->getFinishAt()->getDatabase()
            ));
        }
    }
}

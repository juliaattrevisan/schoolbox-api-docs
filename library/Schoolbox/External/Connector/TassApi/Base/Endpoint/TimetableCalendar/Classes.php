<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\Code;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\DSN;
use Schoolbox\External\Event\Overrides\Model;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Import all the timetables for everyone.
 */
class Classes implements IStrategy
{
    /**
     * Number of days into the future whose timetable subject we import,
     * used to prevent importing billions of events from a distant future
     * that might not really have much relevance to today
     *
     * Defaults to 12 months but we can tune that later.
     */
    private const FUTURE_THRESHOLD = 30 * 12;


    /**
     * Number of days into the past whose timetable subject we import.
     * This is used to import past events so you can see what you did last week.
     *
     * Otherwise at the end of the current day you wouldn't be able to see the
     * events of that day if UPS ran and deleted those events because they were
     * just "in the past"
     */
    private const PAST_THRESHOLD = 14;


    /**
     * Filter to specific people; set to null to disable filtering
     */
    private const WATCHLIST = null;


    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private bool $perform;


    /**
     * @var array
     */
    private array $api;


    /**
     * @var Lookup
     */
    private Lookup $lookup;


    /**
     * This is the config setting for date_local
     * It's used by the Nat Picker in the optional TASS Academic Periods area
     *
     * @var string
     */
    private string $dateLocalFormat;


    /**
     * @var \Schoolbox\External\Event\Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;


    /**
     * @var ClassEntryMapper
     */
    private ClassEntryMapper $classEntryMapper;


    /**
     * @var ClassInvolvementMapper
     */
    private ClassInvolvementMapper $classInvolvementMapper;


    /**
     * @var \Schoolbox\External\Event\Overrides\Mapper
     */
    private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper;


    /**
     * A list of timetable calendar entries, which is a list of days
     *
     * @var array
     */
    private array $timetableCalendar = [];


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
     * A caching mechanism to prevent dupe events
     *
     * $eventEntries[$classCode][$startDate][$finishDate] = $event
     *
     * @var array
     */
    private array $eventEntries = [];


    /**
     * A caching mechanism to prevent dupe CLEAR overrides actions
     *
     * $overridesEntries[$classCode][$startDate][$finishDate] = true
     *
     * @var array
     */
    private array $overridesEntries = [];


    /**
     * Constructor
     *
     * @param LoggerInterface                            $log
     * @param bool                                       $perform
     * @param array                                      $api
     * @param Lookup                                     $lookup
     * @param string                                     $dateLocalFormat
     * @param \Schoolbox\External\Event\Mapper           $eventMapper
     * @param ClassEntryMapper                           $classEntryMapper
     * @param ClassInvolvementMapper                     $classInvolvementMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        array $api,
        Lookup $lookup,
        string $dateLocalFormat,
        \Schoolbox\External\Event\Mapper $eventMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->lookup = $lookup;
        $this->dateLocalFormat = $dateLocalFormat;
        $this->eventMapper = $eventMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->overridesMapper = $overridesMapper;
    }


    /**
     * Import all timetables for everyone
     *
     * @return void
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
//        $this->importStudentTimetables(self::WATCHLIST);
        $this->importStaffTimetables(self::WATCHLIST);
    }


    /**
     * Decide if $externalId is in $watchlist, which is a set of externalIds
     *
     * @param string|null $externalId
     * @param array|null  $watchlist
     * @return bool
     */
    private function isOnWatchlist(?string $externalId, ?array $watchlist): bool
    {
        /* "Everyone is on the watchlist"
         */
        if (null === $watchlist) {
            return true;
        }

        return $watchlist && in_array($externalId, $watchlist, false);
    }


    /**
     * Import all student timetables.
     *
     * @param array|null $watchlist
     * @return void
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function importStudentTimetables(?array $watchlist = null): void
    {
        /* Students
         * Find all the external ids
         */
        $students = $this->lookup->getStudents();

        foreach ($students as $externalId => $person) {
            /* Only import data for externalIds in $watchlist, if provided
             */
            if (!$this->isOnWatchlist($externalId, $watchlist)) {
                continue;
            }

            /* Hit That Api to find every student's timetable and cache it somewhere
             * We'll need to operate on it all again later anyway
             */
            $data = $this->api[DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getStudentTimetable', [
                'student_code' => $externalId
            ]);

            /* Check that the given $externalId had any timetable data at all
             */
            if (!(isset($data['student_timetable']) && \count($data['student_timetable']))) {
                $this->log->warning(sprintf(
                    'Timetable not found for %s (%s).',
                    trim("{$person['firstname']} {$person['lastname']}"),
                    $externalId
                ));

                continue;
            }

            /* Otherwise, find the timetables in which $now occurs
             * If $now isn't in the $timetable, skip it entirely (it's not relevant)
             *
             * The timetable endpoint getStudentTimetable havs the potential to return
             * multiple timetables - you have to find the one which contains $now
             */
            foreach ($data['student_timetable'] as $timetable) {
//                /* Only import data for externalIds in $watchlist, if provided
//                 */
//                if ($this->isOnWatchlist($externalId, $watchlist)) {
//                    print_r($timetable);
//                }

                $this->process($person, $timetable, false);
            }

            /* Example $timetable extract

                Array
                (
                    [HSE_CODE] => A
                    [DESC_TEXT] => Secondary School Timetable 2022/1
                    [HSE_NAME] => Ashkanasy
                    [DAYS] => Array
                        (
                            [11] => Array
                                (
                                    [PERIODS] => Array
                                        (
                                            [11] => Array
                                                (
                                                    [SUBJECTS] => Array
                                                        (
                                                            [0] => Array
                                                                (
                                                                    [SUB_CODE] => 1224
                                                                    [FROM_DATE] =>
                                                                    [SUB_SHORT] => RE
                                                                    [HASOVERRIDE] => 0
                                                                    [ROOM_CODE] => 504
                                                                    [TO_DATE] =>
                                                                    [TCH_CODE] => RIC
                                                                    [SUBTAB_YEAR_GRP_DESC] => 12
                                                                    [CLASS] => D
                                                                    [YEAR_GRP] =>
                                                                    [TT_PRIORITY] => 999
                                                                    [SUB_DESC] => Unit 3&4 Religion & Society
                                                                    [SUB_ABREV] => RE
                                                                    [LINE_CODE] => 12
                                                                    [SUBTAB_YEAR_GRP] => 12
                                                                    [DATE_RANGE] =>
                                                                )
                                                        )

                                                    [PRD_END] => 1900-01-01 15:30:00.0
                                                    [PRD_CODE] => 11
                                                    [PRD_START] => 1900-01-01 14:38:00.0
                                                    [PRD_DESC] => Period 6
                                                )

                                            [2] => Array
                                                (
                                                    [SUBJECTS] => Array
                                                        (
                                                            [0] => Array
                                                                (
                                                                    [SUB_CODE] => 1218
                                                                    [FROM_DATE] =>
                                                                    [SUB_SHORT] => MC
                                                                    [HASOVERRIDE] => 0
                                                                    [ROOM_CODE] => 131
                                                                    [TO_DATE] =>
                                                                    [TCH_CODE] => PEA
                                                                    [SUBTAB_YEAR_GRP_DESC] => 12
                                                                    [CLASS] => C
                                                                    [YEAR_GRP] =>
                                                                    [TT_PRIORITY] => 999
                                                                    [SUB_DESC] => Yr 12 Mechanech
                                                                    [SUB_ABREV] => MC
                                                                    [LINE_CODE] => 12
                                                                    [SUBTAB_YEAR_GRP] => 12
                                                                    [DATE_RANGE] =>
                                                                )
                                                        )

                                                    [PRD_END] => 1900-01-01 08:59:00.0
                                                    [PRD_CODE] => 2
                                                    [PRD_START] => 1900-01-01 08:50:00.0
                                                    [PRD_DESC] => Form Assembly
                                                )
            */
        }
    }


    /**
     * Import all student timetables.
     *
     * @param array|null $watchlist
     * @return void
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function importStaffTimetables(?array $watchlist = null): void
    {
        /* Staff
         * Find all the external ids
         */
        $staff = $this->lookup->getStaff();

        /* Make a naive guess as to what year and semester we are in
         * The getTeacherTimetable endpoint is different to students' version
         * because reasons - why the students endpoint doesn't need this is beyond me
         */
        $year = date('Y');

        $semester = (new SemesterDetector($this->log, $this->dateLocalFormat))->detect($this->api[DSN::MODULE_LMS_INTEGRATION]->getOptions());

        foreach ($staff as $externalId => $person) {
            /* Only import data for externalIds in $watchlist, if provided
             */
            if (!$this->isOnWatchlist($externalId, $watchlist)) {
                continue;
            }

            /* Hit That Api to find every student's timetable and cache it somewhere
             * We'll need to operate on it all again later anyway
             */
            $data = $this->api[DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getTeacherTimetable', [
                'teacher_code' => $externalId,
                'year_num' => $year,
                'semester' => $semester
            ]);

            /* Check that the given $externalId had any timetable data at all
             */
            if (!(isset($data['teacher_timetable']) && \count($data['teacher_timetable']))) {
                $this->log->warning(sprintf(
                    'Timetable not found for %s (%s).',
                    trim("{$person['firstname']} {$person['lastname']}"),
                    $externalId
                ));

                continue;
            }

            /* Otherwise, continue on with the first timetable element
             * (Not sure how many they can actually have in production)
             */
            foreach ($data['teacher_timetable'] as $timetable) {
//                /* Only import data for externalIds in $watchlist, if provided
//                 */
//                if ($this->isOnWatchlist($externalId, $watchlist)) {
//                    print_r($timetable);
//                }

                $this->process($person, $timetable, true);
            }

            /* Example $timetable extract

                Array
                (
                    [DESC_TEXT] => Primary School Timetable 2022/1
                    [DAYS] => Array
                        (
                            [1] => Array
                                (
                                    [PERIODS] => Array
                                        (
                                            [1] => Array
                                                (
                                                    [SUBJECTS] => Array
                                                        (
                                                            [0] => Array
                                                                (
                                                                    [SUB_CODE] => 5130
                                                                    [SUB_SHORT] => Y1Roll
                                                                    [HASOVERRIDE] => 0
                                                                    [ROOM_CODE] => GBH
                                                                    [TCH_CODE] => ABR
                                                                    [SUBTAB_YEAR_GRP_DESC] => 1
                                                                    [CLASS] => A
                                                                    [YEAR_GRP] =>
                                                                    [SUB_DESC] => Primary Y1 Rollcall
                                                                    [SUB_ABREV] =>
                                                                    [LINE_CODE] =>
                                                                    [SUBTAB_YEAR_GRP] => 1
                                                                )

                                                        )

                                                    [PRD_END] => 1900-01-01 08:49:00.0
                                                    [PRD_CODE] => 1
                                                    [PRD_START] => 1900-01-01 08:00:00.0
                                                    [PRD_DESC] => Rollcall
                                                )

                                            [9] => Array
                                                (
                                                    [SUBJECTS] => Array
                                                        (
                                                            [0] => Array
                                                                (
                                                                    [SUB_CODE] => 5130
                                                                    [SUB_SHORT] => Y1Roll
                                                                    [HASOVERRIDE] => 0
                                                                    [ROOM_CODE] => GBH
                                                                    [TCH_CODE] => ABR
                                                                    [SUBTAB_YEAR_GRP_DESC] => 1
                                                                    [CLASS] => A
                                                                    [YEAR_GRP] =>
                                                                    [SUB_DESC] => Primary Y1 Rollcall
                                                                    [SUB_ABREV] =>
                                                                    [LINE_CODE] =>
                                                                    [SUBTAB_YEAR_GRP] => 1
                                                                )

                                                        )

                                                    [PRD_END] => 1900-01-01 13:44:00.0
                                                    [PRD_CODE] => 9
                                                    [PRD_START] => 1900-01-01 13:17:00.0
                                                    [PRD_DESC] => Lunch Play
                                                )

                                        )

                                )
            */
        }
    }


    /**
     * Create individual SUDS Timetable events out of each element
     * inside this person's $timetable
     *
     * $deconflictAll treats every class in this timetable as one that needs
     * a specific set of participating people - yard duties, shared classes,
     * etc. all need deconflicting
     *
     * @param array $person
     * @param array $timetable
     * @param bool  $deconflictAll
     * @return void
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function process(array $person, array $timetable, bool $deconflictAll): void
    {
        $now = \Alaress_Date_Format::now();

        /* Timetable Calendar gives us a list of Days and Dates
         * Each entry in there represents a day in the timetable,
         * which contains multiple periods
         */
        $timetableCalendar = $this->fetchTimetableCalendar($timetable['TT_ID']);

        /* Decide if $timetableCalendar is one that's relevant to $now
         *
         * The endpoints getStudentTimetable/getTeacherTimetable have the potential to
         * return multiple elements, only some of which are appropriate to $now
         */
        if ((new ValidActiveTtid($timetableCalendar))->isActive()) {
            $this->log->debug(sprintf(
                'timetableCalendar %s is not relevant to today; skipping',
                $timetable['TT_ID']
            ));

            return;
        }

        /* For logging
         */
        $name = trim("{$person['firstname']} {$person['lastname']}");

        $this->log->info(sprintf(
            'Processing timetable for %s (%s)',
            $name,
            $person['external_id']
        ));

        /* For each entry in Timetable Calendar, set up a series of SUDS Timetable records
         * You can get a date here, but you'll need to make multiple records.
         *
         * To do it,
         * For each entry in Timetable Calendar, get the Day Code
         * then use that to look up the corresponding struct in $timetable
         * This will get us a list of Periods happening in that day,
         * and their times
         */
        foreach ($timetableCalendar as $dayDefinition) {
            $dayNumber = trim((string)$dayDefinition['DAY_CODE']);

            /* This is a day in the calendar where there isn't a Day Code
             * There is no roster of periods to assign to this date
             * It's probably a weekend, or a non-teaching day somehow
             *
             * Skip it
             */
            if ('' === $dayNumber) {
                continue;
            }

            $date = explode(' ', $dayDefinition['TTDATE'])[0];

            /* Fetch PAST_THRESHOLD days into the past, so people can see where they've been
             */
            $dateCheck = new \Alaress_Date_Format($date, \Alaress_Date_Format::DATABASE_DATE_FORMAT);

            if ($dateCheck->isPast() && $dateCheck->diffInDays($now) > self::PAST_THRESHOLD) {
                $this->log->debug(sprintf(
                    'Timetable Calendar record on %s is too far in the past; skipping',
                    $dateCheck->getDatabaseDate()
                ));

                continue;
            }

            /* Don't fetch too many from the future
             */
            if ($dateCheck->isFuture() && $dateCheck->diffInDays($now) > self::FUTURE_THRESHOLD) {
                $this->log->debug(sprintf(
                    'Timetable Calendar record on %s is too far into the future; skipping',
                    $dateCheck->getDatabaseDate()
                ));

                continue;
            }

            /* This timetable didn't have any classes on a particular day
             */
            if (!isset($timetable['DAYS'][$dayNumber])) {
                continue;
            }

            /* Process relevant dates
             */
            foreach ($timetable['DAYS'][$dayNumber]['PERIODS'] as $periodData) {
                /* Extract the useful time from this microsecond format
                 */
                $startTime = substr($periodData['PRD_START'], 11, 8);
                $endTime = substr($periodData['PRD_END'], 11, 8);

                foreach ($periodData['SUBJECTS'] as $subject) {
                    /* For each entry in $timetable[$day], create a new SUDS Timetable event
                     * Use the date that we got from the outer loop
                     * Combine it with the time in the current array element
                     * Save it
                     */
                    $startAt = new \Alaress_Date_Format("{$date} {$startTime}", \Alaress_Date_Format::DATABASE_FORMAT);
                    $finishAt = new \Alaress_Date_Format("{$date} {$endTime}", \Alaress_Date_Format::DATABASE_FORMAT);

                    $title = trim((string)$subject['SUB_DESC']);
                    $detail = '';
                    $day = "Day {$dayNumber}";
                    $room = trim((string)$subject['ROOM_CODE']);
                    $classCode = (new Code())->encode((string)$subject['SUBTAB_YEAR_GRP'], (string)$subject['SUB_CODE'], (string)$subject['CLASS']);
                    $period = "Period {$periodData['PRD_CODE']}";
                    $type = '';
                    $guid = '';

                    /* Try to find an event that already exists
                     * If it already exists, just continue; it's already been made
                     * Otherwise create a new one
                     *
                     * (Since we are processing each student sequentially there is
                     * potential for dupe events to spawn unnecessarily)
                     */
                    if (isset($this->eventEntries[$classCode][$startAt->getDatabase()][$finishAt->getDatabase()])) {
                        $this->log->debug(sprintf(
                            'Reusing existing timetable event %s on %s %s (%s-%s)',
                            $title,
                            $day,
                            $period,
                            $startAt->getDatabase(),
                            $finishAt->getDatabase()
                        ));

                        $event = $this->eventEntries[$classCode][$startAt->getDatabase()][$finishAt->getDatabase()];
                    } else {
                        /* Try to get an event corresponding to this lesson's (code/start/finish) tuple
                         * in an attempt to recycle an existing event, slowing the propagation of
                         * autoincrementing ids - if it exists, update it and save it
                         */
                        try {
                            $event = $this->eventMapper->findOneByColumnList([
                                'code' => $classCode,
                                'start_at' => $startAt->getDatabase(),
                                'finish_at' => $finishAt->getDatabase(),
                                'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
                                'source' => \Schoolbox\External\Connector\TassApi\Base\Endpoint\Factory::SOURCE_NAME
                            ]);
                        } catch (\Schoolbox\Mapper\Exception $e) {
                            /* Cram in everything else into a new instance
                             */
                            $event = $this->eventMapper->createModel()
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
                                ->setSource(\Schoolbox\External\Connector\TassApi\Base\Endpoint\Factory::SOURCE_NAME);
                        }

                        /* Save it and remember what we saw in case we need to reuse this later
                         */
                        $this->log->debug(sprintf(
                            'Creating new timetable event %s - %s, %s, %s-%s',
                            $event->getTitle(),
                            $event->getDay(),
                            $event->getPeriod(),
                            $event->getStartAt()->getDatabase(),
                            $event->getFinishAt()->getDatabase()
                        ));

                        /* Remember we did this
                         */
                        $this->eventEntries[$classCode][$startAt->getDatabase()][$finishAt->getDatabase()] = $event;

                        if ($this->perform) {
                            $this->eventMapper->save($event);
                        }
                    }

                    /* Save sis_class record
                     */
                    $classEntry = $this->createClassEntry($event);

                    /* Decide how involved humans are saved
                     */
                    if ($deconflictAll) {
                        /* FIXME SPECULATIVE PROGRAMMING
                         *
                         * If this event is a Duty (identified by room code DUT)
                         * and we haven't set up Overrides yet,
                         *     - add a CLEAR override action
                         *     - add an ADD override action for this (eventId, externalId) combo
                         *     - remember that we did this
                         *
                         * if we have already set up a CLEAR override action,
                         *     - create a new ADD override and put $externalId in
                         */
                        $this->deconflict($person, $event);
                    } else {
                        /* Create a new involvement for this event if one doesn't already exist
                         * (This superpositions on top of Additional Subjects)
                         */
                        $this->saveInvolvement($event, $classEntry, $name, $person['external_id']);
                    }
                }
            }
        }
    }


    /**
     * FIXME SPECULATIVE PROGRAMMING
     *
     * Decide if $event is one of those communal Duty events
     * Clear everyone's involvement then add specific people for a given date
     *
     * @param \Schoolbox\External\Event\Model $event
     * @return bool
     */
    private function isDuty(\Schoolbox\External\Event\Model $event): bool
    {
        /* If the location is DUT, it's a duty
         */
        return 'DUT' === $event->getLocation();
    }


    /**
     * FIXME SPECULATIVE PROGRAMMING
     *
     * For each duty, clear everyone's calculated involvement
     * and only add the guys specific to this $event
     *
     * @param array                           $person
     * @param \Schoolbox\External\Event\Model $event
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function deconflict(array $person, \Schoolbox\External\Event\Model $event): void
    {
        $classCode = $event->getCode();
        $startAt = $event->getStartAt()->getDatabase();
        $finishAt = $event->getFinishAt()->getDatabase();

        $this->log->debug(sprintf(
            'Establishing overrides for %s in %s',
            $person['external_id'],
            $event->getTitle()
        ));

        /* Set up a CLEAR action, with a hardcoded sequence 1
         * This must always happen first
         */
        if (!isset($this->overridesEntries[$classCode][$startAt][$finishAt])) {
            $this->log->debug('Setting up CLEAR override');

            $overrideClear = $this->overridesMapper->createModel()
                ->setEventId($event->getId())
                ->setExternalId($person['external_id'])
                ->setAction(Model::CLEAR)
                ->setSequence(1);

            if ($this->perform) {
                $this->overridesMapper->save($overrideClear);
            }

            /* Remember we did this
             */
            $this->overridesEntries[$classCode][$startAt][$finishAt] = true;
        }

        /* Add a guy (doesn't matter what ordering these happen;
         * just hardcode it to 2 for now)
         */
        $overrideAdd = $this->overridesMapper->createModel()
            ->setEventId($event->getId())
            ->setExternalId($person['external_id'])
            ->setAction(Model::ADD)
            ->setSequence(2);

        if ($this->perform) {
            $this->overridesMapper->save($overrideAdd);
        }
    }


    /**
     * Create (or reuse) a ClassEntry corresponding to $event
     *
     * @param \Schoolbox\External\Event\Model $event
     * @return ClassEntry
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function createClassEntry(\Schoolbox\External\Event\Model $event): ClassEntry
    {
        if (isset($this->classEntries[$event->getCode()])) {
            $classEntry = $this->classEntries[$event->getCode()];
        } else {
            try {
                $classEntry = $this->classEntryMapper->findOneByColumn('code', $event->getCode());
            } catch (\Schoolbox\Mapper\Exception $e) {
                $classEntry = $this->classEntryMapper->createModel()
                    ->setName($event->getTitle())
                    ->setCode($event->getCode());
            }

            if ($this->perform) {
                $this->classEntryMapper->save($classEntry);
            }

            $this->classEntries[$event->getCode()] = $classEntry;
        }

        return $classEntry;
    }


    /**
     * Save an involvement for user $externalId, whose name is $name,
     * for the sis_event $event
     *
     * @param \Schoolbox\External\Event\Model $event
     * @param ClassEntry                      $classEntry
     * @param string                          $name
     * @param string                          $externalId
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function saveInvolvement(
        \Schoolbox\External\Event\Model $event,
        ClassEntry $classEntry,
        string $name,
        string $externalId
    ): void {
        if (isset($this->classInvolvements[$classEntry->getId()][$externalId])) {
            $this->log->debug(sprintf(
                'Reusing previous class involvement for %s (%s) in timetable event %s - %s, %s, %s-%s',
                $name,
                $externalId,
                $event->getTitle(),
                $event->getDay(),
                $event->getPeriod(),
                $event->getStartAt()->getDatabase(),
                $event->getFinishAt()->getDatabase()
            ));
        } else {
            try {
                $classInvolvement = $this->classInvolvementMapper->findOneByColumnList([
                    'class_id' => $classEntry->getId(),
                    'external_id' => $externalId
                ]);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $classInvolvement = $this->classInvolvementMapper->createModel()
                    ->setClass($classEntry)
                    ->setExternalId($externalId);
            }

            if ($this->perform) {
                $this->classInvolvementMapper->save($classInvolvement);
            }

            $this->classInvolvements[$classEntry->getId()][$externalId] = true;

            $this->log->debug(sprintf(
                'Creating new class involvement for %s (%s) in timetable event %s - %s, %s, %s-%s',
                $name,
                $externalId,
                $event->getTitle(),
                $event->getDay(),
                $event->getPeriod(),
                $event->getStartAt()->getDatabase(),
                $event->getFinishAt()->getDatabase()
            ));
        }
    }


    /**
     * Get the Timetable Calendar for a particular TTID
     * if we haven't already done so
     *
     * We use this to find out the date/day combo and match
     * it with periods. It's essentially half of the classical
     * timetable grid Definition; the other half comes from
     * the timetable itself, which is a list of periods and their
     * times/days (but not their actual dates)
     *
     * So we have to marry the two datasets into SUDS Timetable
     *
     * @param int $ttid
     * @return array
     */
    private function fetchTimetableCalendar(int $ttid): array
    {
        if (!isset($this->timetableCalendar[$ttid])) {
            $this->timetableCalendar[$ttid] = $this->api[DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getTimetableCalendar', [
                'tt_id' => $ttid
            ]);
        }

        /* Example extract
            [tt_calendar] => Array
                (
                    [0] => Array
                        (
                            [CAT_NUM] =>
                            [CMPY_CODE] => 01
                            [TTDATE] => 2022-02-01 00:00:00.0
                            [RECORD_ID] => 22324
                            [LABEL] => Tue 01/02/2022
                            [ID] => 63-2022-02-01
                            [TABLE_ID] => ttcalendar
                            [VALUE] => 2022-02-01
                            [TT_ID] => 63
                            [DAY_CODE] => 2
                        )

                    [1] => Array
                        (
                            [CAT_NUM] =>
                            [CMPY_CODE] => 01
                            [TTDATE] => 2022-02-02 00:00:00.0
                            [RECORD_ID] => 22325
                            [LABEL] => Wed 02/02/2022
                            [ID] => 63-2022-02-02
                            [TABLE_ID] => ttcalendar
                            [VALUE] => 2022-02-02
                            [TT_ID] => 63
                            [DAY_CODE] => 3
                        )
         */

        return $this->timetableCalendar[$ttid]['tt_calendar'];
    }
}

<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Wonde\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Connector\Wonde\Base\Endpoint\IApi;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Import all the classes for a student, saving them as sis_event records
 * with the 'timetable' classification
 */
class Classes implements IStrategy
{

    /**
     * Prevent infinite loop of api calls
     */
    private const MAX_CALLS = 100;


    /**
     * Number of records per page
     * The maximum is an unwritten 200 or so, apparently
     */
    private const NUM_PER_PAGE = 200;


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
     * @var IApi
     */
    private IApi $api;


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
     * @var \DateTimeZone
     */
    private \DateTimeZone $timezone;


    /**
     * A date string in the format Y-m-d e.g. 2020-10-12
     *
     * @var string
     */
    private string $fromDate;


    /**
     * A date string in the format Y-m-d e.g. 2020-10-12
     *
     * @var string
     */
    private string $toDate;


    /**
     * A list of event guids that we saw previously
     *
     * @var array
     */
    private array $seen = [];


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
     *
     * @param LoggerInterface                            $log
     * @param bool                                       $perform
     * @param IApi                                       $api
     * @param Mapper                                     $eventMapper
     * @param ClassEntryMapper                           $classEntryMapper
     * @param ClassInvolvementMapper                     $classInvolvementMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     * @param \DateTimeZone                              $timezone
     * @param string                                     $fromDate
     * @param string                                     $toDate
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        IApi $api,
        \Schoolbox\External\Event\Mapper $eventMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
        \DateTimeZone $timezone,
        string $fromDate,
        string $toDate
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->eventMapper = $eventMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;

        /* Temporarily disabled while we wait for feedback from schools
         * $this->overridesMapper = $overridesMapper;
         */

        $this->timezone = $timezone;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }


    /**
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        $this->log->info('Processing timetables.');

        $this->log->debug('Preloading student involvements for all the classes.');

        $studentInvolvements = $this->preloadStudentInvolvements();

        $this->log->debug(sprintf(
            'Processing lessons between %s and %s',
            $this->fromDate,
            $this->toDate
        ));

        $nextUrl = sprintf(
            'lessons?page=%s&per_page=%s&lessons_start_after=%s&lessons_start_before=%s&include=%s',
            1,
            self::NUM_PER_PAGE,
            $this->fromDate,
            $this->toDate,
            implode(',', [
                'class',
                'employee',
                'period',
                'room'
            ])
        );

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $lessons = $this->api->retrieve('GET', $nextUrl, []);

            foreach ($lessons['data'] as $lesson) {
                /* Save main event info - see if one already exists
                 * if it has the same name, place, day, period and time
                 * If you have five columns the same, you're probably
                 * looking at an event that we've already loaded before
                 */
                $guid = $lesson['id'];

                /* Try to recycle a previous instance
                 */
                if (isset($this->seen[$guid])) {
                    $event = $this->seen[$guid];

                    $this->log->debug(sprintf(
                        'Reusing previously seen timetable event %s - %s, %s, %s-%s',
                        $event->getTitle(),
                        $event->getDay(),
                        $event->getPeriod(),
                        $event->getStartAt()->getDatabase(),
                        $event->getFinishAt()->getDatabase()
                    ));
                } else {
                    $room = null;

                    /* Rooms are optional and come with two data points - name and code
                     * Example wonde data had a meaningful name in the $code e.g. '3a HomeRoom'
                     * and a less meaningful name in the $name e.g. '401'
                     */
                    if (is_array($lesson['room'])) {
                        $room = "{$lesson['room']['data']['name']} ({$lesson['room']['data']['code']}";
                    }

                    /* Wonde gives you this data struct for dates

                        [start_at] => Array
                            (
                                [date] => 2022-03-28 00:55:00.000000
                                [timezone_type] => 3
                                [timezone] => UTC
                            )
                    */
                    $startAt = (new \Alaress_Date_Format(explode('.', $lesson['start_at']['date'])[0], \Alaress_Date_Format::DATABASE_FORMAT, new \DateTimeZone($lesson['start_at']['timezone'])))
                        ->setTimezone($this->timezone);

                    $finishAt = (new \Alaress_Date_Format(explode('.', $lesson['end_at']['date'])[0], \Alaress_Date_Format::DATABASE_FORMAT, new \DateTimeZone($lesson['end_at']['timezone'])))
                        ->setTimezone($this->timezone);

                    $event = $this->eventMapper->createModel()
                        ->setTitle($lesson['class']['data']['name'])
                        ->setDetail(null)
                        ->setLocation($room)
                        ->setCode($lesson['class']['data']['code'])
                        ->setDay($lesson['period']['data']['day'])
                        ->setPeriod($lesson['period']['data']['name'])
                        ->setStartAt($startAt)
                        ->setFinishAt($finishAt)
                        ->setType(null)
                        ->setClassification(\Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE)
                        ->setGuid($lesson['id'])
                        ->setSource(Prepare::EVENT_IMPORT_IDENTIFIER);
                }

                if ($this->perform) {
                    $this->eventMapper->save($event);
                }

                $this->log->debug(sprintf(
                    'Creating new timetable event %s - %s, %s, %s-%s',
                    $event->getTitle(),
                    $event->getDay(),
                    $event->getPeriod(),
                    $event->getStartAt()->getDatabase(),
                    $event->getFinishAt()->getDatabase()
                ));

                $this->seen[$guid] = $event;

                /* Save sis_class record
                 */
                if (isset($this->classEntries[$event->getCode()])) {
                    $classEntry = $this->classEntries[$event->getCode()];
                } else {
                    $classEntry = $this->classEntryMapper->createModel()
                        ->setName($lesson['class']['data']['name'])
                        ->setCode($lesson['class']['data']['code']);

                    if ($this->perform) {
                        $this->classEntryMapper->save($classEntry);
                    }

                    $this->classEntries[$event->getCode()] = $classEntry;
                }

                /* Save sis_class_user involvements
                 * This is the teacher
                 */
                $externalId = $lesson['employee']['data']['mis_id'];
                $name = sprintf(
                    '%s %s %s',
                    $lesson['employee']['data']['title'],
                    $lesson['employee']['data']['forename'],
                    $lesson['employee']['data']['surname']
                );

                $this->saveInvolvement($event, $classEntry, $name, $externalId);

                /* Save sis_class_user involvements
                 * This is the student(s)
                 */
                $classCode = $lesson['class']['data']['code'];

                /* No students involved in $classCode, I guess
                 */
                if (!isset($studentInvolvements[$classCode])) {
                    $this->log->debug(sprintf(
                        '%s: no students involved',
                        $classCode
                    ));

                    continue;
                }

                $involvements = $studentInvolvements[$classCode];

                $this->log->debug(sprintf(
                    '%s: %s students involved',
                    $classCode,
                    \count($involvements)
                ));

                foreach ($involvements as $externalId => $name) {
                    $this->saveInvolvement($event, $classEntry, $name, (string)$externalId);
                }
            }

            /* If there's a pagination struct, there's more data to come...
             * But make sure that the 'next' element exists before trying to go there
             */
            $nextUrl = false;

            if (isset($lessons['meta']['pagination'])) {
                $nextUrl = $lessons['meta']['pagination']['next'] ?? false;
            }
        }
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
            $classInvolvement = $this->classInvolvementMapper->createModel()
                ->setClass($classEntry)
                ->setExternalId($externalId);

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
     *
     * FIXME Ask Colin - find a less-wonky way to do this. imho class['subject'] should
     *  have a value, but doesn't, in the wonde test data (is their bug or valid data?)
     *
     * First dig out the Wonde class objects which is where the students live
     * You can find it by the class name, because you don't get the subject identifier
     * at the moment, for some reason - normally it lives in [class][data][subject]
     * from what I can infer (it's just always blank)
     *
     * class_name is inconclusive - there are multiple class codes per class name
     * so you end up having to sift multiple times through the same thing
     * e.g. "Health and Physical Education 07" has four individual class codes
     * associated with it - 07HPEDA 07HPEDB 07HPEDC 07HPEDD
     *
     * Anyway, since class_subject is often useless and class_name is inconclusive,
     * just load everything into a set of (student external id, student fullname) tuples
     *
     * @return array
     */
    private function preloadStudentInvolvements(): array
    {
        $nextUrl = sprintf(
            'classes?'. implode('&', [
                'page=%s',
                'per_page=%s',
                'has_students=%s',
                'has_lessons=%s',
                'include=%s'
            ]),
            1,
            self::NUM_PER_PAGE,
            true,
            true,
            implode(',', [
                'students',
                'subject'
            ])
        );

        $stack = [];

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $classes = $this->api->retrieve('GET', $nextUrl, []);

            foreach ($classes['data'] as $class) {
                $classCode = $class['code'];

                /* Build a big list of students involved with $classCode
                 */
                foreach ($class['students']['data'] as $student) {
                    $stack[$classCode][(string)$student['mis_id']] = sprintf(
                        '%s %s',
                        $student['forename'],
                        $student['surname']
                    );
                }
            }

            /* If there's a pagination struct, there's more data to come...
             * But make sure that the 'next' element exists before trying to go there
             */
            $nextUrl = false;

            if (isset($classes['meta']['pagination'])) {
                $nextUrl = $classes['meta']['pagination']['next'] ?? false;
            }
        }

        return $stack;
    }
}
